<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 09.01.17
 * Time: 14:31
 */

namespace AppBundle\Services\Interfaces;


use DreamCommerce\ShopAppstoreLib\ClientInterface;

interface ImportInterface
{
    public function processData(ClientInterface $client,$lang,$shop,$file,$data,$locale);
}