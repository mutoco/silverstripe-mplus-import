<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\ORM\DataList;

class CleanupRelationStep extends AbstractRelationStep
{
    /**
     * @inheritDoc
     */
    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_CLEANUP;
    }

    protected function handleMany(DataList $relation, ImportEngine $engine): void
    {
        // Remove all items from the relation that have not been imported
        $obsolete = $relation->exclude(['MplusID' => $this->relationIds])->getIDList();
        $relation->removeMany($obsolete);
    }
}
