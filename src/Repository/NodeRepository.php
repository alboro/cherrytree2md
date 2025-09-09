<?php

namespace Ctb2Md\Repository;

use Doctrine\ORM\EntityRepository;
use Ctb2Md\Entity\Node;

/**
 * Репозиторий для работы с сущностью Node
 */
class NodeRepository extends EntityRepository
{
    /**
     * Найти узел по его идентификатору.
     *
     * @param int $nodeId
     * @return Node|null
     */
    public function findById(int $nodeId): ?Node
    {
        return $this->find($nodeId);
    }

    /**
     * Найти все узлы.
     *
     * @return Node[]
     */
    public function findAllNodes(): array
    {
        return $this->findAll();
    }

    /**
     * Найти узлы по имени.
     *
     * @param string $name
     * @return Node[]
     */
    public function findByName(string $name): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult();
    }

    /**
     * Сохранить узел в базу данных.
     *
     * @param Node $node
     */
    public function save(Node $node): void
    {
        $this->getEntityManager()->persist($node);
        $this->getEntityManager()->flush();
    }

    /**
     * Удалить узел из базы данных.
     *
     * @param Node $node
     */
    public function delete(Node $node): void
    {
        $this->getEntityManager()->remove($node);
        $this->getEntityManager()->flush();
    }

    /**
     * Рассчитать следующее инкрементное значение для указанного поля.
     *
     * @param string|null $propertyName
     * @return int
     */
    public function calculateNextIncrementValue($propertyName = null): int
    {
        // Если свойство не указано, используем "nodeId" как свойство по умолчанию
        $propertyName = $propertyName ?? 'nodeId';

        // Получаем максимальное значение указанного свойства
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('MAX(n.' . $propertyName . ')')
            ->from(Node::class, 'n');

        $maxValue = $queryBuilder->getQuery()->getSingleScalarResult();

        // Возвращаем следующее значение, увеличенное на 1
        return (int)$maxValue + 1;
    }
}
