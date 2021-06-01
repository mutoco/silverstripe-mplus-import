<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;

class LoadModuleStep implements StepInterface
{
    protected string $module;
    protected string $id;
    protected int $runs;

    public function __construct(string $module, string $id)
    {
        $this->module = $module;
        $this->id = $id;
        $this->runs = 0;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        $this->runs = 0;
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        $this->runs++;
        $stream = $engine->getApi()->queryModelItem($this->module, $this->id);
        if (!$stream && $this->runs < 10) {
            $engine->getApi()->init();
            sleep(10);
            return true;
        }

        if ($stream) {

        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        // TODO: Implement deactivate() method.
    }
}
