<?php

namespace Ctb2Md\Repository;

use Doctrine\ORM\EntityRepository;
use Ctb2Md\Entity\Image;
use Ctb2Md\Entity\Node;

/**
 * Репозиторий для работы с сущностью Image
 */
class ImageRepository extends EntityRepository
{
    /**
     * Найти изображения по узлу (Node).
     *
     * @param Node $node
     * @return Image|null
     */
    public function findByNode(Node $node): ?Image
    {
        return $this->findOneBy(['node' => $node]);
    }

    /**
     * Сохранить изображение в базу данных.
     *
     * @param Image $image
     */
    public function save(Image $image): void
    {
        $this->getEntityManager()->persist($image);
        $this->getEntityManager()->flush();
    }

    /**
     * Удалить изображение из базы данных.
     *
     * @param Image $image
     */
    public function delete(Image $image): void
    {
        $this->getEntityManager()->remove($image);
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
        // Если свойство не указано, используем "offset" по умолчанию
        $propertyName = $propertyName ?? 'offset';

        // Получаем максимальное значение указанного свойства
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder->select('MAX(i.' . $propertyName . ')')
            ->from(Image::class, 'i');

        $maxValue = $queryBuilder->getQuery()->getSingleScalarResult();

        // Возвращаем следующее значение, увеличенное на 1
        return (int)$maxValue + 1;
    }
}
