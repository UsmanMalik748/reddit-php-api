<?php

namespace Usman\Reddit;

use Usman\Reddit\Exception\RedditTransferException;
use Usman\Reddit\Exception\RedditException;
use Usman\Reddit\Http\GlobalVariableGetter;
use Usman\Reddit\Http\RedditUrlGeneratorInterface;
use Usman\Reddit\Http\RequestManager;
use Usman\Reddit\Http\RequestManagerInterface;
use Usman\Reddit\Http\ResponseConverter;
use Usman\Reddit\Storage\DataStorageInterface;
use Usman\Reddit\Storage\SessionStorage;

/**
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
class Authenticator implements AuthenticatorInterface
{
    /**
     * The application ID.
     *
     * @var string
     */
    protected $appId;

    /**
     * The application secret.
     *
     * @var string
     */
    protected $appSecret;

    /**
     * A storage to use to store data between requests.
     *
     * @var DataStorageInterface storage
     */
    private $storage;

    /**
     * @var RequestManagerInterface
     */
    private $requestManager;

    /**
     * @param RequestManagerInterface $requestManager
     * @param string                  $appId
     * @param string                  $appSecret
     */
    public function __construct(RequestManagerInterface $requestManager, $appId, $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->requestManager = $requestManager;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNewAccessToken(RedditUrlGeneratorInterface $urlGenerator)
    {
        $storage = $this->getStorage();
        $code = $this->getCode();

        if ($code === null) {
            /*
             * As a fallback, just return whatever is in the persistent
             * store, knowing nothing explicit (signed request, authorization
             *  code, etc.) was present to shadow it.
             */
            return $storage->get('access_token');
        }

        try {
            $accessToken = $this->getAccessTokenFromCode($urlGenerator, $code);
        } catch (RedditException $e) {
            // code was bogus, so everything based on it should be invalidated.
            $storage->clearAll();
            throw $e;
        }

        $storage->set('code', $code);
        $storage->set('access_token', $accessToken);

        return $accessToken;
    }

    /**
     * Retrieves an access token for the given authorization code
     * (previously generated from www.Reddit.com on behalf of
     * a specific user). The authorization code is sent to www.Reddit.com
     * and a legitimate access token is generated provided the access token
     * and the user for which it was generated all match, and the user is
     * either logged in to Reddit or has granted an offline access permission.
     *
     * @param RedditUrlGeneratorInterface $urlGenerator
     * @param string                        $code         An authorization code.
     *
     * @return AccessToken An access token exchanged for the authorization code.
     *
     * @throws RedditException
     */
    protected function getAccessTokenFromCode(RedditUrlGeneratorInterface $urlGenerator, $code)
    {
        if (empty($code)) {
            throw new RedditException('Could not get access token: The code was empty.');
        }

        $redirectUri = $this->getStorage()->get('redirect_uri');
        try {
            $url = $urlGenerator->getUrl('www', 'v1/accessToken');
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
            $body = http_build_query(
                [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $this->appId,
                    'client_secret' => $this->appSecret,
                ]
            );

            $response = ResponseConverter::convertToArray($this->getRequestManager()->sendRequest('POST', $url, $headers, $body));
        } catch (RedditTransferException $e) {
            // most likely that user very recently revoked authorization.
            // In any event, we don't have an access token, so throw an exception.
            throw new RedditException('Could not get access token: The user may have revoked the authorization response from Reddit.com was empty.', $e->getCode(), $e);
        }

        if (empty($response)) {
            throw new RedditException('Could not get access token: The response from Reddit.com was empty.');
        }

        $tokenData = array_merge(['access_token' => null, 'expires_in' => null], $response);
        $token = new AccessToken($tokenData['access_token'], $tokenData['expires_in']);

        if (!$token->hasToken()) {
            throw new RedditException('Could not get access token: The response from Reddit.com did not contain a token.');
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoginUrl(RedditUrlGeneratorInterface $urlGenerator, $options = [])
    {
        // Generate a state
        $this->establishCSRFTokenState();

        // Build request params
        $requestParams = array_merge([
            'response_type' => 'code',
            'client_id' => $this->appId,
            'state' => $this->getStorage()->get('state'),
            'redirect_uri' => null,
        ], $options);

        // Save the redirect url for later
        $this->getStorage()->set('redirect_uri', $requestParams['redirect_uri']);

        // if 'scope' is passed as an array, convert to space separated list
        $scopeParams = isset($options['scope']) ? $options['scope'] : null;
        if ($scopeParams) {
            //if scope is an array
            if (is_array($scopeParams)) {
                $requestParams['scope'] = implode(' ', $scopeParams);
            } elseif (is_string($scopeParams)) {
                //if scope is a string with ',' => make it to an array
                $requestParams['scope'] = str_replace(',', ' ', $scopeParams);
            }
        }

        return $urlGenerator->getUrl('www', 'v1/authorize', $requestParams);
    }

    /**
     * Get the authorization code from the query parameters, if it exists,
     * and otherwise return null to signal no authorization code was
     * discovered.
     *
     * @return string|null The authorization code, or null if the authorization code not exists.
     *
     * @throws RedditException on invalid CSRF tokens
     */
    protected function getCode()
    {
        $storage = $this->getStorage();

        if (!GlobalVariableGetter::has('code')) {
            return;
        }

        if ($storage->get('code') === GlobalVariableGetter::get('code')) {
            //we have already validated this code
            return;
        }

        // if stored state does not exists
        if (null === $state = $storage->get('state')) {
            throw new RedditException('Could not find a stored CSRF state token.');
        }

        // if state not exists in the request
        if (!GlobalVariableGetter::has('state')) {
            throw new RedditException('Could not find a CSRF state token in the request.');
        }

        // if state exists in session and in request and if they are not equal
        if ($state !== GlobalVariableGetter::get('state')) {
            throw new RedditException('The CSRF state token from the request does not match the stored token.');
        }

        // CSRF state has done its job, so clear it
        $storage->clear('state');

        return GlobalVariableGetter::get('code');
    }

    /**
     * Lays down a CSRF state token for this process.
     */
    protected function establishCSRFTokenState()
    {
        $storage = $this->getStorage();
        if ($storage->get('state') === null) {
            $storage->set('state', md5(uniqid(mt_rand(), true)));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearStorage()
    {
        $this->getStorage()->clearAll();

        return $this;
    }

    /**
     * @return DataStorageInterface
     */
    protected function getStorage()
    {
        if ($this->storage === null) {
            $this->storage = new SessionStorage();
        }

        return $this->storage;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(DataStorageInterface $storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * @return RequestManager
     */
    protected function getRequestManager()
    {
        return $this->requestManager;
    }
}
