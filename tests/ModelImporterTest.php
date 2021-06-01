<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\TextBlock;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class ModelImporterTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class
    ];

    protected static $fixture_file = 'ModelImporterTestFixture.yml';
    private $loadedConfig = null;

    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testModelImport()
    {

    }
}
