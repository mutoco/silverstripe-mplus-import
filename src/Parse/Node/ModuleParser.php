<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\FieldResult;
use Mutoco\Mplus\Parse\Result\ModuleResult;

class ModuleParser extends AbstractParser
{
    protected ?ModuleResult $result;
    protected string $type;
    protected int $searchDepth;
    protected ?array $fieldList;

    protected array $fields = [
        'SYSTEMFIELD' => 'VALUE',
        'DATAFIELD' => 'VALUE',
        'VOCABULARYREFERENCE' => 'FORMATTEDVALUE',
        'VIRTUALFIELD' => 'VALUE'
    ];

    protected array $relations = [
        'REPEATABLEGROUP' => 'REPEATABLEGROUPITEM',
        'MODULEREFERENCE' => 'MODULEREFERENCEITEM'
    ];

    public function __construct(string $type, int $searchDepth = 1)
    {
        $this->type = $type;
        $this->searchDepth = $searchDepth;
        $this->fieldList = null;
        parent::__construct('moduleItem');
    }

    public function getValue(): ModuleResult
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
     * @return ModuleParser
     */
    public function setFieldList(?array $fieldList): self
    {
        $this->fieldList = $fieldList;
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
            }
        }

        parent::handleElementStart($parser, $name, $attributes);
    }

    public function reset()
    {
        parent::reset();
        $this->result = null;
    }

    protected function onEnter(Parser $parser)
    {
        $this->result = new ModuleResult($this->tag, $this->attributes);
        $this->result->setType($this->type);
    }
}
