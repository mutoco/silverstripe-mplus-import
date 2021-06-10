<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ReferenceCollector;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;
use Mutoco\Mplus\Util;
use Tree\Node\Node;

class LoadModuleStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;
    protected ?TreeNode $resultTree;
    protected ?Node $allowedPaths;
    protected \SplQueue $pendingNodes;

    public function __construct(string $module, string $id)
    {
        $this->module = $module;
        $this->id = $id;
        $this->resultTree = null;
        $this->allowedPaths = null;
        $this->pendingNodes = new \SplQueue();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_LOAD;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        $this->resultTree = null;
        $this->pendingNodes = new \SplQueue();
        $this->allowedPaths = Util::pathsToTree($engine->getConfig()->getImportPaths($this->module));
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        if ($engine->getRegistry()->hasImportedTree($this->module, $this->id)) {
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
            // Store the full tree result in the registry
            $engine->getRegistry()->setImportedTree($this->module, $this->id, $this->resultTree);

            $cfg = $engine->getConfig()->getModuleConfig($this->module);
            if (isset($cfg['modelClass'])) {
                $engine->addStep(new ImportModuleStep($this->module, $this->id), ImportEngine::QUEUE_IMPORT);
            }

            // Collect all references again. Everything that is left now should be an external module
            $visitor = new ReferenceCollector();
            $references = $this->resultTree->accept($visitor);
            /** @var TreeNode $reference */
            foreach ($references as $reference) {
                if (($moduleName = $reference->getModuleName()) && ($id = $reference->moduleItemId)) {
                    $engine->addStep(new LoadModuleStep($moduleName, $id));
                }
            }
        }

        $this->resultTree = null;
    }

    protected function resolveTree(ImportEngine $engine): bool
    {
        // Collect all references from the resulting tree.
        // References are links to external modules
        $visitor = new ReferenceCollector();
        $references = $this->resultTree->accept($visitor);

        /** @var TreeNode $reference */
        foreach ($references as $reference) {
            $segments = $reference->getPathSegments();
            // Look up the node inside the tree of allowed paths
            if ($pathNode = Util::findNodeForPath($segments, $this->allowedPaths)) {
                // If the node has sub-nodes, it needs to resolve first
                if (!$pathNode->isLeaf()) {
                    //TODO: Find solution for attributes?
                    if (
                        ($moduleName = $reference->getModuleName()) &&
                        ($id = $reference->moduleItemId) &&
                        ($result = $this->loadModule($engine, $moduleName, $id, $pathNode))
                    ) {
                        foreach ($pathNode->getChildren() as $segment) {
                            if ($resultNode = $result->getNestedNode($segment->getValue())) {
                                $reference->addChild($resultNode);
                            }
                        }
                        return true;
                    }
                }
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
            $parser->setAllowedPaths($allowedPaths);
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
        $obj->pendingNodes = $this->pendingNodes;
        $obj->allowedPaths = Util::treeToPaths($this->allowedPaths);
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->module = $obj->module;
        $this->id = $obj->id;
        $this->resultTree = $obj->resultTree;
        $this->pendingNodes = $obj->pendingNodes;
        $this->allowedPaths = Util::pathsToTree($obj->allowedPaths);
    }
}
