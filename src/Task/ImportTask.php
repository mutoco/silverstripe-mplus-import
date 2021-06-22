<?php


namespace Mutoco\Mplus\Task;


use Mutoco\Mplus\Job\ImportJob;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class ImportTask extends BuildTask
{
    private static $segment = 'ImportMplusTask';

    public function getDescription()
    {
        return _t(
            __CLASS__ . '.Description',
            'Task to schedule an import from M+. Pass the optional "module" parameter to set the '
            . 'module to import. Defaults to "Exhibition". If you just want to import a single module, you can specify '
            . 'another additional param "id" to set the Mplus ID to import.'
        );
    }

    public function run($request)
    {
        $job = new ImportJob();
        $job->hydrate($request->getVar('module') ?? 'Exhibition', $request->getVar('id'));
        QueuedJobService::singleton()->queueJob($job);
        echo "Import Job Queued";
    }
}
