<?php

namespace Mutoco\Mplus\Tests\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class TaxonomyType extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $has_many = [
        'Taxonomies' => Taxonomy::class
    ];

    private static $extensions = [
        DataRecordExtension::class
    ];

    private static $table_name = 'Mutoco_Test_TaxonomyType';
}
