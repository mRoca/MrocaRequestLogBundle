<?php

namespace Mroca\RequestLogBundle\Command;

use Mroca\RequestLogBundle\Service\ResponseLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpRequestLogsCommand extends Command
{
    /** @var ResponseLogger */
    private $responseLogger;

    public function __construct(ResponseLogger $responseLogger)
    {
        $this->responseLogger = $responseLogger;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mroca:response-logs:dump')
            ->setDescription('Copy all responses mocks in another directory')
            ->addArgument('target_directory', InputArgument::REQUIRED, 'The mocks target directory');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetDirectory = $input->getArgument('target_directory');

        $this->responseLogger->dumpMocksTo($targetDirectory);

        return 0;
    }
}
