<?php

namespace Fludio\RestApiGeneratorBundle\Listener;

use Fludio\RestApiGeneratorBundle\Api\ApiResponse;
use Fludio\RestApiGeneratorBundle\Controller\RestApiController;
use JMS\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

class SerializationListener
{
    /**
     * @var Serializer
     */
    private $serializer;
    private $isRestController = false;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        $controller = $event->getController()[0];
        $this->isRestController = $controller instanceof RestApiController;
    }

    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        if ($this->isRestController) {
            $data = $event->getControllerResult();

            $apiResponse = new ApiResponse(200, $data);

            $json = $this->serializer->serialize($apiResponse->toArray(), 'json');
            $response = new Response($json, 200, [
                'Content-Type' => 'application/json'
            ]);

            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}