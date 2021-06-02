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

    public function __construct()
    {
        $this->steps = -1;

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

    public function getSteps(): int
    {
        return $this->steps;
    }

    public function getModuleConfig(): array
    {
        return $this->config()->get('modules');
    }

    public function enqueue(StepInterface $step, string $queue = self::QUEUE_LOAD)
    {
        if (!isset($this->queues[$queue])) {
            throw new \InvalidArgumentException('Not a valid queue name');
        }

        $this->queues[$queue]->enqueue($step);
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
            $size = $currentQueue->count();
            $isComplete = !$currentQueue->top()->run($this);
            if ($currentQueue->count() !== $size) {
                throw new \LogicException('A queue cannot change size during one run step');
            }

            if ($isComplete) {
                $step = $currentQueue->dequeue();
                $step->deactivate($this);
            }

            return true;
        }

        return false;
    }

    public function moduleForRelation(string $module, string $relationName): ?string
    {
        return Util::getRelationModule($this->getModuleConfig(), $module, $relationName);
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->queues = $this->queues;
        $obj->steps = $this->steps;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->steps = $obj->steps;
        $this->queues = $obj->queues;
    }
}
