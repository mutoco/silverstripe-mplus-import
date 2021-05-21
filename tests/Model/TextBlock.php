<?php


namespace Mutoco\Mplus\Tests\Model;


use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TextBlock extends DataObject implements TestOnly
{
	private static $db = [
		'Text' => 'Text',
		'Author' => 'Varchar(127)'
	];

	private static $has_one = [
		'Exhibition' => Exhibition::class
	];

	private static $table_name = 'Mutoco_Test_TextBlock';

	private static $extensions = [
		DataRecordExtension::class
	];
}
