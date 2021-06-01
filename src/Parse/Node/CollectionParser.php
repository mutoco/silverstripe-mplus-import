<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\CollectionResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\EmitterInterface;

class CollectionParser extends AbstractParser
{
    protected ?ParserInterface $childParser = null;
    protected ?CollectionResult $result;

    /**
     * @return ParserInterface
     */
    public function getChildParser(): ParserInterface
    {
        return $this->childParser;
    }

    /**
     * @param ParserInterface|null $childParser
     * @return CollectionParser
     */
    public function setChildParser(?ParserInterface $childParser): self
    {
        if ($this->childParser && $this->childParser instanceof EmitterInterface) {
            $this->childParser->removeListener('parse:result', [$this, 'onChildParsed']);
        }

        $this->childParser = $childParser;

        if ($this->childParser && $this->childParser instanceof EmitterInterface) {
            $this->childParser->on('parse:result', [$this, 'onChildParsed']);
        }

        return $this;
    }

    public function __construct(string $tagName, ParserInterface $childParser)
    {
        parent::__construct($tagName);
        $this->setChildParser($childParser);
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        if ($this->childParser && $this->startDepth + 1 >= $parser->getDepth()) {
            $parser->pushStack($this->childParser);
            $this->childParser->handleElementStart($parser, $name, $attributes);
            return;
        }

        parent::handleElementStart($parser, $name, $attributes);
    }

    public function onChildParsed(ResultInterface $result)
    {
        $this->result->addItem($result);
        $this->parser->popStack();
    }

    public function getValue(): CollectionResult
    {
        return $this->result;
    }

    protected function onEnter(Parser $parser)
    {
        parent::onEnter($parser);
        $this->result = new CollectionResult($this->tag, $this->attributes);
    }
}
