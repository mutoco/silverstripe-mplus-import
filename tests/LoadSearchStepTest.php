<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\LoadSearchStep;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\Person;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Yaml\Yaml;

class LoadSearchStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        Person::class
    ];

    protected array $loadedConfig;

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


    public function testModelImport()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['SearchLoader']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $search = new SearchBuilder('Exhibition', 0, 5);
            $engine->addStep(new LoadSearchStep($search));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            $this->assertEquals([
                'Lorem',
                'Ipsum',
                'Dolor',
                'Sit',
                'Amet',
                'Consectetur',
                'Adipiscing',
                'Elit',
                'Mauris',
                'Venenatis',
                'Ullamcorper',
                'Risus'
            ], Exhibition::get()->column('Title'));

            $this->assertEquals([
                '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'
            ], Exhibition::get()->column('MplusID'));

            $ex6 = Exhibition::get()->find('MplusID', 6);
            $ex7 = Exhibition::get()->find('MplusID', 7);
            $this->assertCount(1, $ex6->Persons());
            $this->assertCount(1, $ex7->Persons());
            $this->assertEquals('Edvard', $ex6->Persons()->First()->Firstname);
            $this->assertEquals('Bilbo', $ex7->Persons()->First()->Firstname);
        });
    }
}
