<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 09.01.17
 * Time: 13:56
 */

namespace AppBundle\Worker;


use AppBundle\Entity\Error;
use AppBundle\Services\AttributesService;
use AppBundle\Services\CsvParserService;
use AppBundle\Services\Interfaces\ImportInterface;
use AppBundle\Services\OptionsService;
use Doctrine\ORM\EntityManagerInterface;
use DreamCommerce\ShopAppstoreBundle\Handler\Application;
use DreamCommerce\ShopAppstoreBundle\Model\ShopInterface;
use Monolog\Logger;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Mmoreram\GearmanBundle\Driver\Gearman;
use Mmoreram\GearmanBundle\Command\Util\GearmanOutputAwareInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * importer worker
 *
 * @Gearman\Work(
 *     service="importer_worker"
 * )
 */
class ImportWorker implements GearmanOutputAwareInterface
{
    /** @var OutputInterface */
    protected $output;

    /**
     * @var EntityManagerInterface $entityManager
     */
    private $entityManager;

    /**
     * @var ImportInterface $importer
     */
    private $importer;

    /**
     * @var UploadedFile $file
     */
    private $file;

    /**
     * @var string $path
     */
    private $path;

    /**
     * @var CsvParserService $csv_parser
     */
    private $csvParser;

    /**
     * @var int $lang;
     */
    private $lang;

    /**
     * @var string $shop
     */
    private $shop;

    /**
     * @var array $data
     */
    private $data;

    /**
     * @var ImportInterface $options
     */
    private $options;

    /**
     * @var ImportInterface $attributes
     */
    private $attributes;

    /**
     * @var Container $container
     */
    private $container;

    /**
     * @var Application $application
     */
    private $application;

    /**
     * @var Logger $logger
     */
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        $path,
        Container $container,
        CsvParserService $csvParser,
        ImportInterface $options,
        ImportInterface $attributes,
        Logger $logger
    )
    {
        $this->entityManager = $entityManager;
        $this->path = $path;
        $this->csvParser = $csvParser;
        $this->options = $options;
        $this->attributes = $attributes;
        $this->container = $container;
        $this->logger = $logger;
        $this->init();
    }

    public function init(){
        $this->registerSignalHandler();
    }

    public function registerSignalHandler(){
        pcntl_signal(SIGTERM, function(){
            $this->output->writeln('SIGNAL RECEIVED, terminating');
            die;
        });
    }

    /**
     * @param \GearmanJob $job
     * @Gearman\Job()
     * @return string
     * @throws \Exception
     */
    public function process(\GearmanJob $job){
        try {
            if(FALSE == $this->entityManager->getConnection()->ping()){
                $this->entityManager->getConnection()->close();
                $this->entityManager->getConnection()->connect();
            }
        } catch (\Throwable $ex) {
            $this->logger->error('Cannot refresh connection : '.$ex->getMessage());
        }

        try {
            $workload = unserialize($job->workload());
            $file_id = $workload['file_id'];
            $locale = $workload['locale'];
            $this->logger->debug('Upload id:'.$file_id);

            $upload = $this->entityManager->getRepository('AppBundle:Upload')->find($file_id);
            $date = date('Y-m-d H:i:s');
            $this->output->writeln("{$date} Got work from shop {$upload->getShop()->getShopUrl()},file id:$file_id");

            $file = new File($this->path.$upload->getFilename());

            try {
                $data = $this->csvParser->read($file);

                $interface = $upload->getType();

                /**
                 * @var OptionsService|AttributesService $importer
                 */
                $importer = $this->$interface;

                $shop = $this->entityManager->getRepository(ShopInterface::class)->find($upload->getShop());

                $this->application = $this->container->get('dream_commerce_shop_appstore.app.'.$shop->getApp());

                $client = $this->application->getClient($shop);

                $total = count($data);
                $upload->setTotal($total);
                $count = 1;
                if ($upload->isActive()) {
                    foreach ($data as $row) {
                        $date = date('Y-m-d H:i:s');
                        $this->output->write("{$date} Parsing row - result:");
                        try {
                            $result = $importer->processData(
                                $client,
                                $upload->getLang(),
                                $upload->getShop(),
                                $upload->getFilename(),
                                $row,
                                $locale
                            );
                            $this->output->writeln($result);
                        }catch (\Exception $exception){
                            $error = new Error();
                            $error->setUpload($upload);
                            $error->setError($exception->getMessage());
                            try {
                                $error->setProductCode($row['product_code']);
                            }catch (\Exception $exception){
                                $error->setProductCode("Brak kodu");
                                $error->setError("Błąd odczytania product_code - popraw plik");
                            }


                            $this->entityManager->persist($error);
                            $this->entityManager->flush();

                            $this->output->writeln($exception->getMessage());
                        }



                        $upload->setOffset($count);

                        $this->entityManager->persist($upload);
                        $this->entityManager->flush();
                        $count++;

                    }
                    $upload->setFinished(true);
                    $upload->setActive(false);
                    $this->entityManager->persist($upload);
                    $this->entityManager->flush();
                    $date = date('Y-m-d H:i:s');
                    $this->output->writeln("{$date} Work done.");
                }
            }catch(Exception $exception){
                $this->output->write($exception->getMessage());
            }
        }catch (\Exception $exception){
            throw new \Exception($exception->getMessage().PHP_EOL.$exception->getTraceAsString());
        }catch (\Throwable $exception){
            throw new \Exception($exception->getMessage().PHP_EOL.$exception->getTraceAsString());
        }
        return true;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }



}