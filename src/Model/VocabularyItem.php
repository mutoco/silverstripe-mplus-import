<?php

namespace Mutoco\Mplus\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\ImportEngine;
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

    public static function findOrCreateFromNode(TreeNode $node, ImportEngine $engine): VocabularyItem
    {
        $vocabNode = self::findVocabularyItemNode($node);

        if (!$vocabNode) {
            throw new \Exception('Node does not contain a vocabularyReferenceItem');
        }

        if (
            ($target = VocabularyItem::get()->find('MplusID', $vocabNode->getId())) &&
            $target instanceof VocabularyItem
        ) {
            return $target;
        }

        $target = VocabularyItem::create();
        $target->update([
            'MplusID' => $vocabNode->getId(),
            'Imported' => DBDatetime::now(),
            'Module' => 'VocabularyItem'
        ]);

        self::updateFromNode($target, $vocabNode, $engine);

        $target->write();
        return $target;
    }

    public static function findVocabularyItemNode(?TreeNode $node): ?TreeNode
    {
        if ($node && $node->getTag() === 'vocabularyReferenceItem') {
            return $node;
        }

        if ($node && $node->getTag() === 'vocabularyReference') {
            foreach ($node->getChildren() as $child) {
                if ($child instanceof TreeNode && $child->getTag() === 'vocabularyReferenceItem') {
                    return $child;
                }
            }
        }

        return null;
    }

    protected static function updateFromNode(VocabularyItem $item, TreeNode $node, ImportEngine $engine): void
    {
        $item->setField('Name', $node->name);
        $item->setField('Language', $node->language);
        $item->setField('Value', $node->getValue());

        if (
            ($parent = $node->getParent()) &&
            ($parent instanceof TreeNode) &&
            ($parent->getTag() === 'vocabularyReference')
        ) {
            $item->setField('VocabularyGroup', VocabularyGroup::findOrCreateFromNode($parent));
        }

        $item->invokeWithExtensions('onUpdateFromNode', $node, $engine);
    }

    public function beforeMplusImport(ImportModuleStep $step, ImportEngine $engine)
    {
        if ($node = self::findVocabularyItemNode($step->getTree())) {
            self::updateFromNode($this, $node, $engine);
        }
    }
}
