<?php

namespace Simples\Http\Error;

use Simples\Error\SimplesRunTimeError;

/**
 * Class ForbiddenError
 * @package Simples\Http\Error
 */
class SimplesForbiddenError extends SimplesRunTimeError
{
    /**
     * @var int
     */
    protected $status = 403;
}
