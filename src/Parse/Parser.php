<?php


namespace Mutoco\Mplus\Parse;


use Mutoco\Mplus\Parse\Node\ParserInterface;

class Parser
{
    protected \SplQueue $nodeStack;
    protected \SplQueue $parserStack;

    /*
    protected array $fields = [
        'SYSTEMFIELD' => 'VALUE',
        'DATAFIELD' => 'FORMATTEDVALUE',
        'VOCABULARYREFERENCE' => 'VALUE',
        'VIRTUALFIELD' => 'VALUE'
    ];

    protected array $relations = [
        'REPEATABLEGROUP' => 'REPEATABLEGROUPITEM',
        'MODULEREFERENCE' => 'MODULEREFERENCEITEM'
    ];
    */

    public function __construct()
    {
        $this->nodeStack = new \SplQueue();
        $this->parserStack = new \SplQueue();
    }


    public function parseFile(string $file)
    {
        $parser = $this->setupParser();

        $stream = fopen($file, 'r');
        while (($data = fread($stream, 16384))) {
            xml_parse($parser, $data); // parse the current chunk
        }
        xml_parse($parser, '', true); // finalize parsing
        fclose($stream);
        xml_parser_free($parser);

        $this->nodeStack = new \SplQueue();
        $this->parserStack = new \SplQueue();
    }

    public function pushStack(ParserInterface $parser)
    {
        $this->parserStack->enqueue($parser);
    }

    public function popStack(): ParserInterface
    {
        return $this->parserStack->dequeue();
    }

    public function handleCharacterData($parser, string $data)
    {
        /** @var ParserInterface $current */
        if ($current = $this->parserStack->top()) {
            $current->handleCharacterData($this, $data);
        }
    }

    public function handleElementStart($parser, string $name, array $attributes)
    {
        $this->nodeStack->enqueue($name);

        /** @var ParserInterface $current */
        if ($current = $this->parserStack->top()) {
            $current->handleElementStart($this, $name, $attributes);
        }
    }

    public function handleElementEnd($parser, string $name)
    {
        /** @var ParserInterface $current */
        if ($current = $this->parserStack->top()) {
            $current->handleElementEnd($this, $name);
        }
        $this->nodeStack->dequeue();
    }

    public function handleDefault($parser, string $data)
    {
        /** @var ParserInterface $current */
        if ($current = $this->parserStack->top()) {
            $current->handleDefault($this, $data);
        }
    }

    protected function setupParser()
    {
        $parser = xml_parser_create();
        xml_set_object($parser, $this);
        xml_set_character_data_handler($parser, 'handleCharacterData');
        xml_set_element_handler($parser, 'handleElementStart', 'handleElementEnd');
        xml_set_default_handler($parser, 'handleDefault');

        return $parser;
    }
}
