<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use Mutoco\Mplus\Parse\Util;
use Mutoco\Mplus\Serialize\SerializableTrait;

class LoadModuleStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;
    protected int $runs;
    protected ?ObjectResult $result;

    public function __construct(string $module, string $id)
    {
        $this->module = $module;
        $this->id = $id;
        $this->runs = 0;
        $this->result = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_LOAD;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        $this->runs = 0;
        $this->result = null;
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
            $rootParser = $engine->getConfig()->parserForModule($this->module);
            $parser = new Parser();
            if (($result = $parser->parse($stream, $rootParser)) && $result instanceof ObjectResult) {
                $this->result = $result;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        if ($this->result) {
            foreach($this->result->getCollections() as $relation) {
                // Must also load module references
                if ($relation->getTag() === 'moduleReference') {
                    // Look up the module from config, otherwise directly from the parsed data
                    $module = $engine->getConfig()->getRelationModule($this->module, $relation->getName()) ?? $relation->targetModule;
                    if ($module) {
                        foreach ($relation->getItems() as $item) {
                            if ($item instanceof ObjectResult && $item->getTag() === 'moduleReferenceItem') {
                                $engine->enqueue(new LoadModuleStep($module, $item->getId()));
                            }
                        }
                    }
                }
            }

            $engine->enqueue(new ImportModuleStep($this->result), ImportEngine::QUEUE_IMPORT);
        }

        $this->result = null;
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
