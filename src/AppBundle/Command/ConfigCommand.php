<?php

namespace AppBundle\Command;

use AppBundle\Services\ApplicationManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:check_config')
            ->setDescription('Checking application config.ini file')
            ->addArgument("shop",null,null,null)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $shop = $input->getArgument("shop");

        ApplicationManager::init($this->getContainer()->getParameter('config'));
        if ($shop) {
            if (!ApplicationManager::check($shop)){
                $output->writeln("Sklep {$shop} ma dostęp do aplikacji");
            }else{
                $output->writeln("Sklep {$shop} nie ma dostępu do aplikacji");
            }
        }else{
            if (ApplicationManager::isLocked()){
                $output->writeln("Aplikacja jest zablokowana dla wszystkich");
            }else{
                $output->writeln("Aplikacja nie jest zablokowana dla wszystkich");
            }
        }
    }
}
