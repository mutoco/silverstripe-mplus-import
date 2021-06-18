<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Job\ImportJob;
use SilverStripe\Dev\FunctionalTest;

class ImportJobTest extends FunctionalTest
{
    public function testSerialization()
    {
        $job = new ImportJob('Exhibition');
        $job->setup();
        $data = $job->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertCount(1, $data->jobData->importer->getQueue());

        $copy = unserialize(serialize($job));
        $data = $job->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertCount(1, $data->jobData->importer->getQueue());

        $data = $copy->getJobData();
        $this->assertEquals(1, $data->totalSteps);
        $this->assertCount(1, $data->jobData->importer->getQueue());
    }
}
