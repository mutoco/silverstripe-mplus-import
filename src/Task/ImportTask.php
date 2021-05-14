<?php


namespace Mutoco\Mplus\Task;


use Mutoco\Mplus\Job\ImportJob;
use SilverStripe\Dev\BuildTask;

class ImportTask extends BuildTask
{
    private static $segment = 'ImportMplusTask';

    public function run($request)
    {
        $job = new ImportJob();
        $job->hydrate('Exhibition');
        singleton('Symbiote\\QueuedJobs\\Services\\QueuedJobService')->queueJob($job);
    }
}
