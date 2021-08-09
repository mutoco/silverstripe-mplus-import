<?php

namespace Mutoco\Mplus\Tests\Extension;

use Mutoco\Mplus\Import\Step\ImportModuleStep;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Tests\Model\TaxonomyType;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataExtension;

class TestTaxonomyExtension extends DataExtension implements TestOnly
{
    public function beforeMplusImport(ImportModuleStep $step)
    {
        if ($tree = $step->getTree()) {
            $this->owner->setField('Title', $tree->name);

            if ($parent = $tree->getParent()) {
                $this->owner->setField('Type', $this->findOrCreateType($parent));
            }
        }
    }

    protected function findOrCreateType(TreeNode $node): TaxonomyType
    {
        if ($target = TaxonomyType::get()->find('MplusID', $node->getId())) {
            return $target;
        }

        $target = TaxonomyType::create();
        $target->update([
            'MplusID' => $node->getId(),
            'Title' => $node->instanceName
        ]);
        $target->write();
        return $target;
    }
}
