<?php


namespace Mutoco\Mplus\Tests\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Work extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Image' => Image::class
    ];

    private static $belongs_many_many = [
        'Exhibitions' => Exhibition::class,
    ];

    private static $table_name = 'Mutoco_Test_Work';

    private static $extensions = [
        DataRecordExtension::class
    ];
}
