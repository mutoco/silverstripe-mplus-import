<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Util;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\ORM\ManyManyThroughList;

class LinkRelationStep extends AbstractRelationStep
{
    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_LINK;
    }

    public function run(ImportEngine $engine): bool
    {
        if ($engine->getBackend()->hasImportedRelation($this->targetClass, $this->targetId, $this->relationName)) {
            return false;
        }

        $result = parent::run($engine);

        $engine->getBackend()->reportImportedRelation($this->targetClass, $this->targetId, $this->relationName, $this->relationIds);

        return $result;
    }

    protected function handleHasOne(DataObject $target, string $field, ImportEngine $engine): void
    {
        $class = $target->getRelationClass($this->relationName);
        $id = Util::isAssoc($this->relationIds) ? array_key_first($this->relationIds) : $this->relationIds[0];
        $item = DataObject::get($class)->find('MplusID', $id);
        $target->setField($field, $item->ID);
        $target->write();
    }

    protected function handleMany(DataList $relation, ImportEngine $engine): void
    {
        $added = [];
        if (!Util::isAssoc($this->relationIds)) {
            $list = DataObject::get($relation->dataClass())->filter(['MplusID' => $this->relationIds]);
            $relation->addMany($list->getIDList());
            $added = $list->column('MplusID');
        } else {
            foreach ($this->relationIds as $id => $data) {
                if ($target = DataObject::get($relation->dataClass())->find('MplusID', $id)) {
                    $added[] = $id;
                    if ($relation instanceof HasManyList) {
                        $target->update($data);
                        $relation->add($target->write());
                    } else if ($relation instanceof ManyManyList || $relation instanceof ManyManyThroughList) {
                        $relation->add($target, $data);
                    }
                }
            }
        }
        $engine->addStep(new CleanupRelationStep($this->targetClass, $this->targetId, $this->relationName, $added));
    }
}
