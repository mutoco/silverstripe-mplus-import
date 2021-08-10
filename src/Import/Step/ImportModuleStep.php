<?php

namespace Mutoco\Mplus\Import\Step;

use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Model\VocabularyItem;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

class ImportModuleStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;
    protected ?TreeNode $tree = null;
    protected ?DataObject $target = null;

    public function __construct(string $module, string $id, ?TreeNode $tree = null)
    {
        $this->module = $module;
        $this->id = $id;
        $this->tree = $tree;
        $this->target = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_IMPORT;
    }

    public function getTree(): ?TreeNode
    {
        return $this->tree;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        // If the exact same module was already imported it's safe to skip
        if ($engine->getBackend()->hasImportedModule($this->module, $this->id)) {
            return;
        }

        if (empty($engine->getBackend()->getImportedIds($this->module))) {
            $engine->addStep(new CleanupRecordsStep($this->module));
        }

        if (!$this->tree) {
            $this->tree = $engine->getBackend()->getImportedTree($this->module, $this->id);
        }

        if (!$this->tree) {
            throw new ImportException(sprintf(
                'Cannot import module (%s #%s) without an imported tree',
                $this->module,
                $this->id
            ));
        }
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        // If the exact same module was already imported it's safe to skip
        if ($engine->getBackend()->hasImportedModule($this->module, $this->id)) {
            return false;
        }

        $config = $engine->getConfig()->getModuleConfig($this->module);
        $this->target = $this->createOrUpdate($config, $this->tree, $engine, $isSkipped);

        if (!$isSkipped) {
            if (isset($config['attachment'])) {
                $result = $this->target->invokeWithExtensions(
                    'mplusShouldImportAttachment',
                    $config['attachment'],
                    $this->tree,
                    $engine
                );
                if (empty($result) || min($result) !== false) {
                    $engine->addStep(new ImportAttachmentStep($this->module, $this->id));
                }
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        if ($engine->getBackend()->hasImportedModule($this->module, $this->id)) {
            return;
        }

        $config = $engine->getConfig()->getModuleConfig($this->module);

        foreach ($config['relations'] as $relationName => $relationCfg) {
            $nodes = $this->tree->getNodesMatchingPath($relationCfg['name']);
            if (empty($nodes)) {
                continue;
            }
            $ids = [];

            foreach ($nodes as $collection) {
                foreach ($collection->getChildren() as $child) {
                    if ($child instanceof TreeNode) {
                        $results = $this->target->invokeWithExtensions(
                            'shouldImportMplusRelation',
                            $relationName,
                            $child,
                            $engine
                        );

                        if (!empty($results) && min($results) === false) {
                            continue;
                        }

                        if (!$child->isReferenceNode()) {
                            // Import related models that are part of the current tree
                            $engine->addStep(new ImportModuleStep($relationCfg['type'], $child->getId(), $child));
                        }
                        $data = [];
                        if (isset($relationCfg['fields'])) {
                            foreach ($relationCfg['fields'] as $field => $path) {
                                $sharedParent = $child->getSharedParent($path);
                                $sharedPath = $sharedParent->getPath();
                                // Check if the shared path is part of the current path
                                if ($sharedPath && str_starts_with($path, $sharedPath . '.')) {
                                    // Query the shared node with only the portion that is extra to the shared path
                                    $node = $sharedParent->getNestedNode(substr($path, strlen($sharedPath) + 1));
                                } else {
                                    $node = $this->tree->getNestedNode($path);
                                }
                                $results = $this->target->invokeWithExtensions(
                                    'transformMplusRelationField',
                                    $field,
                                    $node,
                                    $engine
                                );

                                if (!empty($results)) {
                                    $data[$field] = $results[0];
                                } elseif ($node) {
                                    // Special treatment for Vocabulary items
                                    if (
                                        $node->getTag() === 'vocabularyReference' &&
                                        ($item = VocabularyItem::findOrCreateFromNode($node, $engine))
                                    ) {
                                        $engine->getBackend()->reportImportedModule(
                                            'VocabularyItem',
                                            $item->MplusID
                                        );

                                        if (($group = $item->VocabularyGroup()) && $group->exists()) {
                                            $engine->getBackend()->reportImportedModule(
                                                'VocabularyGroup',
                                                $group->MplusID
                                            );
                                        }
                                        $data[$field] = $item->ID;
                                    } else {
                                        $data[$field] = $node->getValue();
                                    }
                                }
                            }
                        }
                        $ids[$child->getId()] = $data;
                    }
                }
            }

            if (!empty($ids)) {
                // If there are IDs, link the relation
                $engine->addStep(new LinkRelationStep(
                    $this->target->getClassName(),
                    $this->target->MplusID,
                    $relationName,
                    $ids
                ));
            } else {
                // If there are no IDs, clean the relation
                $engine->addStep(new CleanupRelationStep(
                    $this->target->getClassName(),
                    $this->target->MplusID,
                    $relationName,
                    []
                ));
            }
        }

        $engine->getBackend()->reportImportedModule($this->module, $this->id);
    }

    protected function createOrUpdate(
        array $config,
        TreeNode $tree,
        ImportEngine $engine,
        &$skipped = false
    ): DataObject {
        $modelClass = $config['modelClass'] ?? null;
        $id = $this->getId();

        if (!$modelClass || !$id) {
            throw new ImportException('Cannot import Module without modelClass or ID');
        }

        $existing = DataObject::get_one($modelClass, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($modelClass);

        if (!$target->hasExtension(DataRecordExtension::class)) {
            throw new ImportException(sprintf(
                'Dataobject import target (%s) needs to have the DataRecordExtension',
                $modelClass
            ));
        }

        // Skip over existing records that were not modified remotely
        if ($target->isInDB()) {
            $lastModified = 0;
            if ($lastModifiedField = $tree->getNestedNode('__lastModified')) {
                $lastModified = strtotime($lastModifiedField->getValue());
            }

            if ($lastModified > 0 && $lastModified <= strtotime($target->Imported)) {
                // Get result from skip call and filter out any `null` value returns
                $skipCallbackResult = $target->invokeWithExtensions(
                    'beforeMplusSkip',
                    $this,
                    $engine
                );
                // If any callback returned false, we won't skip
                if (empty($skipCallbackResult) || min($skipCallbackResult) !== false) {
                    $skipped = true;
                    return $target;
                }
            }
        }

        $target->invokeWithExtensions('beforeMplusImport', $this, $engine);

        foreach ($config['fields'] as $fieldName => $mplusName) {
            // Skip ID
            if ($fieldName === 'MplusID') {
                continue;
            }

            if ($target->hasDatabaseField($fieldName) && ($fieldNode = $tree->getNestedNode($mplusName))) {
                $results = $target->invokeWithExtensions(
                    'transformMplusFieldValue',
                    $fieldName,
                    $fieldNode,
                    $engine
                );
                $target->setField($fieldName, empty($results) ? $fieldNode->getValue() : $results[0]);
            }
        }

        $target->MplusID = $id;
        $target->Module = $this->module;
        $target->setField('Imported', DBDatetime::now());

        $target->write();
        $target->invokeWithExtensions('afterMplusImport', $this, $engine);
        return $target;
    }


    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();

        // When possible, we also serialize the direct parent, as it sometimes contains important attributes
        if ($this->tree && ($parent = $this->tree->getParent())) {
            $copy = $parent->getCopy();
            $copy->addChild($this->tree);
            $obj->tree = $copy;
            $obj->withParent = true;
        } else {
            $obj->withParent = false;
            $obj->tree = $this->tree;
        }
        $obj->module = $this->module;
        $obj->id = $this->id;
        if ($this->target) {
            $obj->targetClass = $this->target->getClassName();
            $obj->targetId = $this->target->ID;
        }
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->tree = $obj->tree;

        if ($obj->withParent) {
            $this->tree = $this->tree->getChildren()[0];
        }

        $this->module = $obj->module;
        $this->id = $obj->id;
        if (isset($obj->targetClass)) {
            $this->target = DataObject::get_by_id($obj->targetClass, $obj->targetId);
        }
    }
}
