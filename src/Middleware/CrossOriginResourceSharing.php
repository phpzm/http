<?php

namespace Simples\Http\Middleware;

use Simples\Http\Kernel\App;
use Simples\Kernel\Middleware;
use Simples\Http\Contract\Middleware as Contract;
use Psr\Http\Message\ServerRequestInterface;
use Simples\Http\Contract\Delegate;
use Psr\Http\Message\ResponseInterface;
use Simples\Http\Error\SimplesForbiddenError;

/**
 * Class CrossOriginResourceSharing
 * @package Simples\Http\Middleware
 */
class CrossOriginResourceSharing extends Middleware implements Contract
{
    /**
     * @var string
     */
    protected $alias = 'cors';

    /**
     * @var string
     */
    private $headerOrigin = 'Origin';

    /**
     * @var string
     */
    private $headerAccessControlRequestHeaders = 'Access-Control-Request-Headers';

    /**
     * @var string
     */
    private $headerAccessControlRequestMethod = 'Access-Control-Request-Method';

    /**
     * @var string
     */
    private $method = 'options';

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface $request
     * @param Delegate $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, Delegate $delegate): ResponseInterface
    {
        // test if the method used in request is a pre-flight
        if ($this->isPreFlight($request)) {
            // validate if is a valid origin
            $this->validate($request);
            // get the Response in container
            $response = App::response();
            // configure the header of response
            $response = $this->configureResponse($request, $response);
            // clear the body of response and add headers to request be accepted
            $response = $this->configurePreFlight($request, $response);
            // early return with a response configured to be accepted by CORS control
            return $response;
        }
        // delegate the resolution of request
        $response = $delegate->process($request);
        // write headers to configure appropriately the CORS
        $response = $this->configureResponse($request, $response);
        // response processed by this middleware
        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     * @throws SimplesForbiddenError
     */
    protected function validate(ServerRequestInterface $request): bool
    {
        $origins = config('cors.origins');
        // test if the settings have hosts to filter
        if (count($origins) === 0) {
            return true;
        }
        // test if host sent by header is valid
        $origin = $request->getHeader($this->headerOrigin);
        if (in_array($origin, $origins)) {
            return true;
        }
        throw new SimplesForbiddenError("The origin `{$origin}` is not allowed");
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isPreFlight(ServerRequestInterface $request)
    {
        return strtolower($request->getMethod()) === $this->method;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function configurePreFlight(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $accessControlAllowMethods = $request->getHeader($this->headerAccessControlRequestMethod);
        $accessControlAllowHeaders = $request->getHeader($this->headerAccessControlRequestHeaders);
        return $response
            ->withHeader('Access-Control-Allow-Methods', $accessControlAllowMethods)
            ->withHeader('Access-Control-Allow-Headers', $accessControlAllowHeaders);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function configureResponse(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $origin = '*';
        $origins = config('cors.origins');
        if (is_array($origins) && count($origins)) {
            $origin = $request->getHeader($this->headerOrigin);
        }
        $response = $response->withHeader('Access-Control-Allow-Origin', $origin);

        $exposes = config('cors.exposes');
        if (is_array($exposes) && count($exposes)) {
            $response = $response->withHeader('Access-Control-Expose-Headers', implode(',', $exposes));
        }

        return $response
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}
