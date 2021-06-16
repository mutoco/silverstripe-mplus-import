<?php


namespace Mutoco\Mplus\Tests\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Exhibition extends DataObject implements TestOnly
{
	private static $db = [
		'Title' => 'Varchar(255)',
		'DateTo' => 'Date',
		'DateFrom' => 'Date'
	];

	private static $has_many = [
		'Texts' => TextBlock::class
	];

	private static $many_many = [
	    'Persons' => Person::class,
        'Works' => [
            'through' => ExhibitionWork::class,
            'from' => 'Exhibition',
            'to' => 'Work',
        ]
    ];

	private static $many_many_extraFields = [
	    'Persons' => ['Role' => 'Varchar(128)']
    ];

	private static $extensions = [
		DataRecordExtension::class
	];

	private static $table_name = 'Mutoco_Test_Exhibition';
}
