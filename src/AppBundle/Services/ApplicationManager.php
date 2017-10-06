<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 20.09.17
 * Time: 09:42
 */

namespace AppBundle\Services;

class ApplicationManager
{
    private static $config;
    private static $path;

    public static function init($config)
    {
        self::$path = $config;
    }

    public static function readConfigFile()
    {
        $config = parse_ini_file(self::$path,true);

        self::$config = $config;
    }

    public static function isLocked()
    {
        self::readConfigFile();

        if (self::$config['app']['lock'] == "1"){
            return true;
        }
        return false;
    }

    public static function checkForUnlockedShop($shop)
    {
        self::readConfigFile();

        if (in_array($shop,self::$config['shops'])){
            return true;
        }
        return false;
    }

    public static function check($shop)
    {
        if (self::isLocked()){
            if (self::checkForUnlockedShop($shop)){
                return false;
            }else{
                return true;
            }
        }else{
            return true;
        }


    }

    public static function lock()
    {
        self::readConfigFile();

        self::$config['app']['lock'] = 1;

        self::writeIniFile();
    }

    public static function unlock()
    {
        self::readConfigFile();

        self::$config['app']['lock'] = 0;

        self::writeIniFile();
    }

    public static function unlockShop($shop)
    {
        self::readConfigFile();

        $is_there = false;
        foreach (self::$config['shops'] as $key => $value){
            echo "{$key} => {$value}\n";
            if ($value == $shop){
                $is_there = true;
            }
        }
        if (!$is_there) {
            self::$config['shops'][] = $shop;
        }

        self::writeIniFile();
    }

    public static function lockShop($shop)
    {
        self::readConfigFile();

        foreach (self::$config['shops'] as $key => $value){
            if ($value == $shop){
                unset(self::$config['shops'][$key]);
            }
        }

        self::writeIniFile();
    }

    public static function writeIniFile()
    {
        @file_put_contents(self::$path,self::convertArrayToIniFile(self::$config));
    }

    public static function convertArrayToIniFile(array $array,array $parent = array() )
    {
        $out = '';
        foreach ($array as $k => $v)
        {
            if (is_array($v))
            {
                $sec = array_merge((array) $parent, (array) $k);
                $out .= '[' . join('.', $sec) . ']' . PHP_EOL;
                $out .= self::convertArrayToIniFile($v, $sec).PHP_EOL;
            }
            else
            {
                $out .= "$k=$v" . PHP_EOL;
            }
        }

        return $out;
    }
}