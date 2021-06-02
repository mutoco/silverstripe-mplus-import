<?php


namespace Mutoco\Mplus\Import\Step;


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
        $config = $engine->getModuleConfig();
        $target = $this->createOrUpdate($config, $isSkipped);
        // TODO: Import relations
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
        $module = $this->result->getType();
        $cfg = Util::getModuleConfig($config, $module);
        $modelClass = $cfg['modelClass'];
        $id = $this->result->__id;

        $existing = DataObject::get_one($modelClass, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($modelClass);

        if (!$target->hasExtension(DataRecordExtension::class)) {
            throw new \LogicException(sprintf('Dataobject import target (%s) needs to have the DataRecordExtension', $modelClass));
        }

        // Skip over existing records that were not modified remotely
        if ($target->isInDB()) {
            $lastModified = 0;
            if ($lastModifiedField = $this->result->getFieldResult('__lastModified')) {
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
        $fields = Util::getNormalizedFieldConfig($config, $module);

        foreach ($fields as $fieldName => $mplusName) {
            // Skip ID
            if ($fieldName === 'MplusID') {
                continue;
            }

            if ($target->hasDatabaseField($fieldName) && ($fieldResult = $this->result->getFieldResult($mplusName))) {
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
