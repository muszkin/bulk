<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 03.01.17
 * Time: 11:44
 */

namespace AppBundle\Services;


use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class CacheManager
{

    private $cache;

    public function __construct(FilesystemAdapter $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param $key
     * @return bool
     */
    public function getCache($key){
        $return = $this->cache->getItem($key);
        if (!$return->isHit()){
            return false;
        }
        return $return;
    }

    public function saveCache($key,$value){
        $return = $this->cache->getItem($key);
        $return->set($value);
        $this->cache->save($return);
    }


}