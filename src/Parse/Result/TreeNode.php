<?php


namespace Mutoco\Mplus\Parse\Result;


use Tree\Node\Node;

class TreeNode extends Node
{
    protected ?string $tag = null;
    protected array $attributes = [];

    /**
     * @return string|null
     */
    public function getTag(): ?string
    {
        return $this->tag;
    }

    /**
     * @param string|null $tag
     * @return TreeNode
     */
    public function setTag(?string $tag): self
    {
        $this->tag = $tag;
        return $this;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     * @return TreeNode
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function isReferenceNode(): bool
    {
        return $this->tag === 'moduleReferenceItem' && isset($this->attributes['moduleItemId']);
    }

    public function getName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function getPath(): string
    {
        return implode('.', $this->getPathSegments());
    }

    public function getPathSegments(): array
    {
        $segments = [];
        foreach($this->getAncestorsAndSelf() as $item) {
            if (($item instanceof TreeNode) && ($name = $item->getName())) {
                $segments[] = $name;
            }
        }
        return $segments;
    }

    public function getNestedNode($value): ?TreeNode
    {
        if (is_string($value)) {
            $value = explode('.', $value);
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('Value parameter needs to be string or array');
        }

        $node = $this;
        $first = array_shift($value);
        if (!$this->getName()) {
            $node = $this->getChildByName($first);
        }

        if ($node) {
            if (empty($value)) {
                if ($node->getName() === $first) {
                    return $node;
                }
            } else {
                foreach ($node->getChildren() as $child) {
                    if (($child instanceof TreeNode) && ($found = $child->getNestedNode($value))) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    public function getChildByName(string $name) : ?TreeNode
    {
        foreach ($this->getChildren() as $child) {
            if ($child instanceof TreeNode && $child->getName() === $name) {
                return $child;
            }
        }
        return null;
    }

    public function __get(string $name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        foreach ($this->getChildren() as $child) {
            if ($child instanceof TreeNode && $child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }
}
