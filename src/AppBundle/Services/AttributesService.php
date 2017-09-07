<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 03.01.17
 * Time: 11:40
 */

namespace AppBundle\Services;

use AppBundle\Services\Interfaces\ImportInterface;
use Doctrine\Common\Cache\Cache;
use DreamCommerce\ShopAppstoreLib\Client\Exception\Exception;
use DreamCommerce\ShopAppstoreLib\ClientInterface;
use DreamCommerce\ShopAppstoreLib\Resource\Attribute;
use DreamCommerce\ShopAppstoreLib\Resource\AttributeGroup;
use DreamCommerce\ShopAppstoreLib\Resource\Language;
use DreamCommerce\ShopAppstoreLib\Resource\Product;
use DreamCommerce\ShopAppstoreLib\Resource\ProductStock;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;

class AttributesService implements ImportInterface
{
    private $client;

    private $cache;

    private $file;
    private $shop;
    private $lang;

    private $attributesCacheKey;
    private $attributeGroupCacheKey;
    private $productCacheKey;

    private $attributesCache;
    private $attributeGroupCache;
    private $productCache;

    private $group_name;

    /**
     * @var Logger $logger
     */
    private $logger;

    /** @var  Translator $translator */
    private $translator;

    private $locale;

    public function __construct(Cache $cache,Logger $logger,Translator $translator)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    public function processData(ClientInterface $client,$lang,$shop,$file,$data,$locale){
        $this->file = $file;
        $this->shop = $shop;
        $this->lang = $lang;
        $this->client = $client;
        $this->locale = $locale;

        try {
            $this->getLangId();
        }catch (\Exception $exception){
            throw new \Exception($this->translator->trans("exception.lang",[],"messages",$locale));
        }

        try {
            $this->generateCache();
        }catch (\Exception $exception){
            throw new \Exception($this->translator->trans("exception.cache",[],"messages",$locale));
        }
        if (!isset($data['attributes'])){
            throw new \Exception($this->translator->trans('exception.wrong_file.attributes',[],'messages',$locale));
        }
        $attributes = explode(',', $data['attributes']);

        foreach ($attributes as $k => $v){
            $attribute = explode('=',$v);
            $attributes[$k] = $attribute;
        }
        try{
            $this->getProductInfo($data['product_code']);
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
        $this->group_name = $data['attributes_group'];

        foreach ($attributes as $group){
            try {
                $group_id = $this->checkAttributeGroup($this->group_name);
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.check.attribute_group',["%name%" => $this->group_name],"messages",$locale).$exception->getMessage());
            }
            if ($group_id){
                try {
                    $this->checkAttributeGroupCategories($group_id);
                }catch (\Exception $exception){
                    throw new \Exception($this->translator->trans('exception.check.attribute_group_categories',[],"messages",$locale));
                }

                try {
                    $attribute = $this->checkAttributes($group_id, trim($group[0]), trim($group[1]));
                }catch (\Exception $exception) {
                    throw new \Exception($this->translator->trans('exception.check.attribute',['%name%'=>trim($group[0]),"%value%" => trim($group[1])],"messages",$locale));
                }

                try {
                    if (is_object($attribute)) {
                        $this->setAttribute($this->productCache['product_id'], $attribute->attribute_id, trim($group[1]));
                    } else {
                        $attribute = $this->addAttribute($group_id, $group);
                        if ($attribute) {
                            $this->setAttribute($this->productCache['product_id'], $attribute, trim($group[1]));
                        }
                    }
                }catch (\Exception $exception){
                    throw new \Exception($exception->getMessage());
                }
            }else{
                try {
                    $group_id = $this->addAttributeGroup($this->group_name);
                }catch (\Exception $exception){
                    throw new \Exception($this->translator->trans('exception.adding.attribute',[],"messages",$locale).":".$exception->getMessage());
                }

                try {
                    $this->checkAttributeGroupCategories($group_id);
                }catch (\Exception $exception){
                    throw new \Exception($this->translator->trans('exception.check.attribute_group_categories',[],"messages",$locale));
                }

                try {
                    $attribute = $this->checkAttributes($group_id, trim($group[0]), trim($group[1]));
                }catch (\Exception $exception) {
                    throw new \Exception($this->translator->trans('exception.check.attribute',['%name%'=>trim($group[0]),"%value%" => trim($group[1])],"messages",$locale));
                }

                try {
                    if (is_object($attribute)) {
                        $this->setAttribute($this->productCache['product_id'], $attribute->attribute_id, trim($group[1]));
                    } else {
                        $attribute = $this->addAttribute($group_id, $group);
                        if ($attribute) {
                            $this->setAttribute($this->productCache['product_id'], $attribute, trim($group[1]));
                        }
                    }
                }catch (\Exception $exception){
                    throw new \Exception($exception->getMessage());
                }
            }
        }
        return "success";
    }

    private function checkAttributeGroup($name){
        $attribute_group_id = null;
        $attributeGroup = $this->attributeGroupCache;
        foreach ($attributeGroup as $group){
            if ($name == $group['name']){
                $attribute_group_id = $group['attribute_group_id'];
                break;
            }
        }
        return $attribute_group_id;
    }

    private function addAttributeGroup($name){
        $data = [
            "name" => $name,
            "lang_id" => $this->lang,
            "active" => 1,
            "filters" => 0,
            "categories" => [$this->productCache['category_id']]
        ];
        try {
            $resource = new AttributeGroup($this->client);
            $status = $resource->post($data);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
        $this->generateCache(false,true);
        return $status;
    }

    private function checkAttributeGroupCategories($attribute_group_id)
    {
        $categories = $this->attributeGroupCache[$attribute_group_id]['categories'];
        $return = true;
        if (!in_array($this->productCache['category_id'],$categories)) {
            $categories[] = $this->productCache['category_id'];

            $data = [
                "categories" => $categories
            ];
            try {
                $resource = new AttributeGroup($this->client);
                $return = $resource->put($attribute_group_id,$data);
            }catch (\Exception $e){
                throw new \Exception($e->getMessage());
            }
        }
        return $return;
    }


    private function checkAttributes($group_id,$key,$value){
        $check = false;
        foreach ($this->attributesCache as $group){
            if ($group_id == $group['attribute_group_id']){
                if ($key == $group['name']){
                    $check = new \stdClass();
                    $check->attribute_id = $group['attribute_id'];
                    $values = [];
                    foreach ($group['options'] as $option) {
                        $values[] = $option['value'];
                    }
                    if (!in_array($value, $values)) {
                        $values[] = $value;
                        $this->addAttributeOption($group['attribute_id'], $values);
                    }
                }
            }
        }
        return $check;
    }

    private function addAttributeOption($id,$options){
        $data = [
            "options" => $options
        ];
        try {
            $resource = new Attribute($this->client);
            $return = $resource->put($id,$data);
            $this->generateCache(true,false);
        }catch(\Exception $e){
            throw new \Exception($e->getMessage());
        }
        return $return;
    }

    private function setAttribute($product_id,$attribute_id,$value){
        $data = [
            "category_id" => $this->productCache['category_id'],
            "attributes" => [
                $attribute_id => $value
            ]
        ];
        try {
            $resource = new Product($this->client);
            $return = $resource->put($product_id,$data);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
        return $return;
    }

    private function addAttribute($group_id,$attribute){
        $data = [
            "name" => trim($attribute[0]),
            "description" => '',
            "attribute_group_id" => $group_id,
            "type" => 2,
            "active" => 1,
            "default" => trim($attribute[1]),
            "options" => [trim($attribute[1])]
        ];
        try {
            $resource = new Attribute($this->client);
            $attribute_id = $resource->post($data);
            $this->generateCache(true,false);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
        return $attribute_id;
    }

    public function generateCache($regenerateAttributes = false,$regenerateAttributeGroups = false){
        $shop = $this->shop;
        $file = $this->file;

        $this->attributesCacheKey = $shop.$file.'attributes';
        $this->attributeGroupCacheKey = $shop.$file.'attributeGroups';

        if (!$this->cache->contains($this->attributesCacheKey) || $regenerateAttributes){
            $this->cache->save($this->attributesCacheKey,$this->getAttributes());
        }
        $this->attributesCache = $this->cache->fetch($this->attributesCacheKey);
        if (!$this->cache->contains($this->attributeGroupCacheKey) || $regenerateAttributeGroups){
            $this->cache->save($this->attributeGroupCacheKey,$this->getAttributesGroup());
        }
        $this->attributeGroupCache = $this->cache->fetch($this->attributeGroupCacheKey);
    }

    public function getLangId(){
        $filter = [
            "locale" => $this->lang,
        ];
        $resource = new Language($this->client);
        $resource->filters($filter);
        $languages = $resource->get();

        foreach ($languages as $language){
            $this->lang = $language->lang_id;
        }
    }

    private function getAttributesGroup(){
        $resource = new AttributeGroup($this->client);
        $resource->limit(50);
        $attributesGroup = $resource->get();
        $return = [];
        if ($attributesGroup->pages > 1){
            for ($x = 1;$x <= $attributesGroup->pages;$x++){
                $resource->page($x);
                foreach ($resource->get() as $part){
                    $categories = [];
                    foreach ($part->categories as $category){
                        $categories[] = $category;
                    }
                    $return[$part->attribute_group_id] = [
                        "attribute_group_id" => $part->attribute_group_id,
                        "name" => $part->name,
                        "lang_id" => $part->lang_id,
                        "active" => $part->active,
                        "filters" => $part->filters,
                        "categories" => $categories
                    ];
                }
            }
        }else{
            foreach ($attributesGroup as $part){
                $categories = [];
                foreach ($part->categories as $category){
                    $categories[] = $category;
                }
                $return[$part->attribute_group_id] = [
                    "attribute_group_id" => $part->attribute_group_id,
                    "name" => $part->name,
                    "lang_id" => $part->lang_id,
                    "active" => $part->active,
                    "filters" => $part->filters,
                    "categories" => $categories
                ];
            }
        }
        return $return;
    }

    public function getAttributes(){
        $resource = new Attribute($this->client);
        $resource->limit(50);
        $attributes = $resource->get();
        $return = [];
        if ($attributes->pages > 1){
            for ($x = 1;$x <= $attributes->pages;$x++){
                $resource->page($x);
                foreach ($resource->get() as $part){
                    $options = [];
                    foreach ($part->options as $option){
                        $options[] = $option;
                    }
                    $return[$part->attribute_id] = [
                        "attribute_id" => $part->attribute_id,
                        "name" => $part->name,
                        "description" => $part->description,
                        "attribute_group_id" => $part->attribute_group_id,
                        "order" => $part->order,
                        "type" => $part->type,
                        "active" => $part->active,
                        "default" => $part->default,
                        "options" => $options
                    ];
                }
            }
        }else{
            foreach ($attributes as $part){
                $options = [];
                foreach ($part->options as $option){
                    $options[] = $option;
                }
                $return[$part->attribute_id] = [
                    "attribute_id" => $part->attribute_id,
                    "name" => $part->name,
                    "description" => $part->description,
                    "attribute_group_id" => $part->attribute_group_id,
                    "order" => $part->order,
                    "type" => $part->type,
                    "active" => $part->active,
                    "default" => $part->default,
                    "options" => $options
                ];
            }
        }
        return $return;
    }

    private function getProductInfo($product_code){
        $resource = new Product($this->client);
        $resource->filters([
            'stock.code' => $product_code
        ]);
        try {
            $product = $resource->get();
        }catch(\Exception $exception){
            if (strpos($exception->getMessage(),"lock") !== false){
                throw new \Exception($this->translator->trans("exception.lock"));
            }else {
                throw new \Exception($exception->getMessage());
            }
        }
        if (property_exists($product,'count') && $product->count < 1){
            throw new \Exception($this->translator->trans("exception.no_product",['%product_code%'=>$product_code],"messages",$this->locale));
        }else{
            $return = [];
            foreach ($product as $part){
                $attributes = [];
                foreach ($part->attributes as $attributes_list){
                    foreach ($attributes_list as $key => $attribute) {
                        $attributes[] = [
                            "attribute_id" => $key,
                            "attribute_value" => $attribute
                        ];
                    }
                }
                $return = [
                    "product_id" => $part->product_id,
                    "category_id" => $part->category_id,
                    "attributes" => $attributes
                ];
            }
            $this->cache->save($this->productCacheKey . $product_code,$return);
            $this->productCache = $this->cache->fetch($this->productCacheKey.$product_code);
        }
    }

}