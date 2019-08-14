<?php
namespace Neos\Flow\Cli;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\Exception\NoSuchOptionException;
use Neos\Flow\Mvc\Controller\ControllerInterface;
use Neos\Flow\Mvc\Controller\Exception\InvalidControllerException;
use Neos\Flow\Mvc\Exception\ForwardException;
use Neos\Flow\Mvc\Exception\InfiniteLoopException;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Mvc\Exception\UnsupportedRequestTypeException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

/**
 * @Flow\Scope("singleton")
 */
class Dispatcher
{
    /**
     * @var \Neos\Flow\SignalSlot\Dispatcher
     */
    protected $signalDispatcher;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param \Neos\Flow\SignalSlot\Dispatcher $signalDispatcher
     */
    public function injectSignalDispatcher(\Neos\Flow\SignalSlot\Dispatcher $signalDispatcher)
    {
        $this->signalDispatcher = $signalDispatcher;
    }

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Try processing the request until it is successfully marked "dispatched"
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws InvalidControllerException|InfiniteLoopException|NoSuchOptionException|UnsupportedRequestTypeException
     */
    public function dispatch(Request $request, Response $response)
    {
        $dispatchLoopCount = 0;
        /** @var Request $request */
        while (!$request->isDispatched()) {
            if ($dispatchLoopCount++ > 99) {
                throw new InfiniteLoopException(sprintf('Could not ultimately dispatch the request after %d iterations.', $dispatchLoopCount), 1217839467);
            }
            $controller = $this->resolveController($request);
            try {
                $this->emitBeforeControllerInvocation($request, $response, $controller);
                $controller->processRequest($request, $response);
                $this->emitAfterControllerInvocation($request, $response, $controller);
            } catch (StopActionException $exception) {
                $this->emitAfterControllerInvocation($request, $response, $controller);
                if ($exception instanceof ForwardException) {
                    $request = $exception->getNextRequest();
                } elseif (!$request->isMainRequest()) {
                    $request = $request->getParentRequest();
                }
            }
        }

        return $response;
    }

    /**
     * This signal is emitted directly before the request is been dispatched to a controller.
     *
     * @param Request $request
     * @param Response $response
     * @param ControllerInterface $controller
     * @return void
     */
    protected function emitBeforeControllerInvocation(Request $request, Response $response, ControllerInterface $controller)
    {
        $this->signalDispatcher->dispatch(\Neos\Flow\Mvc\Dispatcher::class, 'beforeControllerInvocation', [
            'request' => $request,
            'response' => $response,
            'controller' => $controller
        ]);
    }

    /**
     * This signal is emitted directly after the request has been dispatched to a controller and the controller
     * returned control back to the dispatcher.
     *
     * @param Request $request
     * @param Response $response
     * @param ControllerInterface $controller
     * @return void
     */
    protected function emitAfterControllerInvocation(Request $request, Response $response, ControllerInterface $controller)
    {
        $this->signalDispatcher->dispatch(\Neos\Flow\Mvc\Dispatcher::class, 'afterControllerInvocation', [
            'request' => $request,
            'response' => $response,
            'controller' => $controller
        ]);
    }

    /**
     * Finds and instantiates a controller that matches the current request.
     * If no controller can be found, an instance of NotFoundControllerInterface is returned.
     *
     * @param Request $request The request to dispatch
     * @return ControllerInterface
     * @throws NoSuchOptionException
     * @throws Controller\Exception\InvalidControllerException
     */
    protected function resolveController(Request $request)
    {
        $controllerObjectName = $request->getControllerObjectName();
        if ($controllerObjectName === '') {
            $exceptionMessage = 'No controller could be resolved which would match your request';
            throw new \Neos\Flow\Mvc\Routing\Exception\InvalidControllerException($exceptionMessage, 1303209195);
        }

        $controller = $this->objectManager->get($controllerObjectName);
        if (!$controller instanceof ControllerInterface) {
            throw new \Neos\Flow\Mvc\Routing\Exception\InvalidControllerException('Invalid controller "' . $request->getControllerObjectName() . '". The controller must be a valid request handling controller, ' . (is_object($controller) ? get_class($controller) : gettype($controller)) . ' given.', 1202921619);
        }

        return $controller;
    }
}
