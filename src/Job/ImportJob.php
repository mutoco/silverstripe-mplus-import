<?php
/**
 * Task that runs the import
 */

namespace Mutoco\Mplus\Job;


use SilverStripe\Core\Config\Configurable;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class ImportJob extends AbstractQueuedJob implements QueuedJob
{
    use Configurable;

    private static $data_mapping = [];

    public function __construct($params = [])
    {
        parent::__construct($params);

        if (count($params)) {
            $this->hydrate($params[0]);
        }
    }

    public function getTitle()
    {
        return _t(__CLASS__ . '.Title', 'MuseumPlus import');
    }

    public function hydrate(string $module)
    {
        $mapping = self::config()->get('data_mapping');
        if (!isset($mapping[$module])) {
            throw new \InvalidArgumentException('No mapping for module');
        }

        $this->module = $module;
        $this->mapping = $mapping[$module];
    }

    public function setup()
    {
        parent::setup();

        
    }

    public function process()
    {
        // TODO: Implement process() method.
    }
}
