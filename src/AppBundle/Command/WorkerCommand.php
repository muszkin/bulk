<?php
/**
 * Created by PhpStorm.
 * User: muszkin
 * Date: 09.01.17
 * Time: 13:22
 */

namespace AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends ContainerAwareCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('bulkapp:worker')
            ->setDescription('Execute importer worker');
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $command = $this->getApplication()->find('gearman:worker:execute');
        $arguments = [
            'command' => 'gearman:worker:execute',
            'worker' => 'AppBundleWorkerImportWorker',
            '-n' => true,
        ];

        $input = new ArrayInput($arguments);

        $command->run($input,$output);
    }
}