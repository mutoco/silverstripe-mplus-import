<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;

class MemoryImportBackend implements BackendInterface
{
    use SerializableTrait;

    protected array $modules = [];
    protected array $relations = [];
    protected array $trees = [];
    protected \SplPriorityQueue $queue;

    public function addStep(StepInterface $step, int $priority): void
    {
        $this->queue->insert($step, $priority);
    }

    public function getNextStep(?int &$priority): ?StepInterface
    {
        if ($this->queue->isEmpty()) {
            $priority = 0;
            return null;
        }

        $flags = $this->queue->getExtractFlags();
        $this->queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $data = $this->queue->extract();
        $priority = $data['priority'];
        $this->queue->setExtractFlags($flags);
        return $data['data'];
    }

    public function getRemainingSteps(): int
    {
        return $this->queue->count();
    }

    public function __construct()
    {
        $this->queue = new \SplPriorityQueue();
    }

    public function hasImportedTree(string $module, string $id): bool
    {
        $key = $module . '.' . $id;
        return isset($this->trees[$key]);
    }

    public function getImportedTree(string $module, string $id): ?TreeNode
    {
        $key = $module . '.' . $id;
        return $this->trees[$key] ?? null;
    }

    public function setImportedTree(string $module, string $id, TreeNode $tree): void
    {
        $key = $module . '.' . $id;
        $this->trees[$key] = $tree;
    }

    public function clearImportedTree(string $module, string $id): bool
    {
        $key = $module . '.' . $id;
        if (isset($this->trees[$key])) {
            unset($this->trees[$key]);
            return true;
        }
        return false;
    }

    public function reportImportedRelation(string $class, string $id, string $name, array $ids): void
    {
        $key = join('-', [$class, $id, $name]);
        $this->relations[$key] = $ids;
    }

    public function hasImportedRelation(string $class, string $id, string $name): bool
    {
        $key = join('-', [$class, $id, $name]);
        return isset($this->relations[$key]);
    }

    public function getRelationIds(string $class, string $id, string $name): array
    {
        $key = join('-', [$class, $id, $name]);
        return $this->relations[$key] ?? [];
    }

    public function reportImportedModule(string $name, string $id): void
    {
        if ($this->hasImportedModule($name, $id)) {
            return;
        }

        if (!isset($this->modules[$name])) {
            $this->modules[$name] = [$id];
        } else {
            $this->modules[$name][] = $id;
        }
    }

    public function hasImportedModule(string $name, ?string $id = null): bool
    {
        if (!isset($this->modules[$name])) {
            return false;
        }

        if ($id !== null) {
            return in_array($id, $this->modules[$name], true);
        }

        return true;
    }

    public function getImportedIds(string $module): array
    {
        return $this->modules[$module] ?? [];
    }

    public function clear(): void
    {
        $this->modules = [];
        $this->relations = [];
        $this->trees = [];
        $this->queue = new \SplPriorityQueue();
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->modules = $this->modules;
        $obj->relations = $this->relations;
        $obj->trees = $this->trees;

        $queue = [];
        $this->queue->rewind();
        $flags = $this->queue->getExtractFlags();
        $this->queue->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        while(!$this->queue->isEmpty()){
            $queue[] = $this->queue->extract();
        }

        // Previous process was destructive, need to rebuild the queue
        foreach ($queue as $item) {
            $this->queue->insert($item['data'], $item['priority']);
        }

        $this->queue->setExtractFlags($flags);

        $obj->queue = $queue;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->modules = $obj->modules;
        $this->relations = $obj->relations;
        $this->trees = $obj->trees;
        $this->queue = new \SplPriorityQueue();

        foreach ($obj->queue as $item) {
            $this->queue->insert($item['data'], $item['priority']);
        }
    }
}
