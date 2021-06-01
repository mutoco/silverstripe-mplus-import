<?php


namespace Mutoco\Mplus\Parse\Node;


use GrahamCampbell\ResultType\Result;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\CollectionResult;
use Mutoco\Mplus\Parse\Result\FieldResult;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;

class ObjectParser extends AbstractParser
{
    protected ?ObjectResult $result;
    protected string $type;
    protected int $searchDepth;
    protected ?array $fieldList;

    protected array $fields = [
        'SYSTEMFIELD' => 'VALUE',
        'DATAFIELD' => 'VALUE',
        'VOCABULARYREFERENCE' => 'FORMATTEDVALUE',
        'VIRTUALFIELD' => 'VALUE'
    ];

    protected array $relationTags = [];

    protected array $relationParsers = [];

    public function __construct(string $tagName = 'moduleItem', int $searchDepth = 1)
    {
        $this->searchDepth = $searchDepth;
        $this->fieldList = null;
        parent::__construct($tagName);
    }

    public function getValue(): ObjectResult
    {
        return $this->result;
    }

    /**
     * @return array|null
     */
    public function getFieldList(): ?array
    {
        return $this->fieldList;
    }

    /**
     * @param array|null $fieldList
     * @return ObjectParser
     */
    public function setFieldList(?array $fieldList): self
    {
        $this->fieldList = $fieldList;
        return $this;
    }

    public function getRelationParser($name) : ?CollectionParser
    {
        return $this->relationParsers[$name] ?? null;
    }

    public function setRelationParser(string $name, ?CollectionParser $parser): self
    {
        if (isset($this->relationParsers[$name])) {
            $this->relationParsers[$name]->removeListener('parse:result', [$this, 'onRelationParsed']);
            unset($this->relationParsers[$name]);
        }

        if ($parser) {
            $parser->on('parse:result', [$this, 'onRelationParsed']);
            $this->relationParsers[$name] = $parser;
        }

        $this->relationTags = [];
        foreach ($this->relationParsers as $key => $parser) {
            $tag = $parser->getTag(true);
            if (!isset($this->relationTags[$tag])) {
                $this->relationTags[$tag] = true;
            }
        }

        return $this;
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        if ($this->startDepth + $this->searchDepth >= $parser->getDepth()) {
            if (isset($this->fields[$name])) {
                $fieldParser = new FieldParser($name, $this->fields[$name]);
                $fieldParser->on('parse:result', function (FieldResult $result) use ($parser) {
                    if (!$this->fieldList || in_array($result->getName(), $this->fieldList)) {
                        $this->result->addField($result);
                    }
                    $parser->popStack();
                });
                $parser->pushStack($fieldParser);
                $fieldParser->handleElementStart($parser, $name, $attributes);
                return;
            } else if (isset($this->relationTags[$name])) {
                $relationName = $attributes['NAME'] ?? null;
                if ($relationName && ($collectionParser = $this->getRelationParser($relationName))) {
                    $parser->pushStack($collectionParser);
                    $collectionParser->handleElementStart($parser, $name, $attributes);
                    return;
                }
            }
        }

        parent::handleElementStart($parser, $name, $attributes);
    }

    protected function onRelationParsed(CollectionResult $result)
    {
        $this->result->addRelation($result);
        $this->parser->popStack();
    }

    protected function onEnter(Parser $parser)
    {
        $this->result = new ObjectResult($this->tag, $this->attributes);

        parent::onEnter($parser);
    }
}
