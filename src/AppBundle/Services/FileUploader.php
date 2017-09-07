<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 23.08.16
 * Time: 09:00
 */

namespace AppBundle\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private $targetDir;

    public function __construct($targetDir)
    {
        $this->targetDir = $targetDir;
    }

    public function upload(UploadedFile $file){
        $fileName = md5(uniqid()).'.'.$file->getClientOriginalExtension();

        $file->move($this->targetDir,$fileName);

        return $fileName;
    }
}