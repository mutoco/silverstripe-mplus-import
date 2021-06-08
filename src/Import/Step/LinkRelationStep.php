<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;

class LinkRelationStep extends AbstractRelationStep
{
    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_LINK;
    }

    public function run(ImportEngine $engine): bool
    {
        if ($engine->getRegistry()->hasImportedRelation($this->targetClass, $this->relationName)) {
            return false;
        }

        $result = parent::run($engine);

        $engine->getRegistry()->reportImportedRelation($this->targetClass, $this->relationName, $this->relationIds);

        return $result;
    }

    protected function handleHasOne(DataObject $target, string $field, ImportEngine $engine): void
    {
        $class = $target->getRelationClass($this->relationName);
        $item = DataObject::get($class)->find('MplusID', $this->relationIds[0]);
        $target->setField($field, $item->ID);
    }

    protected function handleMany(DataList $relation, ImportEngine $engine): void
    {
        $list = DataObject::get($relation->dataClass())->filter(['MplusID' => $this->relationIds]);
        $relation->addMany($list->getIDList());

        $engine->addStep(new CleanupRelationStep($this->targetClass, $this->targetId, $this->relationName, $list->column('MplusID')));
    }
}
