<?php

namespace Mutoco\Mplus\Parse\Node;

use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\EmitterInterface;

interface ParserInterface
{
    public function handleCharacterData(Parser $parser, string $data);

    public function handleElementStart(Parser $parser, string $name, array $attributes): ?ParserInterface;

    public function handleElementEnd(Parser $parser, string $name): bool;

    public function handleDefault(Parser $parser, string $data);
}
