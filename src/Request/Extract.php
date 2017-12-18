<?php

namespace Simples\Http\Request;

use Simples\Error\SimplesRunTimeError;

/**
 * Trait Extract
 * @package Simples\Http\Request
 */
trait Extract
{
    /**
     * @return $this
     * @throws SimplesRunTimeError
     */
    public function fromServer()
    {
        $this->extractMethodFromServer();

        $this->extractHeadersFromServer();

        $this->extractUrlFromServer();

        $this->extractDataFromServer();

        return $this;
    }

    /**
     * @return $this
     * @throws SimplesRunTimeError
     */
    private function extractMethodFromServer()
    {
        $method = server('REQUEST_METHOD');
        $method = coalesce(get('_method'), $method);
        $method = coalesce(post('_method'), $method);
        $this->method = strtolower($method);

        return $this;
    }

    /**
     * @return $this
     */
    private function extractHeadersFromServer()
    {
        $this->headers = getallheaders();
        return $this;
    }

    /**
     * @return $this
     */
    private function extractUrlFromServer()
    {
        $self = str_replace('index.php/', '', server('PHP_SELF'));
        $uri = server('REQUEST_URI') ? explode('?', server('REQUEST_URI'))[0] : '';
        $start = '';

        if ($self !== $uri) {
            $peaces = explode('/', $self);
            array_pop($peaces);

            $start = implode('/', $peaces);
            $search = '/' . preg_quote($start, '/') . '/';
            $uri = preg_replace($search, '', $uri, 1);
        }
        $this->uri = substr($uri, -1) !== '/' ? $uri . '/' : $uri;

        $this->url = server('HTTP_HOST') ? server('HTTP_HOST') . $start : $this->url;

        return $this;
    }

    /**
     * @return $this
     * @SuppressWarnings("Superglobals")
     */
    private function extractDataFromServer()
    {
        $_PAYLOAD = (array)json_decode(file_get_contents("php://input"));
        if (!$_PAYLOAD) {
            $_PAYLOAD = [];
        }

        $this->addBodySource('GET', $_GET);
        $this->addBodySource('POST', array_merge($_POST, $_PAYLOAD));

        if ($this->strict) {
            $_GET = [];
            $_POST = [];
        }

        return $this;
    }
}