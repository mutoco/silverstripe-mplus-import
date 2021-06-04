<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Api\ClientInterface;
use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Parse\Util;
use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\Core\Config\Configurable;

class ImportEngine implements \Serializable
{
    use Configurable;
    use SerializableTrait;

    const QUEUE_LOAD = 'LOAD';
    const QUEUE_IMPORT = 'IMPORT';
    const QUEUE_LINK = 'LINK';
    const QUEUE_CLEANUP = 'CLEANUP';

    protected ClientInterface $api;
    protected array $queues;
    protected int $steps;
    protected ImportRegistry $registry;
    protected ?ImportConfig $config;

    public function __construct()
    {
        $this->steps = -1;
        $this->registry = new ImportRegistry();
        $this->config = null;

        $this->queues = [
            self::QUEUE_LOAD => new \SplQueue(),
            self::QUEUE_IMPORT => new \SplQueue(),
            self::QUEUE_LINK => new \SplQueue(),
            self::QUEUE_CLEANUP => new \SplQueue(),
        ];
    }

    public function getApi(): ClientInterface
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
        return $this->steps;
    }

    public function getConfig(): ImportConfig
    {
        if (!$this->config) {
            $this->config = new ImportConfig($this->config()->get('modules'));
        }

        return $this->config;
    }

    public function enqueue(StepInterface $step, ?string $queue = null)
    {
        $queueName = $queue ?? $step->getDefaultQueue();

        if (!isset($this->queues[$queueName])) {
            throw new \InvalidArgumentException('Not a valid queue name');
        }

        $this->queues[$queueName]->enqueue($step);
        $step->activate($this);
    }

    public function getQueue(string $name): ?\SplQueue
    {
        return $this->queues[$name] ?? null;
    }

    public function getCurrentQueue(): ?\SplQueue
    {
        foreach ($this->queues as $queue) {
            if (!$queue->isEmpty()) {
                return $queue;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        return $this->getCurrentQueue() === null;
    }

    public function next(): bool
    {
        $this->steps++;
        $currentQueue = $this->getCurrentQueue();

        if ($currentQueue) {
            $step = $currentQueue->bottom();
            $isComplete = !$step->run($this);

            if ($isComplete) {
                $valid = false;
                try {
                    $valid = ($step === $currentQueue->dequeue());
                    $step->deactivate($this);
                } catch (\Exception $ex) {}

                if (!$valid) {
                    throw new \LogicException('A queue cannot change the current item during one run step');
                }
            }

            return true;
        }

        return false;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->queues = $this->queues;
        $obj->steps = $this->steps;
        $obj->registry = $this->registry;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->steps = $obj->steps;
        $this->queues = $obj->queues;
        $this->registry = $obj->registry;
    }
}
