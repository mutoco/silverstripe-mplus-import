<?php

namespace Mutoco\Mplus\Import\Step;

use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

abstract class AbstractRelationStep implements StepInterface
{
    use SerializableTrait;

    protected string $relationName;
    protected string $targetClass;
    protected string $targetId;
    protected array $relationIds;

    public function __construct(string $targetClass, string $targetId, string $relationName, array $relationIds)
    {
        $this->relationName = $relationName;
        $this->targetClass = $targetClass;
        $this->targetId = $targetId;
        $this->relationIds = $relationIds;
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
        $target = DataObject::get($this->targetClass)->find('MplusID', $this->targetId);
        if (!$target) {
            throw new ImportException('Unable to find target model for LinkRelationStep');
        }

        $type = $target->getRelationType($this->relationName);
        if (!$type) {
            throw new ImportException(sprintf('No relation named "%s" on %s', $this->relationName, $target->getClassName()));
        }

        switch ($type) {
            case 'has_one':
                $class = $target->getRelationClass($this->relationName);
                $field = $this->relationName . 'ID';
                if (!$target->hasField($field) || !$class) {
                    throw new ImportException('Invalid has-one relation "%s"', $this->relationName);
                }
                $this->handleHasOne($target, $field, $engine);
                break;
            case 'many_many':
            case 'has_many':
                $class = $target->getRelationClass($this->relationName);
                $relation = $target->{$this->relationName}();

                if (!($relation instanceof DataList) || !$class) {
                    throw new ImportException('Invalid relation "%s"', $this->relationName);
                }

                $this->handleMany($relation, $engine);
                break;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
    }

    protected function handleHasOne(DataObject $target, string $field, ImportEngine $engine): void
    {
    }

    protected function handleMany(DataList $relation, ImportEngine $engine): void
    {
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->relationName = $this->relationName;
        $obj->targetClass = $this->targetClass;
        $obj->targetId = $this->targetId;
        $obj->relationIds = $this->relationIds;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->relationName = $obj->relationName;
        $this->targetClass = $obj->targetClass;
        $this->targetId = $obj->targetId;
        $this->relationIds = $obj->relationIds;
    }
}
