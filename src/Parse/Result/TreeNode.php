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
    protected bool $resolved = false;

    public function __construct(?string $tag = null, array $attributes = [])
    {
        $this->tag = $tag;
        $this->attributes = $attributes;
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

    public function isReferenceNode(): bool
    {
        return $this->tag === 'moduleReferenceItem' && isset($this->attributes['moduleItemId']);
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    public function markResolved($value = true): self
    {
        $this->resolved = $value;
        return $this;
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

    public function getAncestorsMatchingPath($value): array
    {
        $value = $this->sanitizePathArgument($value);

        $ancestors = $this->getAncestorsAndSelf();

        $i = 0;
        $len = count($value);
        $found = [];
        foreach ($ancestors as $ancestor) {
            if ($ancestor->getName() === null) {
                $found[] = $ancestor;
                continue;
            }

            if ($i >= $len || $ancestor->getName() !== $value[$i]) {
                break;
            }

            $found[] = $ancestor;
            $i++;
        }

        return $found;
    }

    public function getSharedParent($value): TreeNode
    {
        $value = $this->sanitizePathArgument($value);
        $root = $this->root();

        if (empty($value)) {
            return $root;
        }

        $ownAncestors = $this->getAncestorsAndSelf();
        $otherAncestors = $this->getAncestorsMatchingPath($value);
        if (empty($ownAncestors) || empty($otherAncestors) || $ownAncestors[0] !== $otherAncestors[0]) {
            return $root;
        }

        $len = min(count($ownAncestors), count($otherAncestors));
        $sharedAncestor = $root;
        for ($i = 0; $i < $len; $i++) {
            if ($ownAncestors[$i] === $otherAncestors[$i]) {
                $sharedAncestor = $ownAncestors[$i];
            } else {
                break;
            }
        }

        return $sharedAncestor;
    }

    public function getNestedNode($value): ?TreeNode
    {
        $value = $this->sanitizePathArgument($value);

        $first = array_shift($value);

        $visitor = new NamedChildFinder($first);
        $node = $this->accept($visitor);

        if ($node) {
            if (empty($value)) {
                return $node;
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

    public function getNodesMatchingPath($value): array
    {
        $value = $this->sanitizePathArgument($value);

        $candidates = [];
        $name = $value[0];
        if ($this->getName() === $name) {
            $candidates[] = $this;
        }
        $unclear = [];
        foreach ($this->getChildren() as $child) {
            if ($child instanceof TreeNode && ($childName = $child->getName())) {
                if ($name === $childName) {
                    $candidates[] = $child;
                }
            } else {
                $unclear[] = $child;
            }
        }

        foreach ($unclear as $child) {
            $candidates = array_merge($candidates, $child->getNodesMatchingPath($value));
        }

        array_shift($value);
        $result = [];
        if (empty($value)) {
            $result = $candidates;
        } else {
            foreach ($candidates as $child) {
                $result = array_merge($result, $child->getNodesMatchingPath($value));
            }
        }

        return $result;
    }

    public function getNestedValue($path)
    {
        $path = $this->sanitizePathArgument($path);

        if ($node = $this->getNestedNode($path)) {
            return $node->getValue();
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
                return $child->getValue();
            }
        }

        return null;
    }

    public function __clone()
    {

    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();

        $obj->attributes = $this->attributes;
        $obj->tag = $this->tag;
        $obj->value = $this->value;
        $obj->children = $this->children;
        $obj->subTree = $this->subTree;
        $obj->resolved = $this->resolved;

        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->attributes = $obj->attributes;
        $this->tag = $obj->tag;
        $this->value = $obj->value;
        $this->children = $obj->children;
        $this->subTree = $obj->subTree;
        $this->resolved = $obj->resolved;

        foreach ($this->children as $child) {
            $child->setParent($this);
        }
    }

    protected function sanitizePathArgument($value): array
    {
        if (is_string($value)) {
            $value = explode('.', $value);
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('Value parameter needs to be string or array');
        }

        return $value;
    }
}
