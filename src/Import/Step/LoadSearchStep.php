<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;
use Mutoco\Mplus\Util;

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

    public function getSearch(): SearchBuilder
    {
        return $this->search;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultQueue(): string
    {
        return ImportEngine::QUEUE_IMPORT;
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
            $module = $this->search->getModule();
            $parser = new Parser();
            $parser->setAllowedPaths(Util::pathsToTree($engine->getConfig()->getImportPaths($module), $module));
            $result = $parser->parse($stream);
            if ($result instanceof TreeNode && ($tree = $result->getNestedNode($module))) {
                foreach ($tree->getChildren() as $child) {
                    // Must hand over the resulting tree to a LoadModuleStep in order to resolve the full tree
                    $engine->addStep(new LoadModuleStep($module, $child->id, $child));
                }

                $total = (int)$tree->totalSize;
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
