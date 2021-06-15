<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\ORM\DataObject;

/**
 * Clean up all records that exist in the DB, but that weren't imported
 * @package Mutoco\Mplus\Import\Step
 */
class CleanupRecordsStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;

    public function __construct(string $module)
    {
        $this->module = $module;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_CLEANUP;
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
        // Skip if engine should not delete any records
        if (!$engine->getDeleteObsoleteRecords()) {
            return false;
        }

        $ids = $engine->getRegistry()->getImportedIds($this->module);
        $cfg = $engine->getConfig()->getModuleConfig($this->module);
        $modelClass = $cfg['modelClass'] ?? null;

        if (!$modelClass || empty($ids)) {
            return false;
        }

        $obsolete = DataObject::get($modelClass)->exclude(['MplusID' => $ids]);
        /** @var DataObject $record */
        foreach ($obsolete as $record) {
            $this->deleteRecord($record);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
    }

    protected function deleteRecord(DataObject $record): bool
    {
        $rules = array_filter($record->extend('beforeMplusDelete', $this), function ($v) {
            return !is_null($v);
        });

        if (!empty($rules) && min($rules) === false) {
            return false;
        }

        $record->delete();
        return true;
    }


    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->module = $this->module;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->module = $obj->module;
    }
}
