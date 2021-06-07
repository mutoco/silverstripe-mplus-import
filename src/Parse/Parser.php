<?php


namespace Mutoco\Mplus\Parse;


use GuzzleHttp\Psr7\Utils;
use Mutoco\Mplus\Parse\Node\FieldParser;
use Mutoco\Mplus\Parse\Node\TreeParser;
use Mutoco\Mplus\Parse\Node\ParserInterface;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Psr\Http\Message\StreamInterface;

class Parser
{
    protected TreeNode $tree;
    protected TreeNode $current;
    protected \SplStack $parsers;
    protected int $depth;

    protected array $fields = [
        'systemField' => 'value',
        'dataField' => 'value',
        'virtualField' => 'value'
    ];

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getCurrent(): TreeNode
    {
        return $this->current;
    }

    public function parse(StreamInterface $stream): TreeNode
    {
        $parser = $this->setupParser();
        $this->depth = 0;
        $this->tree = $this->current = new TreeNode();
        $this->parsers = new \SplStack();
        $this->parsers->push(new TreeParser());

        while (!$stream->eof()) {
            xml_parse($parser, $stream->read(16384));
        }

        xml_parse($parser, '', true); // finalize parsing
        xml_parser_free($parser);

        return $this->tree;
    }

    public function parseFile(string $file): TreeNode
    {
        return $this->parse(Utils::streamFor(fopen($file, 'r')));
    }

    public function pushStack(): TreeNode
    {
        $node = new TreeNode();
        $this->current->addChild($node);
        $this->current = $node;
        return $node;
    }

    public function popStack(): TreeNode
    {
        $node = $this->current;
        if (($parent = $node->getParent()) && $parent instanceof TreeNode) {
            $this->current = $parent;
        }
        return $node;
    }

    public function handleCharacterData($parser, string $data)
    {
        /** @var ParserInterface $current */
        if (!$this->parsers->isEmpty() && ($current = $this->parsers->top())) {
            $current->handleCharacterData($this, $data);
        }
    }

    public function handleElementStart($parser, string $name, array $attributes)
    {
        $this->depth++;

        if (!$this->parsers->isEmpty() && ($current = $this->parsers->top())) {
            if ($new = $current->handleElementStart($this, $name, $attributes)) {
                $this->parsers->push($new);
            }
        }
    }

    public function handleElementEnd($parser, string $name)
    {
        if (!$this->parsers->isEmpty() && ($current = $this->parsers->top())) {
            if ($current->handleElementEnd($this, $name)) {
                $this->parsers->pop();
            }
        }

        $this->depth--;
    }

    public function handleDefault($parser, string $data)
    {
    }

    protected function setupParser()
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($parser, $this);
        xml_set_character_data_handler($parser, 'handleCharacterData');
        xml_set_element_handler($parser, 'handleElementStart', 'handleElementEnd');
        xml_set_default_handler($parser, 'handleDefault');

        return $parser;
    }
}
