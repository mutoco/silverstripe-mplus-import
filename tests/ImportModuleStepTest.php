<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\TextBlock;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Yaml\Yaml;

class ImportModuleStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class
    ];

    protected array $loadedConfig;
    protected static $fixture_file = 'ImportModuleStepTest.yml';

    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml');
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    /*
    public function testModelImport()
    {
        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->enqueue(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);
        });
    }
    */

    public function testModifiedImport()
    {
        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            DBDatetime::set_mock_now('2021-05-10 10:00:00');

            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->enqueue(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            Person::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $person = Person::get()->find('MplusID', 1982);
            $this->assertEquals('2021-05-10 10:00:00', $exhibition->Imported, 'Imported date must be updated to import time');
            $this->assertEquals('2021-05-10 10:00:00', $person->Imported, 'Imported date must be updated to import time');


            DBDatetime::set_mock_now('2021-05-11 11:00:00');
            $engine->enqueue(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals('2021-05-10 10:00:00', $exhibition->Imported, 'Imported date must still be as before');

            DBDatetime::clear_mock_now();
        });
    }

}
