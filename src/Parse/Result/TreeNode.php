<?php


namespace Mutoco\Mplus\Parse\Result;


use Mutoco\Mplus\Serialize\SerializableTrait;
use Tree\Node\NodeInterface;
use Tree\Node\NodeTrait;

class TreeNode implements NodeInterface, \Serializable
{
    use NodeTrait;
    use SerializableTrait;

    protected ?string $tag;
    protected array $attributes;
    protected ?TreeNode $subTree;

    public function __construct(?string $tag = null, array $attributes = [])
    {
        $this->tag = $tag;
        $this->attributes = $attributes;
        $this->subTree = null;
    }

    public function getId(): ?string
    {
        return $this->attributes['id'] ?? $this->attributes['moduleItemId'] ?? null;
    }

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

    /**
     * @return TreeNode
     */
    public function getSubTree(): TreeNode
    {
        return $this->subTree;
    }

    /**
     * @param TreeNode $subTree
     * @return TreeNode
     */
    public function setSubTree(TreeNode $subTree): self
    {
        $this->subTree = $subTree;
        return $this;
    }

    public function isReferenceNode(): bool
    {
        return $this->tag === 'moduleReferenceItem' && isset($this->attributes['moduleItemId']);
    }

    public function getModuleName(): ?string
    {
        if ($this->isReferenceNode() && ($parent = $this->getParent()) && $parent instanceof TreeNode) {
            return $parent->targetModule;
        }

        return null;
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

        $node = null;
        $first = array_shift($value);
        if (!$this->getName()) {
            $node = $this->getChildByName($first);
        } else if ($this->getName() === $first) {
            $node = $this;
        }

        if ($node) {
            if (empty($value)) {
                if ($node->getName() === $first) {
                    return $node;
                }
            } else {
                foreach ($node->getExpandedChildren() as $child) {
                    if (($child instanceof TreeNode) && ($found = $child->getNestedNode($value))) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    public function getNestedValue($path)
    {
        if (is_string($path)) {
            $path = explode('.', $path);
        }

        if (!is_array($path)) {
            throw new \InvalidArgumentException('Path parameter needs to be string or array');
        }

        $last = array_pop($path);
        if (empty($path)) {
            return $this->__get($last);
        } else if ($node = $this->getNestedNode($path)) {
            return $node->__get($last);
        }
    }

    public function getChildByName(string $name) : ?TreeNode
    {
        foreach ($this->getExpandedChildren() as $child) {
            if ($child instanceof TreeNode && $child->getName() === $name) {
                return $child;
            }
        }
        return null;
    }

    public function getExpandedChildren(): array
    {
        if ($this->subTree) {
            return $this->subTree->getChildren();
        }

        return $this->getChildren();
    }

    public function __get(string $name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        foreach ($this->getChildren() as $child) {
            if ($child instanceof TreeNode && $child->getName() === $name) {
                return $child->getValue();
            }
        }

        return null;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();

        $obj->attributes = $this->attributes;
        $obj->tag = $this->tag;
        $obj->value = $this->value;
        $obj->children = $this->children;
        $obj->subTree = $this->subTree;

        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->attributes = $obj->attributes;
        $this->tag = $obj->tag;
        $this->value = $obj->value;
        $this->children = $obj->children;
        $this->subTree = $obj->subTree;

        foreach ($this->children as $child) {
            $child->setParent($this);
        }
    }
}
