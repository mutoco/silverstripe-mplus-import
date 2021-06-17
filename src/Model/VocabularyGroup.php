<?php


namespace Mutoco\Mplus\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\ORM\DataObject;

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
}
