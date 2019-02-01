<?php

namespace Simples\Http\Kernel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Simples\Error\NotFoundExceptionInterface;
use Simples\Http\Contract\Delegate as Contract;
use Simples\Http\Contract\Middleware;
use Simples\Http\Response;
use Throwable;

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
     * @throws NotFoundExceptionInterface
     */
    public function process(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Middleware $middleware */
        $middleware = current($this->middlewares);
        next($this->middlewares);

        // return $middleware->process($request, $this);
        try {
            $response = $middleware->process($request, $this);
        } catch (Throwable $error) {
            // next lines
        }
        if (!isset($response)) {
            $response = App::response();
        }

        if (isset($error) && $response instanceof Response) {
            $response->setError($error);
        }
        return $response;
    }
}
