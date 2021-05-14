<?php


namespace Mutoco\Mplus\Extension;


use SilverStripe\ORM\DataExtension;

class DataRecordExtension extends DataExtension
{
    private static $db = [
        'MplusID' => 'Int',
        'Module' => 'Varchar(127)'
    ];

    private static $indexes = [
        'MplusID' => [
            'type' => 'unique',
            'columns' => ['MplusID']
        ]
    ];
}
