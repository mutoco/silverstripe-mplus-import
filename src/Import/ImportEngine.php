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
    protected ?StepInterface $lastStep = null;
    protected int $steps;
    protected BackendInterface $registry;
    protected ?ImportConfig $config;
    protected bool $deleteObsoleteRecords = false;

    public function __construct()
    {
        $this->steps = 0;
        $this->registry = new MemoryImportBackend();
        $this->config = null;
        $this->lastStep = null;
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

    public function getRegistry(): BackendInterface
    {
        return $this->registry;
    }

    public function setRegistry(BackendInterface $value): self
    {
        $this->registry = $value;
        return $this;
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

    public function getLastStep(): ?StepInterface
    {
        return $this->lastStep;
    }

    public function addStep(StepInterface $step, ?int $priority = null)
    {
        $this->registry->addStep($step, $priority ?? $step->getDefaultPriority());
    }

    public function getTotalSteps(): int
    {
        $remaining = $this->registry->getRemainingSteps();
        return $remaining + $this->getSteps();
    }

    public function isComplete(): bool
    {
        return $this->registry->getRemainingSteps() === 0;
    }

    public function next(): bool
    {
        $this->steps++;

        if ($this->registry->getRemainingSteps() > 0) {
            $step = $this->getRegistry()->getNextStep($prio);

            if ($step !== $this->lastStep) {
                $step->activate($this);
            }
            $this->lastStep = $step;
            $isComplete = !$step->run($this);

            if ($isComplete) {
                $step->deactivate($this);
            } else {
                $this->addStep($step, $prio);
            }

            return true;
        }

        return false;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->steps = $this->steps;
        $obj->registry = $this->registry;
        $obj->apiClass = $this->api ? get_class($this->api) : null;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->steps = $obj->steps;
        $this->registry = $obj->registry;
        $this->config = null;
        if ($obj->apiClass) {
            $this->setApi(Injector::inst()->create($obj->apiClass));
        }
    }
}
