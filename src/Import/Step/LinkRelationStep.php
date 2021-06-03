<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Relation;

class LinkRelationStep implements StepInterface
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

    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_LINK;
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
                $this->assignOne($target);
                break;
            case 'many_many':
            case 'has_many':
                $this->assignMany($target);
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

    protected function assignOne(DataObject $target): void
    {
        $class = $target->getRelationClass($this->relationName);
        $field = $this->relationName . 'ID';
        if (!$target->hasField($field) || !$class) {
            throw new ImportException('Invalid has-one relation "%s"', $this->relationName);
        }
        $item = DataObject::get($class)->find('MplusID', $this->relationIds[0]);
        $target->setField($field, $item->ID);
    }

    protected function assignMany(DataObject $target): void
    {
        $class = $target->getRelationClass($this->relationName);
        $relation = $target->{$this->relationName}();

        if (!($relation instanceof Relation) || !$class) {
            throw new ImportException('Invalid relation "%s"', $this->relationName);
        }

        $list = DataObject::get($class)->filter(['MplusID' => $this->relationIds])->getIDList();
        $relation->setByIDList($list);
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
