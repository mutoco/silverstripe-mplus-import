<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\EmitterInterface;

interface ParserInterface extends EmitterInterface
{
    public function handleCharacterData(Parser $parser, string $data);

    public function handleElementStart(Parser $parser, string $name, array $attributes);

    public function handleElementEnd(Parser $parser, string $name);

    public function handleDefault(Parser $parser, string $data);

    public function getValue(): ?ResultInterface;

    public function isInside(): bool;
}
