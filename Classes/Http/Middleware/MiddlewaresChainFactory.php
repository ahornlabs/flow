<?php
namespace Neos\Flow\Http\Middleware;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentAfterWrapperMiddleware;
use Neos\Flow\Http\Component\ComponentBeforeWrapperMiddleware;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\PositionalArraySorter;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Creates a new MiddlewaresChain according to the specified settings
 *
 * @Flow\Scope("singleton")
 */
class MiddlewaresChainFactory
{
    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var ComponentContext
     */
    protected $componentContext;

    /**
     * MiddlewaresChainFactory constructor.
     * @param ComponentContext $componentContext
     */
    public function __construct(ComponentContext $componentContext)
    {
        $this->componentContext = $componentContext;
    }

    /**
     * @param array $chainConfiguration
     * @param string $parentChain
     * @return MiddlewaresChain
     * @throws Exception
     */
    public function create(array $chainConfiguration, $parentChain = 'default'): MiddlewaresChain
    {
        if (empty($chainConfiguration)) {
            return new MiddlewaresChain($parentChain);
        }
        $arraySorter = new PositionalArraySorter($chainConfiguration);
        $sortedChainConfiguration = $arraySorter->toArray();

        $middlewaresChain = [];
        foreach ($sortedChainConfiguration as $middlewareName => $configuration) {
            if (isset($configuration['chain'])) {
                $middleware = $this->create($configuration['chain'], $middlewareName);
            } else {
                if (!isset($configuration['middleware'])) {
                    throw new Exception(sprintf('Middleware chain could not be created because no middleware class name is configured for middleware "%s"', $middlewareName), 1401718283);
                }
                // TODO: lazy instantiation of the middlewares?
                $middleware = $this->objectManager->get($configuration['middleware']);
                // TODO: b/c layer that allows configuring old Http Components as middlewares
                if ($middleware instanceof ComponentInterface) {
                    if ($parentChain === 'preprocess') {
                        $middleware = new ComponentBeforeWrapperMiddleware($middleware);
                    } elseif ($parentChain === 'postprocess') {
                        $middleware = new ComponentAfterWrapperMiddleware($middleware);
                    }
                }
                if (!$middleware instanceof MiddlewareInterface) {
                    throw new Exception(sprintf('Middleware chain could not be created because the class "%s" does not implement the MiddlewareInterface in middleware "%s"', $configuration['middleware'], $middlewareName), 1401718283);
                }
                if (method_exists($middleware, 'setComponentContext')) {
                    $middleware->setComponentContext($this->componentContext);
                }
            }
            $middlewaresChain[] = $middleware;
        }

        return new MiddlewaresChain($parentChain, $middlewaresChain);
    }
}
