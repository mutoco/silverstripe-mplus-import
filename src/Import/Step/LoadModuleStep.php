<?php

namespace Mutoco\Mplus\Import\Step;

use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ReferenceCollector;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;
use Mutoco\Mplus\Util;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use Tree\Node\Node;

/**
 * Loads a single model from the API and resolves all necessary relations to import the model
 * @package Mutoco\Mplus\Import\Step
 */
class LoadModuleStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;
    protected ?TreeNode $resultTree;
    protected ?Node $allowedPaths = null;

    /**
     * LoadModuleStep constructor.
     * @param string $module - the name of the Mplus Module, eg. "Exhibition"
     * @param string $id - the ID of the module
     * @param TreeNode|null $resultTree - if this is set, this tree will be used instead of sending a request to the API
     */
    public function __construct(string $module, string $id, ?TreeNode $resultTree = null)
    {
        $this->module = $module;
        $this->id = $id;
        $this->resultTree = $resultTree;
        if ($this->resultTree) {
            $this->resultTree->setParent(null);
        }
        $this->allowedPaths = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getResultTree(): ?TreeNode
    {
        return $this->resultTree;
    }

    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_LOAD;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        $this->allowedPaths = Util::pathsToTree($engine->getConfig()->getImportPaths($this->module));
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        if ($engine->getBackend()->hasImportedTree($this->module, $this->id)) {
            return false;
        }

        if ($this->resultTree) {
            return $this->resolveTree($engine);
        }

        if ($result = $this->loadModule($engine, $this->module, $this->id, $this->allowedPaths)) {
            $this->resultTree = $result;
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        if ($this->resultTree) {
            $cfg = $engine->getConfig()->getModuleConfig($this->module);
            $instance = isset($cfg['modelClass']) ? Injector::inst()->create($cfg['modelClass']) : null;

            if ($instance) {
                // Allow extensions to transform the result tree (eg. remove nodes or similar)
                $instance->invokeWithExtensions('transformMplusResultTree', $this->resultTree, $engine);
            }

            // Store the fully resolved tree result in the backend
            $engine->getBackend()->setImportedTree($this->module, $this->id, $this->resultTree);

            if ($instance) {
                $result = $instance->invokeWithExtensions('shouldImportMplusModule', $this->resultTree, $engine);
                if (empty($result) || min($result) !== false) {
                    $engine->addStep(new ImportModuleStep($this->module, $this->id));
                } else {
                    // Exit if import was explicitly prevented
                    $this->resultTree = null;
                    return;
                }
            }

            // Collect all references again. Everything that is left now should be an external module
            $visitor = new ReferenceCollector();
            $references = $this->resultTree->accept($visitor);

            $useSearch = $engine->getUseSearchToResolve();
            $moduleMap = [];

            /** @var TreeNode $reference */
            foreach ($references as $reference) {
                if (!$reference->isResolved() && ($moduleName = $reference->getModuleName()) && ($id = $reference->moduleItemId)) {
                    if ($useSearch) {
                        $moduleMap[$moduleName][] = $id;
                    } else {
                        $engine->addStep(new LoadModuleStep($moduleName, $id));
                    }
                }
            }

            foreach ($moduleMap as $module => $ids) {
                // Reduce the set of IDs to only the ones that have not been imported yet
                $cleanedIds = array_diff($ids, $engine->getBackend()->getImportedIds($module));
                if (!empty($cleanedIds)) {
                    $search = $this->buildSearch($module, $cleanedIds, $engine);
                    if ($instance) {
                        $instance->invokeWithExtensions('beforeMplusSearchRelated', $search, $module, $cleanedIds);
                    }
                    $step = new LoadSearchStep($search);
                    $step->setDefaultPriority(ImportEngine::PRIORITY_LOAD);
                    $engine->addStep($step);
                }
            }
        }

        $this->resultTree = null;
    }

    protected function buildSearch(string $module, array $ids, ImportEngine $engine): SearchBuilder
    {
        $search = new SearchBuilder($module);
        $pathTree = Util::pathsToTree($engine->getConfig()->getImportPaths($module));
        $search->setSelect(Util::getSearchPaths($pathTree));

        $expert = array_map(function ($id) {
            return [
                'type' => 'equalsField',
                'fieldPath' => '__id',
                'operand' => $id
            ];
        }, $ids);

        if (count($expert) > 1) {
            $expert = ['or' => $expert];
        }

        $cfg = $engine->getConfig()->getModuleConfig($module);

        if ($engine->getImportOnlyNewer() && isset($cfg['modelClass'])) {
            $list = DataObject::get($cfg['modelClass']);
            if ($imported = $engine->getBackend()->getImportedIds($module)) {
                $list = $list->exclude('MplusID', $imported);
            }
            if ($oldestImport = $list->min('Imported')) {
                $expert = [
                    'and' => array_merge([[
                        'type' => 'greater',
                        'fieldPath' => '__lastModified',
                        'operand' => substr($oldestImport, 0, 10)
                    ]], $expert)
                ];
            }
        }

        $search->setExpert($expert);
        return $search;
    }

    protected function resolveTree(ImportEngine $engine): bool
    {
        // Collect all references from the resulting tree.
        // References are links to external modules
        $visitor = new ReferenceCollector();
        $references = $this->resultTree->accept($visitor);

        /** @var TreeNode $reference */
        foreach ($references as $reference) {
            if ($reference->isResolved()) {
                continue;
            }

            $segments = $reference->getPathSegments();
            // Look up the node inside the tree of allowed paths
            if ($pathNode = Util::findNodeForPath($segments, $this->allowedPaths)) {
                // If the node has sub-nodes, it needs to resolve first
                if (!$pathNode->isLeaf()) {
                    // Check if there are unresolved sub-paths
                    $hasUnresolved = false;
                    foreach ($pathNode->getChildren() as $segment) {
                        if (!$reference->getNestedNode($segment->getValue()) && !$reference->getNestedValue($segment->getValue())) {
                            $hasUnresolved = true;
                            break;
                        }
                    }
                    $moduleName = $reference->getModuleName();
                    $id = $reference->moduleItemId;

                    if ($hasUnresolved && (!$moduleName || !$id)) {
                        throw new ImportException(sprintf(
                            'Missing module and id to resolve relation "%s" on "%s"',
                            join('.', $segments),
                            $this->module
                        ));
                    }

                    //TODO: Find solution for attributes?
                    //TODO: Batch the missing references with the search API
                    if ($hasUnresolved) {
                        if ($result = $this->loadModule($engine, $moduleName, $id, $pathNode)) {
                            foreach ($pathNode->getChildren() as $segment) {
                                if ($resultNode = $result->getNestedNode($segment->getValue())) {
                                    $reference->addChild($resultNode);
                                }
                            }

                            $reference->markResolved();
                            return true;
                        } else {
                            throw new ImportException(sprintf(
                                'Unable to import %s (#%s) from the API',
                                $moduleName,
                                $id
                            ));
                        }
                    }
                }
            } else {
                throw new ImportException(sprintf(
                    'Reference "%s" in Module "%s" can not be resolved as it doesn\'t match a valid path',
                    join('.', $segments),
                    $this->module
                ));
            }
        }

        return false;
    }

    protected function loadModule(ImportEngine $engine, string $module, string $id, Node $allowedPaths): ?TreeNode
    {
        //TODO: Cache results to reduce API calls
        $stream = $engine->getApi()->queryModelItem($module, $id);
        if ($stream) {
            $parser = new Parser();
            $clone = Util::cloneTree($allowedPaths);
            $clone->setValue(null);
            $parser->setAllowedPaths($clone);
            return $parser->parse($stream);
        }
        return null;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->module = $this->module;
        $obj->id = $this->id;
        $obj->resultTree = $this->resultTree;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->module = $obj->module;
        $this->id = $obj->id;
        $this->resultTree = $obj->resultTree;
    }
}
