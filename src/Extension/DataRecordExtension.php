<?php

namespace Mutoco\Mplus\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;

class DataRecordExtension extends DataExtension
{
    private static $db = [
        'MplusID' => 'Int',
        'Module' => 'Varchar(127)',
        'Imported' => 'Datetime'
    ];

    private static $indexes = [
        'MplusID' => [
            'type' => 'unique',
            'columns' => ['MplusID']
        ]
    ];

    private static $mplus_import_fields = [
        'Imported' => '__lastModified',
        'MplusID' => '__id'
    ];

    // In case of fluent, don't translate the module field
    private static $field_exclude = [
        'Module'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('MplusID');
        $fields->addFieldToTab(
            'Root.Main',
            TextField::create('MplusID', _t(__CLASS__ . '.MplusID', 'Museum Plus ID')),
            'Module'
        );
        $fields->dataFieldByName('Imported')->setDescription(null);
        $fields->makeFieldReadonly(['Imported', 'MplusID', 'Module']);
    }
}
