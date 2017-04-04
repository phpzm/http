<?php

namespace Simples\Http\Kernel;

use Simples\Error\SimplesRunTimeError;
use Simples\Http\Controller;
use Simples\Http\Error\SimplesForbiddenError;
use Simples\Http\Request;
use Simples\Http\Response;
use Simples\Kernel\App as Kernel;
use Simples\Kernel\Container;
use Simples\Kernel\Wrapper;
use Simples\Route\Match;
use Throwable;

/**
 * Class HandlerHttp
 * @package Simples\Kernel
 *
 * @SuppressWarnings("camelCasePropertyName")
 */
class HttpHandler extends Response
{
    /**
     * @var Request
     */
    private $REQUEST;

    /**
     * @var Match
     */
    private $MATCH;

    /**
     * @var string
     */
    private $headerOrigin = 'Origin';

    /**
     * @var string
     */
    private $headerAccessControlRequestHeaders = 'Access-Control-Request-Headers';

    /**
     * HandlerHttp constructor.
     * @param Request $request
     * @param Match $match
     */
    public function __construct(Request $request, Match $match)
    {
        parent::__construct();

        $this->REQUEST = $request;
        $this->MATCH = $match;
    }

    /**
     * @return Request
     */
    public function request()
    {
        return $this->REQUEST;
    }

    /**
     * @return Match
     */
    public function match()
    {
        return $this->MATCH;
    }

    /**
     * @param $content
     * @return bool
     */
    private function isResponse($content)
    {
        return $content instanceof Response;
    }

    /**
     * @return mixed
     */
    public function apply()
    {
        $response = $this->resolve();
        if ($this->isCORS()) {
            $response->configureResponseCORS($this->request()->getHeader($this->headerOrigin));
        }

        return $response;
    }

    /**
     * @return bool
     */
    private function isCORS()
    {
        return (boolean)off($this->match()->getOptions(), 'cors');
    }

    /**
     * @return bool
     */
    private function isPreFlightCORS()
    {
        return strtolower($this->request()->getMethod()) === 'options';
    }

    /**
     * @return $this
     * @throws SimplesForbiddenError
     */
    private function allowAccessCORS()
    {
        $origin = $this->request()->getHeader($this->headerOrigin);
        $verbs = $this->request()->getHeader($this->headerAccessControlRequestHeaders);
        $allowed = off($this->match()->getOptions(), 'cors');

        if (is_string($allowed) && $origin !== $allowed) {
            throw new SimplesForbiddenError("The origin `{$origin}` is not allowed");
        }
        if (is_array($allowed) && !in_array($origin, $allowed)) {
            throw new SimplesForbiddenError("The origin `{$origin}` is not in allowed list");
        }
        return $this->configureResponseCORS($origin)->preFlight($verbs);
    }

    /**
     * @return HttpHandler|Response
     */
    final private function resolve()
    {
        if ($this->isCORS() && $this->isPreFlightCORS()) {
            return $this->allowAccessCORS();
        }

        /** @var mixed $callback */
        $callback = $this->match()->getCallback();

        if (!$callback) {
            return $this->parse(null);
        }

        if ($callback instanceof Throwable) {
            return $this->parse($callback);
        }

        if (gettype($callback) !== TYPE_OBJECT) {
            return $this->controller($callback);
        }

        return $this->call($callback->bindTo($this), $this->parameters($callback));
    }

    /**
     * @param $callback
     * @return Response
     * @throws SimplesRunTimeError
     */
    private function controller($callback)
    {
        $callable = $this->getCallable($callback);

        if (isset($callable['class'])) {
            $class = $callable['class'];

            /** @var \Simples\Http\Controller $controller */
            $controller = Container::instance()->make($class);
            if (!($controller instanceof Controller)) {
                throw new SimplesRunTimeError("The class must be a instance of Controller, `{$class}` given");
            }
            $controller->boot($this->request(), $this, $this->match());

            $method = $callable['method'] ?? null;
            /** @noinspection PhpParamsInspection */
            if (!method_exists($controller, $method) && is_callable($controller)) {
                $method = '__invoke';
            }
            if ($method) {
                return $this->call([$controller, $method], $this->parameters($controller, $method));
            }
        }

        return $this->parse(null);
    }

    /**
     * @param $callback
     * @return array
     */
    private function getCallable($callback): array
    {
        switch (gettype($callback)) {
            case TYPE_ARRAY:
                if (isset($callback[0]) && isset($callback[1])) {
                    return [
                        'class' => $callback[0],
                        'method' => $callback[1]
                    ];
                }
                foreach ($callback as $key => $value) {
                    return [
                        'class' => $key,
                        'method' => $value
                    ];
                }
                break;
            case TYPE_STRING:
                $peaces = explode(Kernel::options('separator'), $callback);
                $class = $peaces[0];
                $method = camelize(substr($this->match()->getUri(), 1, -1), false);
                if (isset($peaces[1])) {
                    $method = $peaces[1];
                }
                return [
                    'class' => $class,
                    'method' => $method
                ];
        }
        return [];
    }

    /**
     * @param $callable
     * @param $method
     * @return array
     */
    private function parameters($callable, $method = null)
    {
        $data = is_array($this->match()->getParameters()) ? $this->match()->getParameters() : [];
        $options = $this->match()->getOptions();

        $labels = isset($options['labels']) ? $options['labels'] : true;
        if ($method) {
            return Container::instance()->resolveMethodParameters($callable, $method, $data, $labels);
        }
        return Container::instance()->resolveFunctionParameters($callable, $data, $labels);
    }

    /**
     * @param $callback
     * @param array $parameters
     * @return Response
     */
    private function call($callback, $parameters)
    {
        ob_start();
        try {
            $result = call_user_func_array($callback, $parameters);
        } catch (Throwable $throw) {
            $result = $throw;
        }

        $contents = ob_get_contents();
        if ($contents) {
            ob_end_clean();
            Wrapper::buffer($contents);
        }

        return $this->parse($result);
    }

    /**
     * @param $content
     * @return Response
     */
    private function parse($content): Response
    {
        // TODO: organize usage of status codes
        $output = [];
        if (env('TEST_MODE')) {
            $output = Wrapper::messages();
        }

        if ($this->isResponse($content)) {
            /** @var Response $content */
            return $content->meta('output', $output);
        }

        $status = 200;
        if (empty($this->match()->getPath()) || is_null($content)) {
            $status = 404;
            if (is_null($content)) {
                $status = 501; // not implemented
            }
        }

        $meta = [
            'output' => $output
        ];
        if ($content instanceof Throwable) {
            $status = 500;
            if ($content instanceof SimplesRunTimeError) {
                $status = $content->getStatus();
            }
            $meta = array_merge($meta, error_format($content));
            $content = throw_format($content);
        }

        $method = Kernel::options('type');

        return $this->$method($content, $status, $meta);
    }
}
