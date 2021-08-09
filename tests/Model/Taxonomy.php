<?php

namespace Mutoco\Mplus\Tests\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Import\Step\ImportModuleStep;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Taxonomy extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_one = [
        'Type' => TaxonomyType::class
    ];

    private static $extensions = [
        DataRecordExtension::class
    ];

    private static $table_name = 'Mutoco_Test_Taxonomy';
}
