<?php

declare(strict_types=1);

/*
 * This file is part of the Rocket project.
 * (c) dylan bouraoui <contact@myrocket.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\ProjectsToDisplay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectsToDisplay>
 */
class ProjectsToDisplayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectsToDisplay::class);
    }

    public function findOneByUuidOrSlug(string $key): ?ProjectsToDisplay
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.uuid = :key')
            ->orWhere('p.slug = :key')
            ->setParameter('key', $key)
            ->orderBy('p.updatedAt', 'ASC')
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    //    /**
    //     * @return ProjectsToDisplay[] Returns an array of ProjectsToDisplay objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ProjectsToDisplay
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
