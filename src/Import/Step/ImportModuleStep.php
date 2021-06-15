<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\ImportEngine;
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
    protected ?TreeNode $tree;

    protected ?DataObject $target;

    public function __construct(string $module, string $id, ?TreeNode $tree = null)
    {
        $this->module = $module;
        $this->id = $id;
        $this->tree = $tree;
        $this->target = null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_IMPORT;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        // If the exact same module was already imported it's safe to skip
        if ($engine->getRegistry()->hasImportedModule($this->module, $this->id)) {
            return false;
        }

        if (empty($engine->getRegistry()->getImportedIds($this->module))) {
            $engine->addStep(new CleanupRecordsStep($this->module));
        }

        if (!$this->tree) {
            $this->tree = $engine->getRegistry()->getImportedTree($this->module, $this->id);
        }

        if (!$this->tree) {
            throw new ImportException(sprintf('Cannot import module (%s #%s) without an imported tree', $this->module, $this->id));
        }

        $config = $engine->getConfig()->getModuleConfig($this->module);
        $this->target = $this->createOrUpdate($config, $this->tree, $isSkipped);

        if (!$isSkipped) {
            if (isset($config['attachment'])) {
                $engine->addStep(new ImportAttachmentStep($this->module, $this->id));
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        if ($engine->getRegistry()->hasImportedModule($this->module, $this->id)) {
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
                        $engine->addStep(new ImportModuleStep($relationCfg['type'], $child->getId(), $child));
                        $data = [];
                        if (isset($relationCfg['fields'])) {
                            foreach ($relationCfg['fields'] as $field => $path) {
                                $data[$field] = $this->tree->getNestedValue($path);
                            }
                        }
                        $ids[$child->getId()] = $data;
                    }
                }
            }

            if (!empty($ids)) {
                $engine->addStep(new LinkRelationStep($this->target->getClassName(), $this->target->MplusID, $relationName, $ids));
            }
        }

        $engine->getRegistry()->reportImportedModule($this->module, $this->id);
        $engine->getRegistry()->clearImportedTree($this->module, $this->id);
    }

    protected function createOrUpdate(array $config, TreeNode $tree, &$skipped = false): DataObject
    {
        $modelClass = $config['modelClass'] ?? null;
        $id = $this->getId();

        if (!$modelClass || !$id) {
            throw new ImportException('Cannot import Module without modelClass or ID');
        }

        $existing = DataObject::get_one($modelClass, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($modelClass);

        if (!$target->hasExtension(DataRecordExtension::class)) {
            throw new ImportException(sprintf('Dataobject import target (%s) needs to have the DataRecordExtension', $modelClass));
        }

        // Skip over existing records that were not modified remotely
        if ($target->isInDB()) {
            $lastModified = 0;
            if ($lastModifiedField = $tree->getNestedNode('__lastModified')) {
                $lastModified = strtotime($lastModifiedField->getValue());
            }

            if ($lastModified > 0 && $lastModified <= strtotime($target->Imported)) {
                // Get result from skip call and filter out any `null` value returns
                $skipCallbackResult = array_filter(
                    $target->extend('beforeMplusSkip', $this),
                    function ($v) {
                        return !is_null($v);
                    }
                );
                // If any callback returned false, we won't skip
                if (empty($skipCallbackResult) || min($skipCallbackResult) !== false) {
                    $skipped = true;
                    return $target;
                }
            }
        }

        $target->extend('beforeMplusImport', $this);

        foreach ($config['fields'] as $fieldName => $mplusName) {
            // Skip ID
            if ($fieldName === 'MplusID') {
                continue;
            }

            if ($target->hasDatabaseField($fieldName) && ($fieldResult = $tree->getNestedNode($mplusName))) {
                $target->setField($fieldName, $fieldResult->getValue());
            }
        }

        $target->MplusID = $id;
        $target->Module = $this->module;
        $target->setField('Imported', DBDatetime::now());

        $target->write();
        $target->extend('afterMplusImport', $this);
        return $target;
    }


    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->tree = $this->tree;
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
        $this->module = $obj->module;
        $this->id = $obj->id;
        if (isset($obj->targetClass)) {
            $this->target = DataObject::get_by_id($obj->targetClass, $obj->targetId);
        }
    }
}
