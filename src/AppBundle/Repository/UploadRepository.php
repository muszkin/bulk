<?php

namespace AppBundle\Repository;
use Doctrine\Common\Collections\Criteria;
use DreamCommerce\ShopAppstoreBundle\Model\ShopInterface;

/**
 * UploadRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UploadRepository extends \Doctrine\ORM\EntityRepository
{

    public function findActive(ShopInterface $shop){

        $expr = Criteria::expr();
        $criteria = Criteria::create();
        $criteria->where(
            $expr->andX(
                $expr->eq('active',1),
                $expr->eq('shop',$shop)
            )
        );
        $query = $this->createQueryBuilder('s')->addCriteria($criteria);

        return $query->getQuery()->getResult();
    }

}