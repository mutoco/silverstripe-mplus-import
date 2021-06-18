<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Api\ClientInterface;
use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

class ImportEngine implements \Serializable
{
    use Configurable;
    use SerializableTrait;

    const PRIORITY_IMPORT = 1000;
    const PRIORITY_LOAD = 500;
    const PRIORITY_LINK = 100;
    const PRIORITY_FETCH_MORE = 10;
    const PRIORITY_CLEANUP = 0;

    protected ?ClientInterface $api = null;
    protected \SplPriorityQueue $queue;
    protected ?StepInterface $lastStep = null;
    protected int $steps;
    protected ImportRegistry $registry;
    protected ?ImportConfig $config;
    protected bool $deleteObsoleteRecords = false;

    public function __construct()
    {
        $this->steps = 0;
        $this->registry = new ImportRegistry();
        $this->config = null;
        $this->lastStep = null;
        $this->queue = new \SplPriorityQueue();
    }

    /**
     * @return bool - whether or not the import engine should delete obsolete records at the end
     */
    public function getDeleteObsoleteRecords(): bool
    {
        return $this->deleteObsoleteRecords;
    }

    /**
     * @param bool $deleteObsoleteRecords
     * @return ImportEngine
     */
    public function setDeleteObsoleteRecords(bool $deleteObsoleteRecords): self
    {
        $this->deleteObsoleteRecords = $deleteObsoleteRecords;
        return $this;
    }

    public function getApi(): ?ClientInterface
    {
        return $this->api;
    }

    public function setApi(ClientInterface $value): self
    {
        $this->api = $value;
        return $this;
    }

    public function getRegistry(): ImportRegistry
    {
        return $this->registry;
    }

    public function getSteps(): int
    {
        return max(0, $this->steps - 1);
    }

    public function getConfig(): ImportConfig
    {
        if (!$this->config) {
            $this->config = new ImportConfig($this->config()->get('modules'));
        }

        return $this->config;
    }

    public function addStep(StepInterface $step, ?int $priority = null)
    {
        $this->queue->insert($step, $priority ?? $step->getDefaultPriority());
    }

    public function getQueue(): \SplPriorityQueue
    {
        return $this->queue;
    }

    public function getTotalSteps(): int
    {
        $remaining = $this->queue->count();
        return $remaining + $this->getSteps();
    }

    public function isComplete(): bool
    {
        return $this->queue->isEmpty();
    }

    public function next(): bool
    {
        $this->steps++;

        if (!$this->queue->isEmpty()) {
            $this->queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
            $data = $this->queue->extract();
            $step = $data['data'];
            $prio = $data['priority'];
            $this->queue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);

            if ($step !== $this->lastStep) {
                $step->activate($this);
            }
            $this->lastStep = $step;
            $isComplete = !$step->run($this);

            if ($isComplete) {
                $step->deactivate($this);
            } else {
                $this->queue->insert($step, $prio);
            }

            return true;
        }

        return false;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();

        $queue = [];
        $this->queue->rewind();
        $this->queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        while($this->queue->valid()){
            $queue[] = $this->queue->current();
            $this->queue->next();
        }

        $obj->queue = $queue;
        $obj->steps = $this->steps;
        $obj->registry = $this->registry;
        $obj->apiClass = $this->api ? get_class($this->api) : null;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->steps = $obj->steps;
        $this->queue = new \SplPriorityQueue();

        foreach ($obj->queue as $item) {
            $this->queue->insert($item['data'], $item['priority']);
        }

        $this->registry = $obj->registry;
        $this->config = null;
        if ($obj->apiClass) {
            $this->setApi(Injector::inst()->create($obj->apiClass));
        }
    }
}
