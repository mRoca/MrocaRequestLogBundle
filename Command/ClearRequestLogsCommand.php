<?php

namespace Mroca\RequestLogBundle\Command;

use Mroca\RequestLogBundle\Service\ResponseLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearRequestLogsCommand extends Command
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
            ->setName('mroca:response-logs:clear')
            ->setDescription('Empty the responses mocks directory');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->responseLogger->clearMocksDir();

        return 0;
    }
}
