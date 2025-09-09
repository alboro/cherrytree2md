<?php

namespace Ctb2Md\Entity;

/**
 * Сущность Image
 * Соответствует таблице "image" в базе данных.
 */
class Image
{
    private int $nodeId;

    public function __construct(
        private Node $node,  // Many-to-one связь с Node
        private int $offset,
        private string $justification,
        private string $anchor,
        private $png,  // Убираем типизацию для blob поля
        private string $filename,
        private string $link,
        private int $time
    ) {
        $this->nodeId = $node->nodeId();
    }

    // Методы доступа к свойствам

    public function node(): Node
    {
        return $this->node;
    }

    public function nodeId(): int
    {
        return $this->nodeId;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function justification(): string
    {
        return $this->justification;
    }

    public function anchor(): string
    {
        return $this->anchor;
    }

    public function png(): string
    {
        // Если png - это ресурс (blob), читаем его содержимое
        if (is_resource($this->png)) {
            return stream_get_contents($this->png);
        }

        // Если уже строка, возвращаем как есть
        return $this->png;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function link(): string
    {
        return $this->link;
    }

    public function time(): int
    {
        return $this->time;
    }
}
