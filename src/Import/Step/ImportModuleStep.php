<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use Mutoco\Mplus\Parse\Util;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

class ImportModuleStep implements StepInterface
{
    use SerializableTrait;

    protected ?ObjectResult $result;

    public function __construct(ObjectResult $result)
    {
        $this->result = $result;
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
        $id = $this->result->__id ?? $this->result->getId() ?? null;
        $type = $this->result->getType();

        // If the exact same module was already imported it's safe to skip
        if ($engine->getRegistry()->hasImportedModule($type, $id)) {
            return false;
        }

        $config = $engine->getConfig()->getModuleConfig($this->result->getType());
        $target = $this->createOrUpdate($config, $isSkipped);
        $engine->getRegistry()->reportImportedModule($type, $target->MplusID);

        foreach ($config['relations'] as $relationName => $relationCfg) {
            if ($collectionResult = $this->result->getCollection($relationCfg['name'])) {
                // If main import was skipped and the relation isn't an external module, then we can skip
                if ($isSkipped && $collectionResult->getTag() !== 'moduleReference') {
                    continue;
                }

                $ids = [];
                foreach ($collectionResult->getItems() as $result) {
                    if ($result instanceof ObjectResult) {
                        $engine->enqueue(new ImportModuleStep($result));
                        $ids[] = $result->getId();
                    }
                }

                if (!empty($ids)) {
                    $engine->enqueue(new LinkRelationStep($target->getClassName(), $target->MplusID, $relationName, $ids));
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
        $this->result = null;
    }

    protected function createOrUpdate(array $config, &$skipped = false): DataObject
    {
        $modelClass = $config['modelClass'] ?? null;
        $id = $this->result->__id ?? $this->result->getId() ?? null;

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
            if ($lastModifiedField = $this->result->getField('__lastModified')) {
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

            if ($target->hasDatabaseField($fieldName) && ($fieldResult = $this->result->getField($mplusName))) {
                $target->setField($fieldName, $fieldResult->getValue());
            }
        }

        $target->MplusID = $id;
        $target->Module = $this->result->getType();
        $target->setField('Imported', DBDatetime::now());

        $target->write();
        $target->extend('afterMplusImport', $this);
        return $target;
    }


    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->result = $this->result;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->result = $obj->result;
    }
}
