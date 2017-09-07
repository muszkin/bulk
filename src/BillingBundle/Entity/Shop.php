<?php
/**
 * Created by PhpStorm.
 * User: eRIZ
 * Date: 2015-03-27
 * Time: 18:00
 */

namespace BillingBundle\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use DreamCommerce\ShopAppstoreBundle\Model\Shop as ShopBase;

class Shop extends ShopBase{

    protected $id;

    protected $settings;

    public function getId(){
        return $this->id;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    public function __toString()
    {
        return (string) $this->getId();
    }

    public function getShopUrl()
    {
        return parent::getShopUrl();
    }
}