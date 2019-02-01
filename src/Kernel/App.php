<?php

namespace Simples\Http\Kernel;

use ErrorException;
use Simples\Error\NotFoundExceptionInterface;
use Simples\Error\SimplesRunTimeError;
use Simples\Http\Request;
use Simples\Http\Response;
use Simples\Kernel\App as Kernel;
use Simples\Kernel\Container;
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
     * @param array $middlewares Resume of middlewares to be solved
     * @param bool $output (true) Define if the method will generate one output with the response
     * @return mixed The match response for requested resource
     * @throws NotFoundExceptionInterface
     * @throws SimplesRunTimeError
     */
    public static function handle(array $middlewares = [], bool $output = true)
    {
        $http = new Http(self::request());
        try {
            $response = $http->handle($middlewares);
            if ($response->isSuccess()) {
                static::commit();
            }
        } catch (Throwable $throw) {
            $response = App::response();
            $fail = $throw;
        }

        if (!isset($fail) && $response instanceof Response) {
            $fail = $response->getError();
        }

        if (isset($fail)) {
            $fallback = $http->fallback($fail);
        }

        if (isset($fallback)) {
            /** @var Response $response */
            $response = $response->withStatus($fallback->getStatusCode());
            $headers = $fallback->getHeaders();
            foreach ($headers as $name => $value) {
                $response = $response->withAddedHeader($name, $value);
            }
            $response = $response->withBody($fallback->getBody());
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
        $transaction = env('SIMPLES_TRANSACTION_CLASS', '\\Simples\\Persistence\\Transaction');
        if (!class_exists($transaction)) {
            return;
        }

        if (!method_exists($transaction, 'commit')) {
            throw new ErrorException("The transaction commit method was not found");
        }

        /** @noinspection PhpUnnecessaryFullyQualifiedNameInspection, PhpUndefinedMethodInspection */
        if (!$transaction::commit()) {
            throw new ErrorException("Transaction can't commit the changes");
        }
    }

    /**
     * Singleton to Request to keep only one instance for each request
     *
     * @return Request Request object populated by server data
     * @throws NotFoundExceptionInterface
     * @throws SimplesRunTimeError
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
     * @throws NotFoundExceptionInterface
     * @throws SimplesRunTimeError
     */
    public static function route($uri)
    {
        return '//' . self::request()->getUrl() . '/' . ($uri{0} === '/' ? substr($uri, 1) : $uri);
    }

    /**
     * Singleton to Response to keep only one instance for each request
     *
     * @return Response The Response object to populated by request resolution
     * @throws NotFoundExceptionInterface
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
