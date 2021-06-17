<?php


namespace Mutoco\Mplus\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\Step\ImportModuleStep;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Model that represents vocabulary items in M+. A vocabulary item is similar to a taxonomy.
 * @package Mutoco\Mplus\Model
 */
class VocabularyItem extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(127)',
        'Value' => 'Varchar(255)',
        'Language' => 'Varchar(16)'
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

    public static function findOrCreateFromNode(TreeNode $node): VocabularyItem
    {
        if ($node->getTag() === 'vocabularyReference') {
            foreach ($node->getChildren() as $child) {
                if ($child->getTag() === 'vocabularyReferenceItem') {
                    $node = $child;
                    break;
                }
            }
        }

        if (($target = VocabularyItem::get()->find('MplusID', $node->getId())) && $target instanceof VocabularyItem) {
            return $target;
        }

        $target = VocabularyItem::create();
        $target->update([
            'MplusID' => $node->getId(),
            'Imported' => DBDatetime::now()
        ]);

        self::updateFromNode($target, $node);

        $target->write();
        return $target;
    }

    protected static function updateFromNode(VocabularyItem $item, TreeNode $node): void
    {
        $item->setField('Name', $node->name);
        $item->setField('Language', $node->language);
        $item->setField('Value', $node->getValue());

        if (($parent = $node->getParent()) && ($parent instanceof TreeNode) && ($parent->getTag() === 'vocabularyReference')) {
            $item->setField('VocabularyGroup', VocabularyGroup::findOrCreateFromNode($parent));
        }
    }

    public function beforeMplusImport(ImportModuleStep $step)
    {
        if ($tree = $step->getTree()) {
            self::updateFromNode($this, $tree);
        }
    }

}
