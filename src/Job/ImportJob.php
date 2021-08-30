<?php

/**
 * Task that runs the import
 */

namespace Mutoco\Mplus\Job;

use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Exception\ImportException;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\SqliteImportBackend;
use Mutoco\Mplus\Import\Step\LoadSearchStep;
use Mutoco\Mplus\Util;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class ImportJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    private static $use_search = true;
    private static $delete_obsolete = true;

    protected ?ImportEngine $importer = null;
    protected ?string $module = null;
    protected ?string $id = null;

    public function __construct(?string $module = null, ?string $id = null)
    {
        parent::__construct(array_filter([$module, $id]));

        if ($module && is_string($module)) {
            $this->hydrate($module, $id);
        }
    }

    public function getTitle()
    {
        return _t(__CLASS__ . '.Title', 'MuseumPlus import');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $value): self
    {
        $this->id = $value;
        return $this;
    }

    public function hydrate(string $module, ?string $id = null)
    {
        $this->module = $module;
        $this->totalSteps = 1;
        $this->id = $id;
    }

    public function getJobType()
    {
        return QueuedJob::LARGE;
    }

    public function setup()
    {
        parent::setup();

        $client = Injector::inst()->create('Mutoco\Mplus\Api\Client');
        $client->init();
        $this->importer = new ImportEngine();
        $this->importer->setApi($client);
        $this->importer->setDeleteObsoleteRecords(
            $this->id === null ? self::config()->get('delete_obsolete') : false
        );
        $this->importer->setUseSearchToResolve(self::config()->get('use_search'));

        if (class_exists('SQLite3')) {
            $this->importer->setBackend(new SqliteImportBackend());
        }

        $search = new SearchBuilder($this->module);
        $paths = Util::pathsToTree($this->importer->getConfig()->getImportPaths($this->module));
        $search->setSelect(Util::getSearchPaths($paths));

        if (!$this->id) {
            $mapping = self::config()->get('imports');
            if (!isset($mapping[$this->module])) {
                throw new ImportException(sprintf('No import definition for module "%s"', $this->module));
            }

            $cfg = $mapping[$this->module];
            $search->setExpert($cfg['search']);
        } else {
            $search->setExpert([
                [
                    'type' => 'equalsField',
                    'fieldPath' => '__id',
                    'operand' => $this->id
                ]
            ]);
            //$this->importer->addStep(new LoadModuleStep($this->module, $this->id));
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
            $this->importer->getBackend()->clear();
        }
    }

    public function getJobData()
    {
        $data = parent::getJobData();

        $jobData = $this->jobData ?? new \stdClass();
        $jobData->importer = $this->importer;
        $jobData->module = $this->module;
        $jobData->id = $this->id;
        $data->jobData = $jobData;

        return $data;
    }

    public function setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages)
    {
        parent::setJobData($totalSteps, $currentStep, $isComplete, $jobData, $messages);
        if ($jobData) {
            $this->importer = $jobData->importer ?? null;
            $this->module = $jobData->module ?? null;
            $this->id = $jobData->id ?? null;
        }
    }

    public function addMessage($message, $severity = 'INFO')
    {
        if ($severity !== 'INFO' && !empty(trim($message))) {
            parent::addMessage($message, $severity);
        }
    }
}
