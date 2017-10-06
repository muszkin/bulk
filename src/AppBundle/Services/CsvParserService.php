<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 10.01.17
 * Time: 15:23
 */

namespace AppBundle\Services;


use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\File\File;

class CsvParserService
{

    public function read(File $file = null, $delimiter=';')
    {
        $filename = $file->getPath().'/'.$file->getFilename();

        if(!file_exists($filename) || !is_readable($filename)) {
            throw new Exception('File not found');
        }

        $header = NULL;
        $data = array();

        if (($handle = fopen($filename, 'r')) !== FALSE)
        {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
            {
                if(!$header)
                    $header = $this->remove_utf8_bom($row);
                else
                    $data[] = array_combine($this->remove_utf8_bom($header), $this->remove_utf8_bom($row));
            }
            fclose($handle);
        }
        return $data;
    }

    public function remove_utf8_bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }

    public function detectDelimiter(File $file = null)
    {
        $filename = $file->getPath().'/'.$file->getFilename();

        if(!file_exists($filename) || !is_readable($filename)) {
            throw new Exception('File not found');
        }
        if (($fh = fopen($filename,'r')) !== FALSE) {
            $delimiters = ["\t", ";", "|", ","];
            $data_1 = null;
            $data_2 = null;
            $delimiter = $delimiters[0];
            foreach ($delimiters as $d) {
                $data_1 = fgetcsv($fh, 4096, $d);
                if (sizeof($data_1) > sizeof($data_2)) {
                    $delimiter = sizeof($data_1) > sizeof($data_2) ? $d : $delimiter;
                    $data_2 = $data_1;
                }
                rewind($fh);
            }
        }

        if ($delimiter != ';'){
            return false;
        }
        return true;
    }

}