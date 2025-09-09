<?php

namespace Ctb2Md\Entity;

/**
 * Сущность Bookmark
 * Соответствует таблице "bookmark" в базе данных.
 */
class Bookmark
{
    private int $nodeId;
    private Node $node;
    private int $sequence;

    public function __construct(Node $node, int $sequence) {
        $this->node = $node;
        $this->sequence = $sequence;
        $this->nodeId = $node->nodeId();
    }

    // Методы доступа к свойствам
    public function nodeId(): int
    {
        return $this->nodeId;
    }

    public function node(): Node
    {
        return $this->node;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }
}
