<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\FieldResult;
use Mutoco\Mplus\Parse\Result\ModuleResult;

class ModuleParser extends AbstractParser
{
    protected ?ModuleResult $result;
    protected string $type;

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

    public function __construct(string $type)
    {
        $this->type = $type;
        parent::__construct('moduleItem');
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        if ($this->startDepth + 1 === $parser->getDepth()) {
            if (isset($this->fields[$name])) {
                $fieldParser = new FieldParser($name, $this->fields[$name]);
                $fieldParser->on('parse:result', function (FieldResult $result) use ($parser) {
                    $this->result->fields[] = $result;
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

    public function getValue(): ModuleResult
    {
        return $this->result;
    }

    protected function onEnter(Parser $parser)
    {
        $this->result = new ModuleResult($this->tag, $this->attributes);
        $this->result->type = $this->type;
    }
}
