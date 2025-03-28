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
    protected int $priority = ImportEngine::PRIORITY_FETCH_MORE;

    public function __construct(SearchBuilder $search, int $page = 0)
    {
        $this->page = $page;
        $this->search = $search;
    }

    public function getSearch(): SearchBuilder
    {
        return $this->search;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultPriority(): int
    {
        return $this->priority;
    }

    public function setDefaultPriority(int $value): self
    {
        $this->priority = $value;
        return $this;
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
                    // Important to clear the parent
                    $child->setParent(null);
                    // Must hand over the resulting tree to a LoadModuleStep in order to resolve the full tree
                    $engine->addStep(new LoadModuleStep($module, $child->id, $child));
                }

                $total = (int)$tree->totalSize;
                $this->page++;
                if ($this->page * $pageSize < $total) {
                    // Enqueue an additional search step (with same priority).
                    // It will run after the first batch has completed
                    $step = new LoadSearchStep($this->search, $this->page);
                    $step->setDefaultPriority($this->priority);
                    $engine->addStep($step);
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

    protected function getSerializableArray(): array
    {
        return [
            'search' => $this->search,
            'page' => $this->page,
            'priority' => $this->priority,
        ];
    }

    protected function unserializeFromArray(array $data): void
    {
        $this->page = $data['page'];
        $this->search = $data['search'];
        $this->priority = $data['priority'];
    }
}
