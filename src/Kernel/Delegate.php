<?php

namespace Simples\Http\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Simples\Http\Contract\Delegate as Contract;
use Simples\Http\Contract\Middleware;

/**
 * Class Delegate
 * @package Simples\Http\Kernel
 */
class Delegate implements Contract
{
    /**
     * @var array
     */
    private $middlewares = [];

    /**
     * Delegate constructor.
     * @param array $middlewares
     */
    public function __construct(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }

    /**
     * Dispatch the next available middleware and return the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     * @SuppressWarnings("Unused")
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Middleware $middleware */
        $middleware = current($this->middlewares);
        next($this->middlewares);

        return $middleware->process($request, $this);
    }
}
