<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\CollectionResult;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use Mutoco\Mplus\Parse\Util;
use Mutoco\Mplus\Serialize\SerializableTrait;

/**
 * A step that performs a search against the M+ API and imports all modules that were found.
 * If there are more results, this step repeats until all have been loaded.
 * @package Mutoco\Mplus\Import\Step
 */
class LoadSearchStep implements StepInterface
{
    use SerializableTrait;

    protected SearchBuilder $search;
    protected int $page;

    public function __construct(SearchBuilder $search)
    {
        $this->page = 0;
        $this->search = $search;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_LOAD;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        $pageSize = $this->search->getLimit();
        $this->search->setStart($pageSize * $this->page);
        $stream = $engine->getApi()->search($this->search->getModule(), (string)$this->search);

        if ($stream) {
            $moduleParser = $engine->getConfig()->parserForModule($this->search->getModule());
            $rootParser = new CollectionParser('module', $moduleParser);
            $parser = new Parser();
            $result = $parser->parse($stream, $rootParser);
            if ($result instanceof CollectionResult) {
                foreach ($result->getItems() as $item) {
                    if ($item instanceof ObjectResult) {
                        $engine->enqueue(new ImportModuleStep($item));
                    }
                }

                $total = (int)$result->getAttribute('totalSize');
                $this->page++;
                if ($this->page * $pageSize < $total) {
                    return true;
                }
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
        $obj->search = $this->search;
        $obj->page = $this->page;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->page = $obj->page;
        $this->search = $obj->search;
    }
}
