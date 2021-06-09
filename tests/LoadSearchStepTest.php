<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\LoadSearchStep;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Yaml\Yaml;

class LoadSearchStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class
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

            $this->assertEquals(15, $engine->getSteps(), 'Should have performed 3 load steps and 12 imports');

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
        });
    }
}
