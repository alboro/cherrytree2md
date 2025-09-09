<?php

namespace Ctb2Md\Repository;

use Doctrine\ORM\EntityRepository;
use Ctb2Md\Entity\Relation;
use Ctb2Md\Entity\Node;

/**
 * Repository for working with Children entity
 */
class RelationRepository extends EntityRepository
{
    /**
     * Find Children entity by nodeId.
     *
     * @param int $nodeId
     * @return Relation|null
     */
    public function findByNodeId(int $nodeId): ?Relation
    {
        return $this->findOneBy(['nodeId' => $nodeId]); // Use scalar field nodeId instead of node object
    }

    /**
     * Find all Children entities.
     *
     * @return Relation[]
     */
    public function findAllChildren(): array
    {
        return $this->findAll();
    }

    /**
     * Find all Relations with given parent
     *
     * @param int $parentId Parent identifier
     * @return Relation[] Array of Relation objects
     */
    public function findByParent(int $parentId): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.parentId = :parentId')
            ->setParameter('parentId', $parentId);

        return $qb->getQuery()->getResult();
    }

    /**
     * Save Children entity to database.
     *
     * @param Relation $children
     */
    public function save(Relation $children): void
    {
        $this->getEntityManager()->persist($children);
        $this->getEntityManager()->flush();
    }

    /**
     * Delete Children entity from database.
     *
     * @param Relation $children
     */
    public function delete(Relation $children): void
    {
        $this->getEntityManager()->remove($children);
        $this->getEntityManager()->flush();
    }

    /**
     * Calculate next increment value for specified property.
     *
     * @param string|null $propertyName
     * @return int
     */
    public function calculateNextIncrementValue($propertyName = null): int
    {
        // If property is not specified, use "sequence" by default
        $propertyName = $propertyName ?? 'sequence';

        // Get maximum value of specified property
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('MAX(c.' . $propertyName . ')')
            ->from(Relation::class, 'c');

        $maxValue = $queryBuilder->getQuery()->getSingleScalarResult();

        // Return next value, incremented by 1
        return (int)$maxValue + 1;
    }

    /*private todo */ function findChildrenWithNodes()
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c', 'n')
            ->join('c.node', 'n')
            ->orderBy('n.level', 'ASC')
            ->addOrderBy('c.sequence', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function buildTree()
    {
        $children = $this->findChildrenWithNodes();
        $childrenIndex = [];
        foreach ($children as $child) {
            /* @var $child \Ctb2Md\Entity\Relation */
            $childrenIndex[$child->node()->nodeId()] = $child;
            // Check parentId before accessing parent() to avoid loading Node with nodeId=0
            if ($child->parentId() && $child->parentId() !== 0 && $child->parent()) {
                $child->parent()->relation()->addChild($child);
            }
        }
        // Filter only root elements (those without parent)
        $childrenIndex = array_filter($childrenIndex, function (Relation $v) {
            return $v->parentId() === null || $v->parentId() === 0;
        });
        // @todo: try to return just $children

        return array_values($childrenIndex);
    }

    /**
     * @TODO: turn into DAO
     */
    public function calculateLevelByParentId(int $parentId): int
    {
        if ($parentId === 0) {
            return 0;
        }
        $relation = $this->findByNodeId($parentId);
        if (!$relation || !$relation->parent()) {
            return 0;
        }
        return 1 + $this->calculateLevelByParentId($relation->parent()->nodeId());
    }
}
