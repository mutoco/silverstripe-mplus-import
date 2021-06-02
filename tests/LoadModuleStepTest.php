<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Tests\Api\Client;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use Symfony\Component\Yaml\Yaml;

class LoadModuleStepTest extends FunctionalTest
{
    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml');
        if (isset($this->loadedConfig['ImportEngine'])) {
            Config::inst()->merge(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
        }
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testLoadStep()
    {
        $engine = new ImportEngine();
        $engine->setApi(new Client());
        $engine->enqueue(new LoadModuleStep('Exhibition', 2));
        $engine->next();
        $this->assertCount(1, $engine->getQueue(ImportEngine::QUEUE_LOAD));
        $this->assertCount(1, $engine->getQueue(ImportEngine::QUEUE_IMPORT));
        $this->assertEquals(1982, $engine->getQueue(ImportEngine::QUEUE_LOAD)->top()->getId());
        $this->assertEquals('Person', $engine->getQueue(ImportEngine::QUEUE_LOAD)->top()->getModule());
        $engine->next();
        $this->assertCount(0, $engine->getQueue(ImportEngine::QUEUE_LOAD));
        $this->assertCount(2, $engine->getQueue(ImportEngine::QUEUE_IMPORT));
    }
}
