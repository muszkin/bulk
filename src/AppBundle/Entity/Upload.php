<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\ShopAppstoreBundle\Model\ShopInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Upload
 *
 * @ORM\Table(name="upload")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UploadRepository")
 */
class Upload
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="filename", type="string", length=255)
     */
    private $filename;

    /**
     * @ORM\ManyToOne(targetEntity="BillingBundle\Entity\Shop",inversedBy="id")
     * @ORM\JoinColumn(name="shop_id",referencedColumnName="id",onDelete="CASCADE")
     */
    private $shop;

    /**
     * @var bool
     *
     * @ORM\Column(name="finished", type="boolean",options={"default":0})
     */
    private $finished;

    /**
     * @var int
     *
     * @ORM\Column(name="offset", type="integer",options={"default":0})
     */
    private $offset;


    /**
     * @var string
     *
     * @ORM\Column(name="type",type="string")
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(name="lang",type="string")
     */
    private $lang;

    /**
     * @var int
     *
     * @ORM\Column(name="total",type="integer",options={"default":0})
     */
    private $total;

    /**
     * @var bool
     *
     * @ORM\Column(name="active",type="boolean",options={"default":0})
     */
    private $active;

    /**
     * @var array
     *
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Error",mappedBy="upload",cascade={"remove"})
     */
    private $errors;


    public function __construct(){
        $this->errors = new ArrayCollection();
    }
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
     * Set filename
     *
     * @param string $filename
     *
     * @return Upload
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set shop
     *
     * @param ShopInterface $shop
     *
     * @return Upload
     */
    public function setShop(ShopInterface $shop)
    {
        $this->shop = $shop;

        return $this;
    }

    /**
     * Get shop
     *
     * @return ShopInterface
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * Set finished
     *
     * @param boolean $finished
     *
     * @return Upload
     */
    public function setFinished($finished)
    {
        $this->finished = $finished;

        return $this;
    }

    /**
     * Get finished
     *
     * @return bool
     */
    public function getFinished()
    {
        return $this->finished;
    }

    /**
     * Set offset
     *
     * @param integer $offset
     *
     * @return Upload
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Get offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    public function __toString()
    {
      return $this->getFilename().'/'.$this->getType().'/'.$this->getShop().'/'.$this->getLang();
    }

    /**
     * @return int
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @param int $total
     */
    public function setTotal($total)
    {
        $this->total = $total;
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive($active)
    {
        $this->active = $active;
    }

    /**
     * Get active
     *
     * @return boolean
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Add error
     *
     * @param \AppBundle\Entity\Error $error
     *
     * @return Upload
     */
    public function addError(\AppBundle\Entity\Error $error)
    {
        $this->errors[] = $error;

        return $this;
    }

    /**
     * Remove error
     *
     * @param \AppBundle\Entity\Error $error
     */
    public function removeError(\AppBundle\Entity\Error $error)
    {
        $this->errors->removeElement($error);
    }

    /**
     * Get errors
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
