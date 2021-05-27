<?php


namespace Mutoco\Mplus\Import;


use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Relation;

class RelationImporter extends ModelImporter
{
    protected DataObject $target;
    protected string $relationName;

    /**
     * @return DataObject
     */
    public function getTarget(): DataObject
    {
        return $this->target;
    }

    /**
     * @return string
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

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

    protected function getIdsPerModel(): array
    {
        $received = $this->getReceivedIds();

        return [
            $this->modelClass => $received,
            $this->getRelationKey() => $received
        ];
    }

    protected function getRelationKey() : string
    {
        return sprintf('%s-%d.%s', $this->target->getClassName(), $this->target->ID, $this->relationName);
    }

    protected function performCleanup($idList)
    {
        $key = $this->getRelationKey();
        if (isset($idList[$key])) {
            $received = $idList[$key];
            if (!empty($received) && ($relation = $this->getRelation())) {
                $obsolete = $relation->exclude(['MplusID' => $received]);
                foreach ($obsolete as $record) {
                    $relation->remove($record);

                    // Call an extension hook, if that returns `true`, the object is good to delete
                    $rules = array_filter($record->extend('afterMplusUnlink', $this), function ($v) {
                        return !is_null($v);
                    });

                    if (!empty($rules) && max($rules) == true) {
                        $this->deleteRecord($record);
                    }
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
        if ($this->target->hasMethod($this->relationName)) {
            $relation = $this->target->{$this->relationName}();
            if ($relation instanceof Relation) {
                return $relation;
            }
        }
        return null;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = parent::getSerializableObject();

        $obj->targetClass = $this->target->getClassName();
        $obj->targetId = $this->target->ID;
        $obj->relationName = $this->relationName;

        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        if (($target = DataObject::get_by_id($obj->targetClass, $obj->targetId)) && !is_null($target)) {
            $this->target = $target;
        }
        $this->relationName = $obj->relationName;
        parent::unserializeFromObject($obj);

    }
}
