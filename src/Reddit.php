<?php

namespace Usman\Reddit;

use Usman\Reddit\Exception\LoginError;
use Usman\Reddit\Http\GlobalVariableGetter;
use Usman\Reddit\Http\RequestManager;
use Usman\Reddit\Http\ResponseConverter;
use Usman\Reddit\Http\UrlGenerator;
use Usman\Reddit\Http\UrlGeneratorInterface;
use Usman\Reddit\Storage\DataStorageInterface;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Reddit lets you talk to Reddit api.
 *
 * When a new user arrives and want to authenticate here is whats happens:
 * 1. You redirect him to whatever url getLoginUrl() returns.
 * 2. The user logs in on www.Reddit.com and authorize your application.
 * 3. The user returns to your site with a *code* in the the $_REQUEST.
 * 4. You call isAuthenticated() or getAccessToken()
 * 5. If we don't have an access token (only a *code*), getAccessToken() will call fetchNewAccessToken()
 * 6. fetchNewAccessToken() gets the *code* from the $_REQUEST and calls getAccessTokenFromCode()
 * 7. getAccessTokenFromCode() makes a request to www.Reddit.com and exchanges the *code* for an access token
 * 8. When you have the access token you should store it in a database and/or query the API.
 * 9. When you make a second request to the API we have the access token in memory, so we don't go through all these
 *    authentication steps again.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
class Reddit implements RedditInterface
{
    /**
     * The OAuth access token received in exchange for a valid authorization
     * code.  null means the access token has yet to be determined.
     *
     * @var AccessToken
     */
    protected $accessToken = null;

    /**
     * The OAuth access token received in exchange for a valid authorization
     * code.  null means the access token has yet to be determined.
     *
     * @var AccessToken
     */
    protected $refreshToken = null;

    /**
     * @var string format
     */
    private $format;

    /**
     * @var string responseFormat
     */
    private $responseDataType;

    /**
     * @var ResponseInterface
     */
    private $lastResponse;

    /**
     * @var RequestManager
     */
    private $requestManager;

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;


    private $debug;
    private $enabled;
    private $interval;
    private $last_request;

    private $user_agent;

    /**
     * Constructor.
     *
     * @param string $appId
     * @param string $appSecret
     * @param string $format           'json', 'xml'
     * @param string $responseDataType 'array', 'string', 'simple_xml' 'psr7', 'stream'
     */
    public function __construct($appId, $appSecret, $format = 'json', $user_agent = 'demo_agent' ,$responseDataType = 'array')
    {
        $this->format = $format;
        $this->responseDataType = $responseDataType;

        $this->requestManager = new RequestManager();
        $this->authenticator = new Authenticator($this->requestManager, $appId, $appSecret);

        $this->debug = false;
        $this->enabled = true;
        $this->interval = 2;    
        $this->last_request = microtime(true) * 10000;
        $this->user_agent = $user_agent;
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthenticated()
    {
        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return false;
        }

        $user = $this->api('GET', '/v2/userinfo');

        return !empty($user['id']);
    }

    /**
     * {@inheritdoc}
     */
    public function api($method, $resource, array $options = [])
    {
        
        $url = $this->getUrlGenerator()->getUrl(
            'api',
            $resource,
            isset($options['query']) ? $options['query'] : []
        );

        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_CONNECTTIMEOUT] = 10;
        $options[CURLOPT_TIMEOUT] = 30;
        $options[CURLOPT_USERAGENT] = $this->user_agent;
        $options[CURLOPT_CUSTOMREQUEST] = $method;
        $options[CURLOPT_HTTPHEADER][] = "Authorization: bearer" . (string) $this->getAccessToken();
        if ($this->format) {
            $options[CURLOPT_HTTPHEADER][] = "Content-Type: application/json";
        }
        //Execution is placed in a loop in case CAPTCHA is required.
        do {
            //Prepare URL or POST parameters
            if (isset($params)) {
                if ($method == 'GET') {
                    $url .= '?' . http_build_query($options);
                } else {
                    $options[CURLOPT_POSTFIELDS] = $options;
                }
            }
            //Build cURL object

            $ch = curl_init($url);
            curl_setopt_array($ch, $options);
            //Wait on rate limiter if necessary
            $this->wait();
            //Print request URL for debug
            if ($this->debug) {
                echo $url . "\n";
            }
           
            //Send request and close connection
            $response_raw = curl_exec($ch);
            curl_close($ch);
            //Parse response
            $response = json_decode($response_raw);
            if ($json_error = json_last_error()) {
                $response = $response_raw;
            }
            if (isset($response->json->captcha)) {
                $params['iden'] = $response->json->captcha;
                $params['captcha'] = $this->getCaptchaResponse($url,$response->json->captcha);
                $needs_captcha = ($params['captcha'] === 'skip') ? false : true;
            } else {
                $needs_captcha = false;
            }
        } while ($needs_captcha);
        
        if ($this->format == 'STD') {
            $this->lastResponse = $response;
        } else {
           $this->lastResponse = json_decode($response_raw, true);
        }
        
        return $this->lastResponse;
    }

    private function getCaptchaResponse($url,$iden)
    {
        fwrite(STDERR, "\n==============================================================================\n");
        fwrite(STDERR, "CAPTCHA REQUIRED!\nPlease visit this link and type in the letters you see in the picture.\n\n");
        fwrite(STDERR, "{$url}/captcha/$iden\n");
        fwrite(STDERR, "\n(Too hard? Leave blank to request another. To skip this command, type 'skip'.)");
        fwrite(STDERR, "\n==============================================================================\n");
        $answer = readline("Response: ");
        return $answer;
    }

    /**
     * Enable the rate limiter, on by default.
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disable the rate limiter.
     * This is meant to allow you to perform requests in bursts, but me mindful of reddit's rate limits and your program's structure.
     * https://github.com/reddit/reddit/wiki/API
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Set the rate limiter to wait the specified number of seconds past the previous API call to make the next one.
     * If this time has already elapsed during execution of other parts of the program, no wait is needed.
     * @param int|float $interval Number of seconds that must elapse between each API call.
     */
    public function setInterval($interval)
    {
        $this->interval = $interval;
    }

    /**
     * Used by Phapper object to wait until another API call can be made.
     */

    public function wait()
    {
        $now = microtime(true) * 10000;
        $wait_until = $this->last_request + ($this->interval * 10000);
        if ($this->enabled && $now < $wait_until) {
            usleep(($wait_until - $now) * 100);
        }
        $this->last_request = microtime(true) * 10000;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoginUrl($options = [])
    {
        $urlGenerator = $this->getUrlGenerator();

        // Set redirect_uri to current URL if not defined
        if (!isset($options['redirect_uri'])) {
            $options['redirect_uri'] = $urlGenerator->getCurrentUrl();
        }

        return $this->getAuthenticator()->getLoginUrl($urlGenerator, $options);
    }

    /**
     * See docs for Reddit::api().
     *
     * @param string $resource
     * @param array  $options
     *
     * @return mixed
     */
    public function get($resource, array $options = [])
    {
        return $this->api('GET', $resource, $options);
    }

    /**
     * See docs for Reddit::api().
     *
     * @param string $resource
     * @param array  $options
     *
     * @return mixed
     */
    public function post($resource, array $options = [])
    {
        return $this->api('POST', $resource, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function clearStorage()
    {
        $this->getAuthenticator()->clearStorage();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasError()
    {
        return GlobalVariableGetter::has('error');
    }

    /**
     * {@inheritdoc}
     */
    public function getError()
    {
        if ($this->hasError()) {
            return new LoginError(GlobalVariableGetter::get('error'), GlobalVariableGetter::get('error_description'));
        }
    }

    /**
     * Get the default format to use when sending requests.
     *
     * @return string
     */
    protected function getFormat()
    {
        return $this->format;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormat($format)
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Get the default data type to be returned as a response.
     *
     * @return string
     */
    protected function getResponseDataType()
    {
        return $this->responseDataType;
    }

    /**
     * {@inheritdoc}
     */
    public function setResponseDataType($responseDataType)
    {
        $this->responseDataType = $responseDataType;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemporaryToken()
    {

        if ($this->accessToken === null) {
            if (null !== $response = $this->getAuthenticator()->fetchNewAccessToken($this->getUrlGenerator())) {
                $this->setAccessToken($response);
                $this->lastResponse = $response;
            }
        }
        // return the new access token or null if none found
        return $this->lastResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function getParmanentToken($refreshToken)
    {

        $response = $this->getAuthenticator()->fetchNewPermanentAccessToken($this->getUrlGenerator(),$refreshToken);
        $this->setAccessToken($response);
        $this->lastResponse = $response;
        // return the new access token or null if none found
        return $this->lastResponse;
    }

    public function getAccessToken()
    {
        if ($this->accessToken === null) {
            $response = $this->getAuthenticator()->fetchNewAccessToken($this->getUrlGenerator());
            if ($response !== null) {
                $this->accessToken = new AccessToken($response);
            }
        }

        // Return the accessToken string if set, or null otherwise
        return $this->accessToken ? $this->accessToken->getAccessToken() : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setAccessToken($accessToken)
    { 
        
        if (!($accessToken instanceof AccessToken)) {
            $accessToken = new AccessToken($accessToken);
        }
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    /**
     * @return UrlGeneratorInterface
     */
    protected function getUrlGenerator()
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new UrlGenerator();
        }

        return $this->urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(DataStorageInterface $storage)
    {
        $this->getAuthenticator()->setStorage($storage);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->getRequestManager()->setHttpClient($client);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHttpMessageFactory(MessageFactory $factory)
    {
        $this->getRequestManager()->setMessageFactory($factory);

        return $this;
    }

    /**
     * @return RequestManager
     */
    protected function getRequestManager()
    {
        return $this->requestManager;
    }

    /**
     * @return Authenticator
     */
    protected function getAuthenticator()
    {
        return $this->authenticator;
    }
}
