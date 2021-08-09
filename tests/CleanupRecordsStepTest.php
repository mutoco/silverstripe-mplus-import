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
use Symfony\Component\Yaml\Yaml;

class CleanupRecordsStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class
    ];

    protected array $loadedConfig;
    protected static $fixture_file = 'CleanupRecordsStepTest.yml';

    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml'
        );
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }


    public function testCleanup()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $existing = $this->objFromFixture(Exhibition::class, 'exhibition3');
            $existing->write();
            $this->assertNotNull(Exhibition::get()->find('MplusID', 3));
            $this->assertNotNull(Person::get()->find('MplusID', 10));
            $this->assertNotNull(TextBlock::get()->find('MplusID', 4));

            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            $engine = new ImportEngine();
            $engine->setDeleteObsoleteRecords(true);
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            $this->assertNull(Exhibition::get()->find('MplusID', 3), 'Record that wasn\'t imported got deleted');
            $this->assertNull(Person::get()->find('MplusID', 10), 'Record that wasn\'t imported got deleted');
            $this->assertNull(TextBlock::get()->find('MplusID', 4), 'Record that wasn\'t imported got deleted');
            $this->assertCount(1, Exhibition::get());
            $this->assertCount(1, Person::get());
            $this->assertCount(5, TextBlock::get());
        });
    }
}
