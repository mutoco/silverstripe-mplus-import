<?php


namespace Mutoco\Mplus\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\Step\ImportModuleStep;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Tests\Model\TaxonomyType;
use SilverStripe\ORM\DataObject;

/**
 * Model that represents vocabulary items in M+. A vocabulary item is similar to a taxonomy.
 * @package Mutoco\Mplus\Model
 */
class VocabularyItem extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(127)',
        'Value' => 'Varchar(255)'
    ];

    private static $has_one = [
        'VocabularyGroup' => VocabularyGroup::class
    ];

    private static $extensions = [
        DataRecordExtension::class
    ];

    private static $table_name = 'Mplus_VocabularyItem';

    private static $summary_fields = [
        'MplusID',
        'Name',
        'Value'
    ];

    public function beforeMplusImport(ImportModuleStep $step)
    {
        if ($tree = $step->getTree()) {
            $this->setField('Name', $tree->name);

            if (($parent = $tree->getParent()) && $parent instanceof TreeNode) {
                $this->setField('Type', $this->findOrCreateGroup($parent));
            }
        }
    }

    protected function findOrCreateGroup(TreeNode $node): VocabularyGroup
    {
        if (($target = VocabularyGroup::get()->find('MplusID', $node->getId())) && $target instanceof VocabularyGroup) {
            return $target;
        }

        $target = VocabularyGroup::create();
        $target->update([
            'MplusID' => $node->getId(),
            'Name' => $node->instanceName
        ]);
        $target->write();
        return $target;
    }
}
