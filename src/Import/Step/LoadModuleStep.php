<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Util;
use Mutoco\Mplus\Serialize\SerializableTrait;

class LoadModuleStep implements StepInterface
{
    use SerializableTrait;

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
        //TODO: Cache results to reduce API calls
        $stream = $engine->getApi()->queryModelItem($this->module, $this->id);
        if (!$stream && $this->runs < 10) {
            $engine->getApi()->init();
            sleep(10);
            return true;
        }

        if ($stream) {
            $rootParser = Util::parserFromConfig(
                $engine->getModuleConfig(),
                $this->module
            );
            $parser = new Parser();
            if ($result = $parser->parse($stream, $rootParser)) {
                $engine->enqueue(new ImportModuleStep($result), ImportEngine::QUEUE_IMPORT);
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {

    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->module = $this->module;
        $obj->id = $this->id;
        $obj->runs = $this->runs;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->module = $obj->module;
        $this->id = $obj->id;
        $this->runs = $obj->runs;
    }
}
