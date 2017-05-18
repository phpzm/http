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
     * @var array
     */
    private $origins = [];

    /**
     * CrossOriginResourceSharing constructor.
     * @param array $origins ([])
     */
    public function __construct($origins = [])
    {
        $this->origins = $origins;
    }

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
        // validate if is a valid origin
        $this->validate($request);

        // test if the method used in request is a pre-flight
        if ($this->isPreFlight($request)) {
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
        if (count($this->origins) === 0) {
            return true;
        }
        $origin = $request->getHeader($this->headerOrigin);
        if (in_array($origin, $this->origins)) {
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
        return strtolower($request->getMethod()) === 'options';
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
            ->plain('')
            ->header('Access-Control-Allow-Methods', $accessControlAllowMethods)
            ->header('Access-Control-Allow-Headers', $accessControlAllowHeaders);
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
        $origin = $request->getHeader($this->headerOrigin);
        return $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400');
    }
}
