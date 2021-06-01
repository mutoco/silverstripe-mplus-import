<?php


namespace Mutoco\Mplus\Import\Step;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use Mutoco\Mplus\Serialize\SerializableTrait;

class ImportModuleStep implements StepInterface
{
    use SerializableTrait;

    protected ObjectResult $result;

    public function __construct(ObjectResult $result)
    {
        $this->result = $result;
    }

    /**
     * @inheritDoc
     */
    public function activate(ImportEngine $engine): void
    {
        // TODO: Implement activate() method.
    }

    /**
     * @inheritDoc
     */
    public function run(ImportEngine $engine): bool
    {
        // TODO: Implement run() method.
    }

    /**
     * @inheritDoc
     */
    public function deactivate(ImportEngine $engine): void
    {
        // TODO: Implement deactivate() method.
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->result = $this->result;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->result = $obj->result;
    }
}
