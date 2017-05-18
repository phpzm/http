<?php

namespace Simples\Http\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface Middleware
 * @package Simples\Http\Contract
 */
interface Middleware
{
    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param Delegate $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, Delegate $delegate): ResponseInterface;
}
