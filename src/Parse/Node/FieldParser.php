<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Result\FieldResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;

class FieldParser extends AbstractParser
{
    public function getValue(): ResultInterface
    {
        return new FieldResult($this->tag, $this->attributes, trim($this->characters));
    }
}
