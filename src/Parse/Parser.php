<?php


namespace Mutoco\Mplus\Parse;


use Mutoco\Mplus\Parse\Node\ParserInterface;
use Mutoco\Mplus\Parse\Result\ResultInterface;

class Parser
{
    protected \SplStack $nodeStack;
    protected \SplStack $parserStack;

    public function __construct()
    {
        $this->nodeStack = new \SplStack();
        $this->parserStack = new \SplStack();
    }

    public function getDepth(): int
    {
        return $this->nodeStack->count();
    }

    public function parseFile(string $file, ?ParserInterface $rootParser = null) : ?ResultInterface
    {
        $parser = $this->setupParser();
        if ($rootParser) {
            $this->pushStack($rootParser);
        }

        $stream = fopen($file, 'r');
        while (($data = fread($stream, 16384))) {
            xml_parse($parser, $data); // parse the current chunk
        }
        xml_parse($parser, '', true); // finalize parsing
        fclose($stream);
        xml_parser_free($parser);

        $this->nodeStack = new \SplStack();
        $this->parserStack = new \SplStack();

        if ($rootParser) {
            return $rootParser->getValue();
        }

        return null;
    }

    public function pushStack(ParserInterface $parser)
    {
        $this->parserStack->push($parser);
    }

    public function popStack(): ParserInterface
    {
        return $this->parserStack->pop();
    }

    public function handleCharacterData($parser, string $data)
    {
        /** @var ParserInterface $current */
        if (!$this->parserStack->isEmpty() && ($current = $this->parserStack->top())) {
            $current->handleCharacterData($this, $data);
        }
    }

    public function handleElementStart($parser, string $name, array $attributes)
    {
        $this->nodeStack->push($name);

        /** @var ParserInterface $current */
        if (!$this->parserStack->isEmpty() && ($current = $this->parserStack->top())) {
            $current->handleElementStart($this, $name, $attributes);
        }
    }

    public function handleElementEnd($parser, string $name)
    {
        /** @var ParserInterface $current */
        if (!$this->parserStack->isEmpty() && ($current = $this->parserStack->top())) {
            $current->handleElementEnd($this, $name);
        }

        $this->nodeStack->pop();
    }

    public function handleDefault($parser, string $data)
    {
        /** @var ParserInterface $current */
        if (!$this->parserStack->isEmpty() && ($current = $this->parserStack->top())) {
            $current->handleDefault($this, $data);
        }
    }

    protected function setupParser()
    {
        $parser = xml_parser_create();
        xml_parser_set_option ( $parser , XML_OPTION_CASE_FOLDING , false );
        xml_set_object($parser, $this);
        xml_set_character_data_handler($parser, 'handleCharacterData');
        xml_set_element_handler($parser, 'handleElementStart', 'handleElementEnd');
        xml_set_default_handler($parser, 'handleDefault');

        return $parser;
    }
}
