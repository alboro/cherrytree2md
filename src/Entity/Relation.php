<?php

namespace Ctb2Md\Entity;

use JsonSerializable;

/**
 * Children Entity
 * Corresponds to "children" table in database.
 */
class Relation implements JsonSerializable
{
    private int $nodeId;  // Return $nodeId as primary identifier
    private ?int $parentId = null;
    private int $sequence;
    private Node $node;  // Return regular name $node
    private ?Node $parent;

    private array $relations = [];

    public function __construct(Node $node, int $parentId, int $sequence) {
        $this->node = $node;
        $this->parentId = $parentId;
        $this->sequence = $sequence;
        $this->nodeId = $node->nodeId();
    }

    // Property access methods
    public function getId(): int
    {
        return $this->nodeId;
    }

    public function nodeId(): int
    {
        return $this->nodeId;
    }

    public function parentId(): ?int
    {
        return $this->parentId;
    }

    public function node(): Node
    {
        return $this->node;
    }

    public function parent(): ?Node
    {
        return $this->parent;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function move(?Node $newParent, int $sequence): void
    {
        if (!$newParent === $this->parent && $sequence === $this->sequence) {
            throw new \RuntimeException('No changes to apply');
        }

        $this->parent = $newParent;
        null !== $sequence && $this->sequence = $sequence;
    }

    // @todo: remove
    public function jsonSerialize(): mixed
    {
        return [
            'is_rich'  => $this->node()->isRich(),
            'name'     => $this->node()->name(),
            'content'  => $this->node()->content(),
            'children' => $this->children(),
        ];
    }

    /**
     * @return iterable<int, self>
     */
    public function children(): array
    {
        return $this->relations;
    }

    // @todo: remove
    public function addChild(self $child)
    {
        $this->relations[] = $child;

        return $this;
    }
}
