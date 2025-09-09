<?php

namespace Ctb2Md\Repository;

use Doctrine\ORM\EntityRepository;
use Ctb2Md\Entity\Bookmark;
use Ctb2Md\Entity\Node;

/**
 * Репозиторий для работы с сущностью Bookmark
 */
class BookmarkRepository extends EntityRepository
{
    /**
     * Найти Bookmark по узлу (Node).
     *
     * @param Node $node
     * @return Bookmark|null
     */
    public function findByNode(Node $node): ?Bookmark
    {
        return $this->findOneBy(['node' => $node]);
    }

    /**
     * Сохранить Bookmark в базу данных.
     *
     * @param Bookmark $bookmark
     */
    public function save(Bookmark $bookmark): void
    {
        $this->getEntityManager()->persist($bookmark);
        $this->getEntityManager()->flush();
    }

    /**
     * Удалить Bookmark из базы данных.
     *
     * @param Bookmark $bookmark
     */
    public function delete(Bookmark $bookmark): void
    {
        $this->getEntityManager()->remove($bookmark);
        $this->getEntityManager()->flush();
    }
}
