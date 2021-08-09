<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Job\ImportJob;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class ImportJobTest extends FunctionalTest
{
    public function setUp()
    {
        parent::setUp();

        Config::modify()->merge('SilverStripe\Core\Injector\Injector', 'Mutoco\Mplus\Api\Client', [
           'constructor' => [
               'https://some.endpoint.example/',
               'user',
               'pass'
           ]
        ]);
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
