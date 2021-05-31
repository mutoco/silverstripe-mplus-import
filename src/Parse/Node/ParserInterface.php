<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;

interface ParserInterface
{
    public function handleCharacterData(Parser $parser, string $data);

    public function handleElementStart(Parser $parser, string $name, array $attributes);

    public function handleElementEnd(Parser $parser, string $name);

    public function handleDefault(Parser $parser, string $data);

    public function isComplete(): bool;

    public function getValue(): ResultInterface;
}
