<?php
/**
 * Created by PhpStorm.
 * User: eRIZ
 * Date: 2015-03-27
 * Time: 15:47
 */

namespace BillingBundle\Entity;

use DreamCommerce\ShopAppstoreBundle\Model\Billing as BillingBase;

class Billing extends BillingBase
{
    protected $id;

    public function getId(){
        return $this->id;
    }


}