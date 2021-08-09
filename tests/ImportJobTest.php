<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Job\ImportJob;
use Mutoco\Mplus\Tests\Api\Client;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use Symfony\Component\Yaml\Yaml;

class ImportJobTest extends FunctionalTest
{
    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml'
        );
        if (isset($this->loadedConfig['ImportEngine'])) {
            Config::inst()->merge(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
        }

        Config::inst()->merge(ImportJob::class, 'imports', ['Exhibition' => [
            'search' => [
                'or' => [
                    [
                        'type' => 'equalsField',
                        'fieldPath' => '__id',
                        'operand' => '1'
                    ],
                    [
                        'type' => 'equalsField',
                        'fieldPath' => '__id',
                        'operand' => '2'
                    ]
                ]
            ]
        ]]);
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testSerialization()
    {
        $job = new ImportJob('Exhibition');
        $job->setup();
        $data = $job->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertEquals(1, $data->jobData->importer->getTotalSteps());

        $copy = unserialize(serialize($job));
        $data = $job->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertEquals(1, $data->jobData->importer->getTotalSteps());

        $data = $copy->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertEquals(1, $data->jobData->importer->getTotalSteps());
    }
}
