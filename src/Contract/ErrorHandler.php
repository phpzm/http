<?php
/**
 *
 */

namespace Simples\Http\Contract;

use Throwable;

/**
 * Interface ErrorHandler
 * @package Simples\Http\Contract
 */
interface ErrorHandler
{
    /**
     * @param Throwable $error
     * @param array $meta
     * @return int
     */
    public function status(Throwable $error, array $meta = []): int;

    /**
     * @param Throwable $error
     * @param array $meta
     * @return array
     */
    public function meta(Throwable $error, array $meta = []): array;

    /**
     * @param Throwable $error
     * @param array $meta
     * @return mixed
     */
    public function content(Throwable $error, array $meta = []);
}
