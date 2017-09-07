<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Error
 *
 * @ORM\Table(name="error")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ErrorRepository")
 */
class Error
{
    /**
     * @var int
     *
     * @ORM\Column(name="error_id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="product_code", type="string", length=255)
     */
    private $productCode;

    /**
     * @var string
     *
     * @ORM\Column(name="error", type="string", length=255)
     */
    private $error;

    /**
     * @var Upload
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Upload",inversedBy="id")
     * @ORM\JoinColumn(referencedColumnName="id",onDelete="CASCADE")
     */
    private $upload;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set productCode
     *
     * @param string $productCode
     *
     * @return Error
     */
    public function setProductCode($productCode)
    {
        $this->productCode = $productCode;

        return $this;
    }

    /**
     * Get productCode
     *
     * @return string
     */
    public function getProductCode()
    {
        return $this->productCode;
    }

    /**
     * Set error
     *
     * @param string $error
     *
     * @return Error
     */
    public function setError($error)
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Get error
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set upload
     *
     * @param \AppBundle\Entity\Upload $upload
     *
     * @return Error
     */
    public function setUpload(\AppBundle\Entity\Upload $upload = null)
    {
        $this->upload = $upload;

        return $this;
    }

    /**
     * Get upload
     *
     * @return \AppBundle\Entity\Upload
     */
    public function getUpload()
    {
        return $this->upload;
    }

    public function toArray(){
        return [
            "error_id" => $this->getId(),
            "product_code" => $this->getProductCode(),
            "error" => $this->getError()
        ];
    }
}
