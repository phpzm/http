<?php

namespace Simples\Http\Kernel;

use ErrorException;
use Simples\Http\Response;
use Simples\Kernel\App as Kernel;
use Simples\Http\Request;
use Simples\Kernel\Container;
use Simples\Persistence\Transaction;
use Throwable;

/**
 * Class App
 * @package Simples\Http\Kernel
 */
class App
{
    /**
     * Used to catch http requests and handle response to their
     *
     * @param bool $output (true) Define if the method will generate one output with the response
     * @return mixed The match response for requested resource
     * @throws ErrorException Generated when is not possible commit the changes
     */
    public static function handler(bool $output = true)
    {
        $fail = null;
        $response = null;

        $http = new Http(self::request());
        try {
            $response = $http->handler();
            if ($response->isSuccess()) {
                static::commit();
            }
        } catch (Throwable $throw) {
            $fail = $throw;
        }

        if ($fail) {
            $response = $http->fallback($fail);
        }

        if ($output) {
            $http->output($response);
        }

        return $response;
    }

    /**
     * Make a global commit of all changes made into request
     *
     * @throws ErrorException Generated when is not possible commit the changes
     */
    private static function commit()
    {
        if (!Transaction::commit()) {
            throw new ErrorException("Transaction can't commit the changes");
        }
    }

    /**
     * Singleton to Request to keep only one instance for each request
     *
     * @return Request Request object populated by server data
     */
    public static function request()
    {
        $container = Container::instance();
        if (!$container->has('request')) {
            $request = new Request(Kernel::options('strict'));
            $container->register('request', $request->fromServer());
        }
        return $container->get('request');
    }

    /**
     * Simple helper to generate a valid route to resources of project
     *
     * Ex.: `self::route('/download/images/picture.png')`, will print //localhost/download/images/picture.png
     *
     * @param string $uri Path to route
     * @return string
     */
    public static function route($uri)
    {
        return '//' . self::request()->getUrl() . '/' . ($uri{0} === '/' ? substr($uri, 1) : $uri);
    }

    /**
     * Singleton to Response to keep only one instance for each request
     *
     * @return Response Response object populated by server data
     */
    public static function response()
    {
        $container = Container::instance();
        if (!$container->has('response')) {
            $container->register('response', new Response());
        }
        return $container->get('response');
    }
}
