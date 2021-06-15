<?php


namespace Mutoco\Mplus\Task;


use Mutoco\Mplus\Job\ImportJob;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class ImportTask extends BuildTask
{
    private static $segment = 'ImportMplusTask';

    public function run($request)
    {
        $job = new ImportJob();
        $job->hydrate('Exhibition');
        QueuedJobService::singleton()->queueJob($job);
    }
}
