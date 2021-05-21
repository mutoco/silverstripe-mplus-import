<?php


namespace Mutoco\Mplus\Import;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Relation;

class RelationImporter extends ModelImporter
{
    protected DataObject $target;
    protected string $relationName;

    public function __construct(
        string $model,
        string $xpath,
        ModelImporter $parent,
        DataObject $target,
        string $relationName,
        ?\DOMNode $context = null
    ){
        $this->target = $target;
        $this->relationName = $relationName;
        parent::__construct($model, $xpath, $parent, $context);
    }

    public function finalize()
    {
        if ($relation = $this->getRelation()) {
            $obsolete = $relation->exclude(['MplusID' => $this->getReceivedIds()]);
            foreach ($obsolete as $record) {
                $relation->remove($record);

                // Call an extension hook, if that returns `true`, the object is good to delete
                $rules = array_filter($record->extend('afterMplusUnlink', $this), function ($v) {
                    return !is_null($v);
                });

                if (max($rules) == true) {
                    $this->deleteRecord($record);
                }
            }
        }
    }

    protected function persistedRecord(DataObject $record)
    {
        parent::persistedRecord($record);

        if ($relation = $this->getRelation()) {
            $relation->add($record);
        }
    }

    protected function getRelation() : ?Relation
    {
        $relation = $this->target->{$this->relationName}();
        if ($relation instanceof Relation) {
            return $relation;
        }
    }
}
