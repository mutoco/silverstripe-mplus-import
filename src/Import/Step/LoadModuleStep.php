<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ReferenceCollector;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;

class LoadModuleStep implements StepInterface
{
    use SerializableTrait;

    protected string $module;
    protected string $id;
    protected int $runs;
    protected ?TreeNode $result;
    protected ?TreeNode $origin;

    public function __construct(string $module, string $id, ?TreeNode $origin = null)
    {
        $this->module = $module;
        $this->id = $id;
        $this->runs = 0;
        $this->result = null;
        $this->origin = $origin;
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
        if ($engine->getRegistry()->hasImportedTree($this->module, $this->id)) {
            return false;
        }

        $this->runs++;
        //TODO: Cache results to reduce API calls
        $stream = $engine->getApi()->queryModelItem($this->module, $this->id);
        if (!$stream && $this->runs < 10) {
            $engine->getApi()->init();
            sleep(10);
            return true;
        }

        if ($stream) {
            $parser = new Parser();
            //TODO: Make sure import paths are set for intermediate modules without config!!
            $parser->setAllowedPaths($engine->getConfig()->getImportPaths($this->module));

            if ($result = $parser->parse($stream)) {
                $this->result = $result;
                $engine->getRegistry()->setImportedTree($this->module, $this->id, $result);
                if ($this->origin) {
                    $this->origin->setSubTree($result);
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
        if ($this->result) {
            $engine->addStep(new ImportModuleStep($this->module, $this->id), ImportEngine::QUEUE_IMPORT);

            $visitor = new ReferenceCollector();
            $references = $this->result->accept($visitor);
            /** @var TreeNode $reference */
            foreach ($references as $reference) {
                if (($moduleName = $reference->getModuleName()) && ($id = $reference->moduleItemId)) {
                    $engine->addStep(new LoadModuleStep($moduleName, $id, $reference));
                }
            }
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
