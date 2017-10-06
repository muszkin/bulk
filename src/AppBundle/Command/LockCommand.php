<?php

namespace AppBundle\Command;

use AppBundle\Services\ApplicationManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LockCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:lock')
            ->setDescription('Lock application')
            ->addArgument("unlock",null,"Unlock",null)
            ->addArgument("shop",null,"Shop",null)

        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ApplicationManager::init($this->getContainer()->getParameter('config'));

        $unlock = $input->getArgument("unlock");
        $shop = $input->getArgument("shop");

        if ($unlock && !$shop){
            ApplicationManager::unlock();
            $output->writeln("Aplikacja została włączona");
        }else if ($unlock && $shop){
            ApplicationManager::unlockShop($shop);
            $output->writeln("Sklep został dodany do white listy");
        }else if (!$unlock && $shop){
            ApplicationManager::lockShop($shop);
            $output->writeln("Sklep został usunięty z white listy");
        }else if (!$unlock && !$shop){
            ApplicationManager::lock();
            $output->writeln("Aplikacja została wyłączona");
        }

    }
}
