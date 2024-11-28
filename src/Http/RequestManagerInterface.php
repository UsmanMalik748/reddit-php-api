<?php

namespace Usman\Reddit\Http;

use Usman\Reddit\Exception\RedditTransferException;
use Http\Client\HttpClient;

/**
 * A request manager builds a request.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
interface RequestManagerInterface
{
    /**
     * Send a request.
     *
     * @param string $method
     * @param string $uri
     * @param array  $headers
     * @param string $body
     * @param string $protocolVersion
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws RedditTransferException
     */
    public function sendRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1');

    /**
     * @param \Http\Client\HttpClient $httpClient
     *
     * @return RequestManager
     */
    public function setHttpClient(HttpClient $httpClient);
}
