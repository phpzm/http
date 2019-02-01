<?php
/**
 *
 */

namespace Simples\Http\Kernel;

use Exception;
use Simples\Error\SimplesRunTimeError;
use Simples\Http\Contract\ErrorHandler as Contract;
use Simples\Kernel\App;
use Throwable;

/**
 * Class ErrorHandler
 * @package Simples\Http\Kernel
 */
class ErrorHandler implements Contract
{
    /**
     * @param Throwable $error
     * @param array $meta
     * @return int
     */
    public function status(Throwable $error, array $meta = []): int
    {
        if ($error instanceof SimplesRunTimeError) {
            return $error->getStatus();
        }
        return (int)App::config('app.status.fail');
    }

    /**
     * @param Throwable $error
     * @param array $meta
     * @return array
     */
    public function meta(Throwable $error, array $meta = []): array
    {
        return array_merge($meta, error_format($error));
    }

    /**
     * @param Throwable $error
     * @param array $meta
     * @return mixed
     * @throws Exception
     */
    public function content(Throwable $error, array $meta = [])
    {
        return throw_format($error);
    }
}
