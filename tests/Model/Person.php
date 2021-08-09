<?php

namespace Mutoco\Mplus\Tests\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Person extends DataObject implements TestOnly
{
    private static $db = [
        'Firstname' => 'Varchar(255)',
        'Lastname' => 'Varchar(255)',
        'DateOfBirth' => 'Date',
        'DateOfDeath' => 'Date',
        'PlaceOfBirth' => 'Varchar(255)',
        'PlaceOfDeath' => 'Varchar(255)'
    ];

    private static $table_name = 'Mutoco_Test_Person';

    private static $extensions = [
        DataRecordExtension::class
    ];
}
