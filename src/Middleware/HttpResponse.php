<?php

namespace Simples\Http\Middleware;

use Simples\Kernel\Middleware;
use Simples\Http\Contract\Middleware as Contract;
use Psr\Http\Message\ServerRequestInterface;
use Simples\Http\Contract\Delegate;
use Psr\Http\Message\ResponseInterface;
use Closure;

/**
 * Class HttpResponse
 * @package Simples\Http\Middleware
 */
class HttpResponse extends Middleware implements Contract
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * HttpResponse constructor.
     * @param $callable
     */
    public function __construct(Closure $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param Delegate $delegate
     *
     * @return ResponseInterface
     * @SuppressWarnings("Unused")
     */
    public function process(ServerRequestInterface $request, Delegate $delegate): ResponseInterface
    {
        return call_user_func_array($this->callable, []);
    }

}
