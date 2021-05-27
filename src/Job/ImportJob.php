<?php
/**
 * Task that runs the import
 */

namespace Mutoco\Mplus\Job;


use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ModelImporter;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class ImportJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    private static $data_mapping = [];

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
    }

    public function getJobType()
    {
        return QueuedJob::LARGE;
    }

    public function setup()
    {
        parent::setup();

        $search = new SearchBuilder($this->module, $this->cfg['search']);

        $client = Injector::inst()->create('Mutoco\Mplus\Api\Client');
        $client->init();
        $xml = $client->search($this->module, $search);

        $this->importer = new ModelImporter($this->module, $this->cfg['xpath']);
        $this->importer->setApi($client);
        $this->importer->initialize($xml);
        $this->totalSteps = $this->importer->getTotalSteps();
        $this->currentStep = 0;
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
        $this->importer->importNext();
        $this->totalSteps = $this->importer->getTotalSteps();
        $this->currentStep++;

        if ($this->importer->getIsFinalized()) {
            $this->importer->cleanup($this->importer->getImportedIdsPerModel());
            $this->isComplete = true;
        }
    }
}
