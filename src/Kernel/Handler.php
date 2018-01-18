<?php

namespace Simples\Http\Kernel;

use Psr\Http\Message\ResponseInterface;
use Simples\Error\SimplesRunTimeError;
use Simples\Http\Controller;
use Simples\Http\Middleware\HttpResponse;
use Simples\Http\Request;
use Simples\Http\Response;
use Simples\Kernel\App as Kernel;
use Simples\Kernel\Container;
use Simples\Kernel\Wrapper;
use Simples\Route\Match;
use Throwable;

/**
 * Class Handler
 * @package Simples\Http\Kernel
 */
class Handler extends Response
{
    /**
     * Handler constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Check if content is a instance of Response
     *
     * @param $content
     * @return bool
     */
    private function isResponse($content)
    {
        // test if the content is an instance of Response
        return $content instanceof Response;
    }

    /**
     * Apply the middleware's to Match
     *
     * @param Request $request
     * @param Match $match
     * @param array $middlewares ([])
     * @return ResponseInterface
     * @throws SimplesRunTimeError
     */
    public function apply(Request $request, Match $match, array $middlewares = [])
    {
        // get the pipe of route selected by router
        $pipe = $match->getOption('pipe');

        // check if pipe is valid
        if (!is_array($pipe)) {
            // early return
            return $this->resolve($request, $match);
        }
        // filter the middlewares using the pipe of match
        $piping = array_filter($middlewares, function ($middleWare) use ($pipe) {
            // check if alias of middleware is in pipe
            return in_array($middleWare->alias(), $pipe);
        });

        // check if exists valid middlewares
        if (!count($piping)) {
            // early return
            return $this->resolve($request, $match);
        }

        // inject the handler into the HttpResponse middleware
        $that = $this;
        $piping[] = new HttpResponse(function () use ($that, $request, $match) {
            // resolve the request using the match
            return $that->resolve($request, $match);
        });

        // create a delegate with the middlewares
        $delegate = new Delegate($piping);

        // start the pipe
        return $delegate->process($request);
    }

    /**
     * @param Request $request
     * @param Match $match
     * @return Response
     * @throws SimplesRunTimeError
     */
    final private function resolve(Request $request, Match $match)
    {
        /** @var mixed $callback */
        $callback = $match->getCallback();

        if (!$callback) {
            return $this->parse($match, null);
        }

        if ($callback instanceof Throwable) {
            return $this->parse($match, $callback);
        }

        if (gettype($callback) !== TYPE_OBJECT) {
            return $this->controller($request, $match);
        }

        return $this->call($match, $callback->bindTo($this), $this->parameters($match));
    }

    /**
     * @param Request $request
     * @param Match $match
     * @return Response
     * @throws SimplesRunTimeError
     */
    private function controller(Request $request, Match $match)
    {
        $callable = $this->getCallable($match);

        if (isset($callable['class'])) {
            $class = $callable['class'];

            /** @var \Simples\Http\Controller $controller */
            $controller = Container::instance()->make($class);
            if (!($controller instanceof Controller)) {
                throw new SimplesRunTimeError("The class must be a instance of Controller, `{$class}` given");
            }
            $controller->boot($request, $this, $match);

            $method = $callable['method'] ?? null;
            /** @noinspection PhpParamsInspection */
            if (!method_exists($controller, $method) && is_callable($controller)) {
                $method = '__invoke';
            }
            if ($method) {
                $parameters = $this->parameters($match, $controller, $method);
                return $this->call($match, [$controller, $method], $parameters);
            }
        }

        return $this->parse($match, null);
    }

    /**
     * @param Match $match
     * @return array
     */
    private function getCallable(Match $match): array
    {
        $callback = $match->getCallback();

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
                $method = camelize(substr($match->getUri(), 1, -1), false);
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
     * @param Match $match
     * @param $instance (null)
     * @param $method (null)
     * @return array
     * @throws SimplesRunTimeError
     */
    private function parameters(Match $match, $instance = null, $method = null)
    {
        $data = is_array($match->getParameters()) ? $match->getParameters() : [];
        $options = $match->getOptions();

        /** @noinspection PhpAssignmentInConditionInspection */
        if ($parameters = off($options, 'parameters')) {
            if (is_callable($parameters)) {
                $parameters = $parameters($data);
            }
            $data = array_merge_recursive($data, $parameters);
        }

        $labels = off($options, 'labels', true);

        if ($instance && $method) {
            return Container::instance()->resolveMethodParameters($instance, $method, $data, $labels);
        }
        return Container::instance()->resolveFunctionParameters($match->getCallback(), $data, $labels);
    }

    /**
     * @param Match $match
     * @param callable $callback
     * @param array $parameters
     * @return Response
     */
    private function call(Match $match, $callback, $parameters)
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

        return $this->parse($match, $result);
    }

    /**
     * @param Match $match
     * @param Response|Throwable $content
     * @return Response
     */
    private function parse(Match $match, $content): Response
    {
        $output = [];
        if (env('TEST_MODE')) {
            $output = Wrapper::messages();
        }

        if ($this->isResponse($content)) {
            /** @var Response $content */
            return $content->meta('output', $output);
        }

        $status = Kernel::config('app.status.success');
        if (empty($match->getPath()) || is_null($content)) {
            $status = Kernel::config('app.status.notFound');
            if (is_null($content)) {
                $status = Kernel::config('app.status.notImplemented');
            }
        }

        $meta = [
            'output' => $output
        ];
        if ($content instanceof Throwable) {
            $status = Kernel::config('app.status.fail');
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
