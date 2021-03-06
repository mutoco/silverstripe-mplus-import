<?php

namespace Mutoco\Mplus\Tests\Model;

use Mutoco\Mplus\Extension\DataRecordExtension;
use Mutoco\Mplus\Model\VocabularyItem;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class ExhibitionWork extends DataObject implements TestOnly
{
    private static $db = [
        'Sort' => 'Int'
    ];

    private static $has_one = [
        'Exhibition' => Exhibition::class,
        'Work' => Work::class,
        'Type' => VocabularyItem::class
    ];

    private static $table_name = 'Mutoco_Test_ExhibitionWork';
}
