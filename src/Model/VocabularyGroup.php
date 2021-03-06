<?php

namespace Mutoco\Mplus\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Model that represents a vocabulary group.
 * @package Mutoco\Mplus\Model
 */
class VocabularyGroup extends DataObject
{
    private static $db = [
        'Name' => 'Varchar(127)'
    ];

    private static $has_many = [
        'VocabularyItems' => VocabularyItem::class
    ];

    private static $extensions = [
        DataRecordExtension::class
    ];

    private static $table_name = 'Mplus_VocabularyGroup';

    private static $summary_fields = [
        'MplusID',
        'Name'
    ];

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName('VocabularyItems');

            // Setup voc items gridfield
            $gridConfig = GridFieldConfig_RecordViewer::create();

            $itemsGrid = GridField::create(
                'VocabularyItems',
                $this->fieldLabel('VocabularyItems'),
                $this->VocabularyItems(),
                $gridConfig
            );

            $fields->addFieldToTab('Root.Main', $itemsGrid);
        });

        $fields = parent::getCMSFields();
        $fields->changeFieldOrder(['Name', 'MplusID', 'Module', 'Imported', 'VocabularyItems']);
        return $fields;
    }

    public static function findOrCreateFromNode(TreeNode $node): VocabularyGroup
    {
        if (($target = VocabularyGroup::get()->find('MplusID', $node->getId())) && $target instanceof VocabularyGroup) {
            return $target;
        }

        $target = VocabularyGroup::create();
        $target->update([
            'MplusID' => $node->getId(),
            'Name' => $node->instanceName,
            'Module' => 'VocabularyGroup',
            'Imported' => DBDatetime::now()
        ]);
        $target->write();
        return $target;
    }
}
