<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 03.01.17
 * Time: 11:40
 */

namespace AppBundle\Services;


use AppBundle\Services\Interfaces\ImportInterface;
use function array_merge;
use Doctrine\Common\Cache\Cache;
use DreamCommerce\ShopAppstoreLib\ClientInterface;
use DreamCommerce\ShopAppstoreLib\Resource\Language;
use DreamCommerce\ShopAppstoreLib\Resource\Option;
use DreamCommerce\ShopAppstoreLib\Resource\OptionGroup;
use DreamCommerce\ShopAppstoreLib\Resource\OptionValue;
use DreamCommerce\ShopAppstoreLib\Resource\Product;
use DreamCommerce\ShopAppstoreLib\Resource\ProductStock;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;

class OptionsService implements ImportInterface
{
    private $client;

    private $cache;

    private $file;
    private $shop;
    private $lang;

    private $optionsGroupCacheKey;
    private $optionsCacheKey;
    private $optionsValuesCacheKey;

    private $productCacheKey;
    private $productCache;

    private $optionsGroupCache;
    private $optionsCache;
    private $optionsValuesCache;


    private $product_code;

    private $post;

    const keys = [
        "product_id",
        "price",
        "price_type",
        "active",
        "default",
        "stock",
        "warn_level",
        "sold",
        "code",
        "ean",
        "weight",
        "weight_type",
        "availability_id",
        "delivery_id",
        "gfx_id: null",
        "package",
        "price_wholesale",
        "price_special: ",
        "price_type_wholesale",
        "price_type_special",
        "calculation_unit_id",
    ];

    const type = [
        "select",
        "color",
        "radio",
    ];

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
            $this->generateCache();
        }catch (\Exception $exception){
            throw new \Exception($this->translator->trans("exception.cache",[],"messages",$locale));
        }
        try {
            $this->makeObject($this->createOptionsArray($data));
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());
        }
        if (empty($this->post['option_code'])){
            $this->createCode();
        }

        $this->product_code = $this->post['product_code'];
        try {
            $this->productCache = $this->generateProductCache($this->product_code);
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage());

        }

        try {
            $group_id = $this->checkOptionGroups($this->post['option_group']);
        }catch (\Exception $exception){
            throw new \Exception($this->translator->trans('exception.check.options_group',["%name%" => $this->post['option_group']],"messages",$locale).$exception->getMessage());
        }

        if ($group_id) {
            try {
                $this->checkOptions($group_id);
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.check.options',[],"messages",$locale));
            }
            foreach ($this->post['options'] as $option) {
                try {
                    $this->checkOptionValues($option['option_id'], $group_id);
                }catch (\Exception $exception){
                    throw new \Exception($this->translator->trans('exception.check.option',['%id%'=> $option['option_id']]).$exception->getMessage());
                }
            }

            try {
                $stock_id = $this->checkExistOfOption();
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.check.option_exists',[],"messages",$locale));
            }
            try {
                if (!$stock_id) {
                    $this->createStock();
                } else {
                    $this->createStockUpdate($stock_id);
                }
            }catch (\Exception $exception){
                throw new \Exception($exception->getMessage());
            }
        }else{
            try {
                $group_id = $this->addOptionGroup($this->post['option_group']);
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.adding.option',['%name%'=> $this->post['option_group']]).$exception->getMessage());
            }
            try {
                $this->checkOptions($group_id);
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.check.options',[],"messages",$locale));
            }
            foreach ($this->post['options'] as $option) {
                try {
                    $this->checkOptionValues($option['option_id'], $group_id);
                }catch (\Exception $exception){
                    throw new \Exception($this->translator->trans('exception.check.option',['%id%'=> $option['option_id']]).$exception->getMessage());
                }
            }
            try {
                $stock_id = $this->checkExistOfOption();
            }catch (\Exception $exception){
                throw new \Exception($this->translator->trans('exception.check.option_exists',[],"messages",$locale));
            }
            try {
                if (!$stock_id) {
                    $this->createStock();
                } else {
                    $this->createStockUpdate($stock_id);
                }
            }catch (\Exception $exception){
                throw new \Exception($exception->getMessage());
            }
        }
        return "success";
    }

    private function makeObject($data){
        $this->post = $data;
    }

    private function createOptionsArray($data){
        $options = [];
        for ($x = 1;$x <= 10;$x++){
            if (isset($data["option_".$x])){
                $part = explode('|',$data['option_'.$x]);
                if (!empty($part[0])) {
                    $option = [
                        "type" => trim($part[0]),
                        "option" => trim($part[1]),
                        "option_value" => trim($part[2]),
                        "color" => (trim($part[0]) == 'color') ? trim($part[3]) : null,
                    ];
                    array_push($options, $option);
                    unset($data['option_' . $x]);
                }
            }else{
                unset($data['option_' . $x]);
                break;
            }
        }
        if (empty($options)){
            throw new \Exception($this->translator->trans('exception.wrong_file.options',[],'messages',$this->locale));
        }
        $data['options'] = $options;
        return $data;
    }

    private function generateProductCache($product_code,$regenerate = false){
        if (!$this->cache->contains($this->productCacheKey.$product_code) || $regenerate){
            try {
                $this->cache->save($this->productCacheKey . $product_code, $this->getProductAllInfo($product_code));
            }catch(\Exception $e){
                throw new \Exception($e->getMessage());
            }
        }
        return $this->cache->fetch($this->productCacheKey.$product_code);
    }

    private function getProductAllInfo($product_code){
        $filters = [
            "stock.code" => $product_code
        ];
        $resource = new Product($this->client);
        $resource->filters($filters);
        $products = $resource->get();
        $return = [];
        if ($products->count > 0){
            foreach ($products->getArrayCopy() as $product) {
                $filters = [
                    "product_id" => $product->product_id,
                ];
                $resource = new ProductStock($this->client);
                $resource->filters($filters);
                $stocks = $resource->get();
                if ($stocks->count > 1) {
                    foreach ($stocks->getArrayCopy() as $key => $stock) {
                        if (0 == $stock->extended) {
                            unset($stocks[$key]);
                        }else{
                            $product->options[] = $stock;
                        }
                    }

                }
                $return = $product;
            }
        }else{
            throw new \Exception($this->translator->trans("exception.no_product",['%product_code%'=>$product_code],"messages",$this->locale));
        }
        return $return;
    }

    public function generateCache($regenerateOptionsGroup = false,$regenerateOptions = false,$regenerateOptionsValues = false){
        $shop = $this->shop;
        $file = $this->file;

        $this->optionsGroupCacheKey = $shop.$file.'optionGroups';
        $this->optionsCacheKey = $shop.$file.'options';
        $this->optionsValuesCacheKey = $shop.$file.'optionValues';
        $this->productCacheKey = $shop.$file.'product';

        if (!$this->cache->contains($this->optionsGroupCacheKey) || $regenerateOptionsGroup){
            $resource = new OptionGroup($this->client);
            $resource->limit(50);
            $res = $resource->get();
            $pages = $res->pages;
            $full = [];
            if ($pages > 1){
                for ($x = 1;$x <= $pages;$x++){
                    $resource->page($x);
                    $full = array_merge($full,$resource->get()->getArrayCopy());
                }
                $this->cache->save($this->optionsGroupCacheKey,json_decode(json_encode($full),true));
            }else{
                $this->cache->save($this->optionsGroupCacheKey,json_decode(json_encode($resource->get()->getArrayCopy()),true));
            }

        }
        $this->optionsGroupCache = $this->cache->fetch($this->optionsGroupCacheKey);


        if (!$this->cache->contains($this->optionsCacheKey) || $regenerateOptions){
            $resource = new Option($this->client);
            $resource->limit(50);
            $res = $resource->get();
            $pages = $res->pages;
            $full = [];
            if ($pages > 1){
                for ($x = 1;$x <= $pages;$x++){
                    $resource->page($x);
                    $full = array_merge($full,$resource->get()->getArrayCopy());
                }
                $this->cache->save($this->optionsCacheKey,$full);
            }else{
                $this->cache->save($this->optionsCacheKey,$resource->get()->getArrayCopy());
            }

        }
        $this->optionsCache = $this->cache->fetch($this->optionsCacheKey);

        if (!$this->cache->contains($this->optionsValuesCacheKey) || $regenerateOptionsValues){
            $resource = new OptionValue($this->client);
            $resource->limit(50);
            $res = $resource->get();
            $pages = $res->pages;
            $full = [];
            if ($pages > 1){
                for ($x = 1;$x <= $pages;$x++){
                    $resource->page($x);
                    $full = array_merge($full,$resource->get()->getArrayCopy());
                }
                $this->cache->save($this->optionsValuesCacheKey,$full);
            }else {
                $this->cache->save($this->optionsValuesCacheKey, $resource->get()->getArrayCopy());
            }
        }
        $this->optionsValuesCache = $this->cache->fetch($this->optionsValuesCacheKey);

    }

    private function addOptionGroup($name){
        $optionGroup = [
            "filters" => 0,
            "translations" => [
                $this->lang => [
                    "name" => $name,
                ],
            ],
        ];
        $resource = new OptionGroup($this->client);
        $optionGroup = $resource->post($optionGroup);
        $this->generateCache(true);
        return $optionGroup;
    }

    private function addOption($group_id,$type,$name){
        $option = [
            "group_id" => $group_id,
            "order" => 0,
            "type" => $type,
            "stock" => 1 ,
            "filters" => 0,
            "required" => 1,
            "change_price_type" => $this->post['price_mode'],
            "change_price_value" => $this->post['price'],
            "percent" => ($this->post['price_percent'] == 1)?1:0,
            "translations" => [
                $this->lang => [
                    "name" => $name
                ]
            ]
        ];
        $resource = new Option($this->client);
        $option = $resource->post($option);
        $this->generateCache(false,true);
        return $option;
    }

    private function addOptionValue($option_id,$key){
        $optionValue = [];
        switch ($this->post['options'][$key]['type']){
            case "color":
                $optionValue['color'] = $this->post['options'][$key]['color'];
            case "select":
            case "radio":
                $optionValue['change_price_type'] = 0;
                $optionValue['change_price_value'] = 0;
                $optionValue['percent'] = 0;
                $optionValue['order'] = 0;
            case "file":
            case "text":
            case "checkbox":
                $optionValue['option_id'] = $option_id;
                $optionValue['translations'][$this->lang]['value'] = $this->post['options'][$key]['option_value'];
        }
        $resource = new OptionValue($this->client);
        $optionValue = $resource->post($optionValue);
        $this->generateCache(false,false,true);
        return $optionValue;
    }

    private function updateStock($stock_id,$data = []){
        $stock = [];
        if (!empty($data)){
            foreach (self::keys as $v){
                if (isset($data[$v])){
                    $stock[$v] = $data[$v];
                }
            }
        }
        $options = [];
        foreach ($this->post['options'] as $opt) {
            $options[$opt['option_id']] = $opt['ovalue_id'];
        }
        $stock['extended'] = 1;
        $stock['options'] = $options;
        try {
            $resource = new ProductStock($this->client);
            $stock_id = $resource->put($stock_id,$stock);
        }catch(\Exception $e){
            throw new \Exception($e->getMessage());
        }
        return $stock_id;
    }

    private function addStock($product_id,$data){
        $stock = [];
        foreach (self::keys as $v){
            if (isset($data[$v])){
                $stock[$v] = $data[$v];
            }
        }
        $options = [];
        foreach ($this->post['options'] as $opt) {
            $options[$opt['option_id']] = $opt['ovalue_id'];
        }
        $stock['product_id'] = $product_id;
        $stock['extended'] = 1;
        $stock['options'] = $options;
        try {
            $resource = new ProductStock($this->client);
            $stock = $resource->post($stock);
        }catch(\Exception $e){
            throw new \Exception($e->getMessage());
        }
        $this->generateProductCache($this->post['product_code'],true);
        return $stock;
    }

    private function checkOptionGroups($group_name){
        $lang = $this->lang;
        foreach ($this->optionsGroupCache as $optionGroups ){
            if ($group_name == $optionGroups['translations'][$lang]['name']){
                return $optionGroups['group_id'];
            }
        }
        return false;
    }

    private function checkOptions($group_id){
        $lang = $this->lang;
        $optionsArray = [];
        foreach ($this->optionsCache as $options) {
            if ($group_id == $options['group_id']) {
                if (isset($options['translations'][$lang]['name'])) {
                    $optionsArray[] = $options['translations'][$lang]['name'];
                }
            }
        }
        foreach ($this->post['options'] as $k => $options) {
            if (in_array($options['option'], $optionsArray)) {
                $this->post['options'][$k]['option_id'] = $this->findOptionIdByName($options['option'],$group_id);
            } else {
                $this->post['options'][$k]['option_id'] =
                    $this->addOption($group_id,
                        $this->post['options'][$k]['type'],
                        $this->post['options'][$k]['option']);
            }
        }
    }

    private function findOptionIdByName($name,$group_id){
        $lang = $this->lang;
        foreach ($this->optionsCache as $k => $option){
            if ($group_id == $option['group_id'] && $option['translations'][$lang]['name'] == $name){
                return $option['option_id'];
            }
        }
        return false;
    }

    private function findOptionValueIdByValue($option_id,$value){
        $lang = $this->lang;
        foreach ($this->optionsValuesCache as $k => $option){
            if ($option_id == $option['option_id'] && $value == $option['translations'][$lang]['value']){
                return $option['ovalue_id'];
            }
        }
        return false;
    }

    private function checkOptionValues($option_id,$group_id){
        $lang = $this->lang;
        $optionValues = [];
        $add = false;
        foreach ($this->optionsValuesCache as $ovalues) {
            if ($option_id == $ovalues['option_id']) {
                $optionValues[] = $ovalues['translations'][$lang]['value'];
            }
        }
        if (empty($optionValues)) {
            $add = true;
        }
        foreach ($this->post['options'] as $k => $options) {
            if (!$add) {
                if (in_array($options['option_value'], $optionValues)) {
                    $this->post['options'][$k]['ovalue_id'] = $this->findOptionValueIdByValue($option_id, $options['option_value']);
                } else {
                    if ($this->findOptionIdByName($options['option'], $group_id) == $option_id) {
                        $this->post['options'][$k]['ovalue_id'] = $this->addOptionValue($option_id, $k);
                    }
                }
            } else {
                if ($this->findOptionIdByName($options['option'], $group_id) == $option_id) {
                    $this->post['options'][$k]['ovalue_id'] = $this->addOptionValue($option_id, $k);
                }
            }
        }
    }

    private function checkExistOfOption(){
        foreach ($this->productCache['options'] as $key => $stock) {
            if ($this->post['option_code'] == $stock['code']){
                return $stock['stock_id'];
            }
        }
        return false;
    }

    private function createStockUpdate($stock_id){
        $data = [
            "price" => $this->post['price'],
            "price_type" => ($this->post['price_mode'] == 1)?1:0,
            "active" => $this->post['active'],
            "default" => 0, //$this->post['default'], change when fix goes live
            "stock" => $this->post['stock'],
            "code" => $this->post['option_code'],
            "weight" => $this->post['weight'],
            "weight_type" => ($this->post['weight'] > 0)?1:0,
        ];
        $stock_id = $this->updateStock($stock_id,$data);
        return $stock_id;
    }

    private function createStock(){
        $data = [
            "price" => $this->post['price'],
            "price_type" => ($this->post['price_mode'] == 1)?1:0,
            "active" => $this->post['active'],
            "default" => 0 ,//$this->post['default'], change when fix goes live
            "stock" => $this->post['stock'],
            "code" => $this->post['option_code'],
            "weight" => $this->post['weight'],
            "weight_type" => ($this->post['weight'] > 0)?1:0,
        ];
        $stock_id = $this->addStock($this->productCache->product_id,$data);
        return $stock_id;
    }

    private function createCode(){
        $code = $this->post['product_code'];
        foreach ($this->post['options'] as $option){
            $code .= "-".substr($option['option'],0,3)."-".substr($option['option_value'],0,3);
        }
        $this->post['option_code'] = $code;
    }

}
