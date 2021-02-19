<?php

namespace Jsor\HalClient;

use Psr\Http\Message\{ResponseInterface, UriInterface};

interface HalClientInterface
{
    public function getRootUrl() : UriInterface;

    /** @return string[] */
    public function getHeader(string $name) : array;

    /** @param string|string[] $value */
    public function withHeader(string $name, $value) : HalClientInterface;

    /**
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     * @return HalResource|ResponseInterface
     * */
    public function root(array $options = []);

    /**
     * @param UriInterface|string $uri
     * @param array               $options
     * @return HalResource|ResponseInterface
     */
    public function get($uri, array $options = []);

    /**
     * @param UriInterface|string $uri
     * @param array               $options
     * @return HalResource|ResponseInterface
     */
    public function post($uri, array $options = []);

    /**
     * @param UriInterface|string $uri
     * @param array               $options
     * @return HalResource|ResponseInterface
     */
    public function put($uri, array $options = []);

    /**
     * @param UriInterface|string $uri
     * @param array               $options
     * @return HalResource|ResponseInterface
     */
    public function delete($uri, array $options = []);

    /**
     * @param UriInterface|string $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     * @return HalResource|ResponseInterface
     * */
    public function request(string $method, $uri, array $options = []);
}
