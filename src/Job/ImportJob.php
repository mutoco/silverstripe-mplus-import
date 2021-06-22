<?php
/**
 * Task that runs the import
 */

namespace Mutoco\Mplus\Job;


use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\SqliteImportBackend;
use Mutoco\Mplus\Import\Step\LoadSearchStep;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class ImportJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    protected ?ImportEngine $importer = null;
    protected ?array $cfg = null;
    protected ?string $module = null;

    public function __construct($params = [])
    {
        parent::__construct($params);

        if (is_array($params) && count($params)) {
            $this->hydrate($params[0]);
        } else if (is_string($params)) {
            $this->hydrate($params);
        }
    }

    public function getTitle()
    {
        return _t(__CLASS__ . '.Title', 'MuseumPlus import');
    }

    public function hydrate(string $module)
    {
        $mapping = self::config()->get('imports');
        if (!isset($mapping[$module])) {
            throw new \InvalidArgumentException(sprintf('No import definition for module "%s"', $module));
        }

        $this->cfg = $mapping[$module];
        $this->module = $module;
        $this->totalSteps = 1;
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function setup()
    {
        parent::setup();

        $search = new SearchBuilder($this->module);
        $search->setExpert($this->cfg['search']);

        $client = Injector::inst()->create('Mutoco\Mplus\Api\Client');
        $client->init();
        $this->importer = new ImportEngine();
        $this->importer->setDeleteObsoleteRecords(true);
        $this->importer->setApi($client);
        if (class_exists('SQLite3')) {
            $this->importer->setRegistry(new SqliteImportBackend());
        }
        $this->importer->addStep(new LoadSearchStep($search));

        $this->totalSteps = $this->importer->getTotalSteps();
        $this->currentStep = $this->importer->getSteps();
    }

    public function prepareForRestart()
    {
        parent::prepareForRestart();
        $client = Injector::inst()->create('Mutoco\Mplus\Api\Client');
        $client->init();
        $this->importer->setApi($client);
    }

    public function process()
    {
        $this->importer->next();
        $this->totalSteps = $this->importer->getTotalSteps();
        $this->currentStep = $this->importer->getSteps();

        if ($this->importer->isComplete()) {
            $this->isComplete = true;
            $this->importer->getRegistry()->clear();
        }
    }

    public function getJobData()
    {
        $data = parent::getJobData();

        $jobData = $this->jobData ?? new \stdClass();
        $jobData->importer = $this->importer;
        $jobData->module = $this->module;
        $jobData->cfg = $this->cfg;
        $data->jobData = $jobData;

        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        parent::setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages);
        if ($jobData) {
            $this->importer = $jobData->importer ?? null;
            $this->module = $jobData->module ?? null;
            $this->cfg = $jobData->cfg ?? null;
        }
    }
}
