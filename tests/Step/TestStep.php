<?php

namespace Mutoco\Mplus\Tests\Step;

use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Serialize\SerializableTrait;

class TestStep implements StepInterface
{
    use SerializableTrait;

    public static array $stack = [];
    private int $loops;
    private int $step;
    private bool $enqueue;
    private string $name;

    public function __construct($loops = 1, bool $enqueue = false, $name = 'A')
    {
        $this->loops = $loops;
        $this->step = 0;
        $this->enqueue = $enqueue;
        $this->name = $name;
    }

    public function getDefaultPriority(): int
    {
        return ImportEngine::PRIORITY_LOAD;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        self::$stack[] = $this->name . ':' . __FUNCTION__;
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        self::$stack[] = $this->name . ':' . __FUNCTION__;
        $this->step++;

        if ($this->enqueue) {
            $engine->addStep(new TestStep(1, false, 'B'), ImportEngine::PRIORITY_IMPORT);
            $this->enqueue = false;
        }

        return $this->step < $this->loops;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        self::$stack[] = $this->name . ':' . __FUNCTION__;
    }

    protected function getSerializableArray(): array
    {
        return [
            'loops' => $this->loops,
            'step' => $this->step,
            'enqueue' => $this->enqueue,
            'name' => $this->name,
        ];
    }

    protected function unserializeFromArray(array $data): void
    {
        $this->loops = $data['loops'];
        $this->step = $data['step'];
        $this->enqueue = $data['enqueue'];
        $this->name = $data['name'];
    }
}
