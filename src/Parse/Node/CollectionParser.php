<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\CollectionResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\EmitterInterface;

class CollectionParser extends AbstractParser
{
    protected string $sizeAttr;
    protected int $size;
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

    public function __construct(string $tagName, ParserInterface $childParser, string $sizeAttr = 'SIZE')
    {
        parent::__construct($tagName);
        $this->setChildParser($childParser);
        $this->sizeAttr = $sizeAttr;
    }

    public function onChildParsed(ResultInterface $result)
    {
        $this->result->addItem($result);

        if ($this->result->count() >= $this->size) {
            $this->parser->popStack();
        }
    }

    public function getValue(): CollectionResult
    {
        return $this->result;
    }

    protected function onEnter(Parser $parser)
    {
        parent::onEnter($parser);

        $this->size = (int)($this->attributes[$this->sizeAttr] ?? 0);
        $this->result = new CollectionResult($this->tag, $this->attributes);
        $parser->pushStack($this->childParser);
    }
}
