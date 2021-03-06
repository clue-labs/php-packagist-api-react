<?php

namespace Clue\React\Packagist\Api;

use Packagist\Api\Result\Factory;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Response;

class Client
{
    private $http;
    private $resultFactory;

    public function __construct(Browser $http, Factory $resultFactory = null)
    {
        $this->http = $http;

        if (null === $resultFactory) {
            $resultFactory = new Factory();
        }

        $this->resultFactory = $resultFactory;
    }

    public function search($query, array $filters = array())
    {
        $results = array();
        $filters['q'] = $query;
        $url = $this->url('/search.json?' . http_build_query($filters));
        $that = $this;

        $fetch = function ($url) use (&$results, $that, &$fetch) {
            return $that->request($url)->then(function (Response $response) use (&$results, $that, $fetch) {
                $parsed = $that->parse((string)$response->getBody());
                $results = array_merge($results, $that->create($parsed));

                if (isset($parsed['next'])) {
                    return $fetch($parsed['next']);
                } else {
                    return $results;
                }
            });
        };

        return $fetch($url);
    }

    public function get($package)
    {
        return $this->respond(sprintf($this->url('/packages/%s.json'), $package));
    }

    public function all(array $filters = array())
    {
        $url = '/packages/list.json';
        if ($filters) {
            $url .= '?'.http_build_query($filters);
        }

        return $this->respond($this->url($url));
    }

    protected function url($url)
    {
        return 'https://packagist.org'.$url;
    }

    protected function respond($url)
    {
        $response = $this->request($url);
        $that     = $this;

        return $response->then(function (Response $response) use ($that) {
            return $that->create($that->parse((string)$response->getBody()));
        });
    }

    public function request($url)
    {
        return $this->http->get($url);
    }

    public function parse($data)
    {
        return json_decode($data, true);
    }

    public function create(array $data)
    {
        return $this->resultFactory->create($data);
    }
}
