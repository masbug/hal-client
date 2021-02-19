<?php

namespace Jsor\HalClient;

use GuzzleHttp\Psr7 as GuzzlePsr7;
use Jsor\HalClient\HttpClient\{HttpClientInterface, Guzzle6HttpClient, Guzzle7HttpClient};
use Psr\Http\Message\{RequestInterface, ResponseInterface, UriInterface};
use Jsor\HalClient\Internal\HalResourceFactory;

final class HalClient implements HalClientInterface
{
    private HttpClientInterface $httpClient;
    private HalResourceFactory $factory;
    private RequestInterface $defaultRequest;

    /** @var string[] $validContentTypes */
    private static array $validContentTypes = [
        'application/hal+json',
        'application/json',
        'application/vnd.error+json'
    ];

    /**
     * HalClient constructor.
     * @param UriInterface|string      $rootUrl
     * @param HttpClientInterface|null $httpClient
     */
    public function __construct($rootUrl, HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? self::createDefaultHttpClient();

        $this->factory = new HalResourceFactory(self::$validContentTypes);

        $this->defaultRequest = new GuzzlePsr7\Request('GET', $rootUrl, [
            'User-Agent' => get_class($this),
            'Accept'     => implode(', ', self::$validContentTypes)
        ]);
    }

    public function __clone()
    {
        $this->httpClient     = clone $this->httpClient;
        $this->defaultRequest = clone $this->defaultRequest;
    }

    /**
     * @return \Psr\Http\Message\UriInterface
     */
    public function getRootUrl() : UriInterface
    {
        return $this->defaultRequest->getUri();
    }

    /**
     * @param UriInterface|string $rootUrl
     * @return $this
     */
    public function withRootUrl($rootUrl) : self
    {
        $instance = clone $this;

        $instance->defaultRequest = $instance->defaultRequest->withUri(
            GuzzlePsr7\uri_for($rootUrl)
        );

        return $instance;
    }

    /** @return string[] */
    public function getHeader(string $name) : array
    {
        return $this->defaultRequest->getHeader($name);
    }

    /** @param string|string[] $value */
    public function withHeader(string $name, $value) : self
    {
        $instance = clone $this;

        $instance->defaultRequest = $instance->defaultRequest->withHeader(
            $name,
            $value
        );

        return $instance;
    }

    /**
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     * @return HalResource|ResponseInterface
     * */
    public function root(array $options = [])
    {
        return $this->request('GET', '', $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     *
     * @return HalResource|ResponseInterface
     * */
    public function get($uri, array $options = [])
    {
        return $this->request('GET', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     *
     * @return HalResource|ResponseInterface
     * */
    public function post($uri, array $options = [])
    {
        return $this->request('POST', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     *
     * @return HalResource|ResponseInterface
     * */
    public function put($uri, array $options = [])
    {
        return $this->request('PUT', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     *
     * @return HalResource|ResponseInterface
     * */
    public function delete($uri, array $options = [])
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * @param string|UriInterface $uri
     * @param array{version?:string, return_raw_response?:bool, headers?:array<string, string|string[]>, query?:string|array<string, int|string|string[]>, body?:string|array<mixed>} $options
     *
     * @return HalResource|ResponseInterface
     * */
    public function request(
        string $method,
        $uri,
        array $options = []
    ) {
        $request = $this->createRequest($method, $uri, $options);

        try {
            $response = $this->httpClient->send($request);
        } catch (\Throwable $e) {
            throw Exception\HttpClientException::create($request, $e);
        }

        return $this->handleResponse($request, $response, $options);
    }

    /**
     * @param string              $method
     * @param UriInterface|string $uri
     * @param array               $options
     * @return RequestInterface
     */
    public function createRequest(
        string $method,
        $uri,
        array $options = []
    ) : RequestInterface {
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = clone $this->defaultRequest;

        $request = $request->withMethod($method);

        $request = $request->withUri(
            self::resolveUri($request->getUri(), $uri)
        );

        $request = $this->applyOptions($request, $options);

        return $request;
    }

    private function applyOptions(RequestInterface $request, array $options) : RequestInterface
    {
        if (isset($options['version'])) {
            $request = $request->withProtocolVersion($options['version']);
        }

        if (isset($options['query'])) {
            $request = $this->applyQuery($request, $options['query']);
        }

        if (isset($options['headers'])) {
            foreach ($options['headers'] as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if (isset($options['body'])) {
            $request = $this->applyBody($request, $options['body']);
        }

        return $request;
    }

    /** @param string|array<string, string>|array<string, string[]> $query */
    private function applyQuery(RequestInterface $request, $query) : RequestInterface
    {
        $uri = $request->getUri();

        if (!is_array($query)) {
            $query = GuzzlePsr7\parse_query($query);
        }

        $newQuery = array_merge(
            GuzzlePsr7\parse_query($uri->getQuery()),
            $query
        );

        return $request->withUri(
            $uri->withQuery(http_build_query($newQuery, '', '&'))
        );
    }

    /**
     * @param RequestInterface $request
     * @param array|string     $body
     * @return RequestInterface
     */
    private function applyBody(RequestInterface $request, $body) : RequestInterface
    {
        if (is_array($body)) {
            $body = json_encode($body);

            if (!$request->hasHeader('Content-Type')) {
                $request = $request->withHeader(
                    'Content-Type',
                    'application/json'
                );
            }
        }

        return $request->withBody(GuzzlePsr7\stream_for($body));
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array             $options
     * @return HalResource|ResponseInterface
     */
    private function handleResponse(
        RequestInterface $request,
        ResponseInterface $response,
        array $options
    ) {
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 200 && $statusCode < 300) {
            if (
                isset($options['return_raw_response']) &&
                true === $options['return_raw_response']
            ) {
                return $response;
            }

            return $this->factory->createResource($this, $request, $response);
        }

        throw Exception\BadResponseException::create(
            $request,
            $response,
            $this->factory->createResource($this, $request, $response, true)
        );
    }

    private static function createDefaultHttpClient() : HttpClientInterface
    {
        // @codeCoverageIgnoreStart
        if (!interface_exists('GuzzleHttp\ClientInterface')) {
            throw new \RuntimeException(
                'Cannot create default HttpClient because guzzlehttp/guzzle is not installed.' .
                'Install with `composer require guzzlehttp/guzzle:"^7.0"`.'
            );
        }
        // @codeCoverageIgnoreEnd

        $ghciMajorVersion = 'unknown';
        if (defined('\GuzzleHttp\ClientInterface::MAJOR_VERSION')) {
            $ghciMajorVersion = \GuzzleHttp\ClientInterface::MAJOR_VERSION;
        }

        switch ($ghciMajorVersion) {
            case '7':
                return new Guzzle7HttpClient();
            // @codeCoverageIgnoreStart
            default:
                throw new \RuntimeException(
                    sprintf(
                        'Unsupported GuzzleHttp\Client version %s.',
                        $ghciMajorVersion
                    )
                );
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param UriInterface        $base
     * @param UriInterface|string $rel
     * @return UriInterface
     */
    private static function resolveUri(UriInterface $base, $rel) : UriInterface
    {
        if (!($rel instanceof UriInterface)) {
            $rel = new GuzzlePsr7\Uri($rel);
        }

        return GuzzlePsr7\UriResolver::resolve($base, $rel);
    }
}
