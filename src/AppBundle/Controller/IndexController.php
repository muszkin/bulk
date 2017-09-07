<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Upload;
use AppBundle\Form\UploadType;
use Doctrine\DBAL\DBALException;
use DreamCommerce\ShopAppstoreBundle\Controller\ApplicationController;
use DreamCommerce\ShopAppstoreBundle\Model\ShopInterface;
use DreamCommerce\ShopAppstoreLib\Resource;
use Mmoreram\GearmanBundle\Driver\Gearman\Job;
use Mmoreram\GearmanBundle\Module\JobStatus;
use Mmoreram\GearmanBundle\Service\GearmanClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndexController extends ApplicationController
{

    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $this->get('translator')->setLocale($request->get('locale'));

        $em = $this->getDoctrine()->getManager();

        $upload = $em->getRepository('AppBundle:Upload')->findOneBy([
            'shop' => $this->shop,
            'active' => 1,
        ]);

        /**
         * @var Upload $upload
         */
        if ($upload){
            return $this->render('@App/progress.html.twig',["shop"=>$this->shop,"upload"=>$upload->getId()]);
        }else{
            $uploads = $em->getRepository('AppBundle:Upload')->findBy(["shop"=>$this->shop]);
            foreach ($uploads as $upload){
                $em->remove($upload);
                $em->flush();
            }
        }

        $upload = new Upload();

        $resource = new Resource\Language($this->client);
        $resource->limit(50);

        $languages = $resource->get();

        $languagesList = [];

        foreach ($languages as $language){
            $languagesList[$language->locale] = $language->locale;
        }

        $form = $this->createForm(UploadType::class,$upload,['languages' => $languagesList]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()){

            /**
             * @var UploadedFile $file
             */

            $file = $upload->getFilename();

            try {
                $fileName = $this->get('file_uploader')->upload($file);
            }catch (UploadException $uploadException){
                $this->addFlash('error',$uploadException->getMessage());
            }
            $file = new File($this->getParameter("upload_dir").$fileName);

            if (!$this->get('csv_parser')->detectDelimiter($file)){
                $this->addFlash('error',"Wrong delimiter in file");
                $this->redirectToRoute("homepage");
            }
            $data = $this->get('csv_parser')->read($file);

            $upload->setShop($this->shop);
            $upload->setFilename($fileName);
            $upload->setFinished(false);
            $upload->setOffset(0);
            $upload->setActive(true);
            $upload->setTotal(count($data));


            try {
                $em->persist($upload);
                $em->flush();
            }catch(DBALException $DBALException){
                $this->addFlash('error',$DBALException->getMessage());
            }

            /** @var GearmanClient $gearman */
            $gearman = $this->get('gearman');

            try {
                $gearman->doBackgroundJob('AppBundleWorkerImportWorker~process', serialize(["file_id" => $upload->getId(),"locale" => $request->get('locale')]));
            }catch (\Exception $exception){
                $this->get('logger')->error($exception->getTraceAsString());
            }

            return $this->render('@App/progress.html.twig',["shop"=>$this->shop,"upload"=>$upload->getId()]);

        }

        return $this->render('@App/main.html.twig',['form' => $form->createView()]);
    }

    /**
     * @Route("/progress",name="progress")
     * @return string
     */
    public function getProgress(Request $request){
        $this->get('translator')->setLocale($request->get('locale'));
        $em = $this->getDoctrine();

        $upload = $em->getRepository('AppBundle:Upload')->findOneBy([
            'shop' => $this->shop
        ]);

        /**
         * @var Upload $upload
         */

        if (!is_null($upload)) {
            $errors = [];
            foreach ($upload->getErrors() as $error){
                $errors[] = [
                    "error_id" => $error->getId(),
                    "product_code" => $error->getProductCode(),
                    "error" => $error->getError()
                ];
            }
            $return = [
                "current" => $upload->getOffset(),
                "total" => $upload->getTotal(),
                "percent" => round(($upload->getOffset() * 100) / $upload->getTotal(), 2),
                "errors" => $errors
            ];
        }else{
            $return = [
                "current" => 0,
                "total" => 0,
                "percent" => 0,
                "errors" => [
                    "error_id" => 0,
                    "product_code" => 0,
                    "error" => 'File or shop didn\'t math',
                ]
            ];
        }

        return new JsonResponse($return);

    }

    /**
     * @param Upload $upload
     * @Route("/workdone/{upload}",name="workdone")
     * @return mixed
     */
    public function workDone(Upload $upload){
        $em = $this->getDoctrine();

        $upload = $em->getRepository('AppBundle:Upload')->findOneBy([
            'id' => $upload,
        ]);

        if ($upload){
            $em->getEntityManager()->remove($upload);
            $em->getEntityManager()->flush();
        }
        return $this->redirect($this->generateAppUrl('homepage'));
    }
}
