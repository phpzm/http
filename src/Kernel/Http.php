<?php

namespace Simples\Http\Kernel;

use Simples\Http\Request;
use Simples\Http\Response;
use Simples\Kernel\App as Kernel;
use Simples\Route\Match;
use Simples\Route\Router;
use Throwable;

/**
 * Class Http
 * @package Simples\Kernel
 */
class Http
{
    /**
     * @var array
     */
    const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'find', 'purge', 'deletehard'];

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Match
     */
    private $match;

    /**
     * @var string
     */
    private $headerAccessControlRequestMethod = 'Access-Control-Request-Method';

    /**
     * Http constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param array $pipe
     * @return Response
     */
    public function handler(array $pipe = []): Response
    {
        // TODO: container
        $router = new Router(Kernel::options('labels'), Kernel::options('type'));

        $method = $this->request->getMethod();
        if ($this->request->hasHeader($this->headerAccessControlRequestMethod)) {
            $method = $this->request->getHeader($this->headerAccessControlRequestMethod);
        }
        /** @var Match $match */
        $this->match = static::routes($router)->match($method, $this->request->getUri());

        $handler = new Handler($this->request, $this->match, $pipe);

        return $handler->apply();
    }

    /**
     * Load the routes of project
     *
     * @param Router $router The router what will be used
     * @param array $files (null) If not informe will be used "route.files"
     * @return Router Object with the routes loaded in
     */
    public static function routes(Router $router, array $files = null)
    {
        $files = $files ? $files : Kernel::config('route.files');

        foreach ($files as $file) {
            $router->load(path(true, $file));
        }

        return $router;
    }

    /**
     * @param Throwable $fail
     * @return Response
     */
    public function fallback(Throwable $fail): Response
    {
        if (!$this->match) {
            $method = '';
            $uri = '';
            $path = '';
            $callback = null;
            $parameters = [];
            $options = [];
            $this->match = new Match($method, $uri, $path, $callback, $parameters, $options);
        }
        $this->match->setCallback($fail);

        $handler = new Handler($this->request, $this->match);

        return $handler->apply();
    }

    /**
     * @param Response $response
     */
    public function output(Response $response)
    {
        $headers = $response->getHeaders();
        foreach ($headers as $name => $value) {
            header(implode(':', [$name, $value]), true);
        }

        http_response_code($response->getStatusCode());

        $contents = $response->getBody()->getContents();
        if ($contents) {
            out($contents);
        }
    }
}
