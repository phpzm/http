<?php

namespace Simples\Http;

use Simples\Route\Match;

/**
 * Class Middleware
 * @package Simples\Http
 */
abstract class Middleware
{
    /**
     * @param Request $request
     * @param callable $next
     * @param Match $match
     * @return Response
     */
    public abstract function handle(Request $request, callable $next, Match $match): Response;
}
