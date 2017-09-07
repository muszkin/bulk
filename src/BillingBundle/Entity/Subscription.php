<?php
/**
 * Created by PhpStorm.
 * User: eRIZ
 * Date: 2015-03-27
 * Time: 15:47
 */

namespace BillingBundle\Entity;

use DreamCommerce\ShopAppstoreBundle\Model\Subscription as SubscriptionBase;

class Subscription extends SubscriptionBase
{
    protected $id;

    public function getId(){
        return $this->id;
    }


}