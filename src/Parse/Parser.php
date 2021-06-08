<?php


namespace Mutoco\Mplus\Parse;


use GuzzleHttp\Psr7\Utils;
use Mutoco\Mplus\Parse\Node\ParserInterface;
use Mutoco\Mplus\Parse\Node\TreeParser;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Psr\Http\Message\StreamInterface;
use Tree\Node\Node;

class Parser
{
    protected \SplStack $parsers;
    protected int $depth;
    protected array $allowedPaths = [];
    protected ?Node $pathTree = null;
    protected ?TreeNode $current = null;

    /**
     * @return string[]
     */
    public function getAllowedPaths(): array
    {
        return $this->allowedPaths;
    }

    /**
     * @param string[] $allowedPaths
     * @return Parser
     */
    public function setAllowedPaths(array $allowedPaths): self
    {
        $this->allowedPaths = $allowedPaths;

        if (!empty($allowedPaths)) {
            $this->pathTree = new Node();
            foreach ($this->allowedPaths as $path) {
                $parts = explode('.', $path);
                $node = $this->pathTree;
                foreach ($parts as $part) {
                    $found = false;
                    foreach ($node->getChildren() as $child) {
                        if ($child->getValue() === $part) {
                            $found = true;
                            $node = $child;
                            break;
                        }
                    }
                    if (!$found) {
                        $node->addChild($node = new Node($part));
                    }
                }
            }
        } else {
            $this->pathTree = null;
        }

        return $this;
    }

    public function getPathTree(): ?Node
    {
        return $this->pathTree;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getCurrent(): TreeNode
    {
        return $this->current;
    }

    public function isAllowedPath($value): bool
    {
        if ($this->pathTree === null) {
            return true;
        }

        if (is_string($value)) {
            $value = explode('.', $value);
        }

        if (empty($value)) {
            return false;
        }

        $node = $this->pathTree;
        for ($i = 0; $i < count($value); $i++) {
            $segment = $value[$i];
            $found = false;
            foreach ($node->getChildren() as $child) {
                if ($child->getValue() === $segment) {
                    $node = $child;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    public function isAllowedNext(string $name): bool
    {
        $segments = isset($this->current) ? $this->current->getPathSegments() : [];
        array_push($segments, $name);
        return $this->isAllowedPath($segments);
    }

    public function parse(StreamInterface $stream): TreeNode
    {
        $parser = $this->setupParser();
        $this->depth = 0;
        $this->current = new TreeNode();
        $this->parsers = new \SplStack();
        $this->parsers->push(new TreeParser());

        while (!$stream->eof()) {
            xml_parse($parser, $stream->read(16384));
        }

        xml_parse($parser, '', true); // finalize parsing
        xml_parser_free($parser);

        return $this->current->root();
    }

    public function parseFile(string $file): TreeNode
    {
        return $this->parse(Utils::streamFor(fopen($file, 'r')));
    }

    public function addNode(TreeNode $node): TreeNode
    {
        $this->current->addChild($node);
        $this->current = $node;
        return $node;
    }

    public function popNode(): TreeNode
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
