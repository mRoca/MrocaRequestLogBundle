<?php

namespace Mroca\RequestLogBundle\EventListener;

use Mroca\RequestLogBundle\Service\ResponseLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ResponseLogSubscriber implements EventSubscriberInterface
{
    /** @var ResponseLogger */
    private $responseLogger;

    const GENERATE_LOG_HEADER = 'x-generate-response-mock';

    public function __construct(ResponseLogger $responseLogger)
    {
        $this->responseLogger = $responseLogger;
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        if (!$event->isMasterRequest() || !$event->getRequest()->headers->has(self::GENERATE_LOG_HEADER)) {
            return;
        }

        $this->responseLogger->logReponse($event->getRequest(), $event->getResponse());
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }
}
