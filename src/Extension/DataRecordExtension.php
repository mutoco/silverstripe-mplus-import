<?php


namespace Mutoco\Mplus\Extension;


use SilverStripe\Forms\FieldList;
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

    public function updateCMSFields(FieldList $fields)
    {
        $fields->makeFieldReadonly(['Imported', 'MplusID', 'Module']);
    }
}
