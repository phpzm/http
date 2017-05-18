<?php

namespace Simples\Http\Contract;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface Delegate
 * @package Simples\Http\Contract
 */
interface Delegate
{
    /**
     * Dispatch the next available middleware and return the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request): ResponseInterface;
}
