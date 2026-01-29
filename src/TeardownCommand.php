<?php declare(strict_types=1);

namespace KateGray\DnsChallenge;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TeardownCommand extends Command {
    protected function configure()
    {
        $this->setDescription ('Adds an ACME challenge to a CloudFlare zone')
             ->setName('teardown')
             ->setHelp('This command takes the provided zone and acme domain, ' .
                'makes an API call to Cloudflare, and deletes the required record.')
             ->addArgument('zone', InputArgument::REQUIRED,"Zone (domain name)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->getApplication()->find('setup');

        $arguments = [
            'zone' => $input->getArgument('zone'),
            'challenge' => false
        ];

        $setup_arguments = new ArrayInput($arguments);
        return $command->run($setup_arguments, $output);
    }
}
