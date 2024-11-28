<?php

namespace Usman\Reddit;

use GuzzleHttp\Psr7\Response;

/**
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
class RedditTest extends \PHPUnit_Framework_TestCase
{
    const APP_ID = '123456789';
    const APP_SECRET = '987654321';

    public function testApi()
    {
        $resource = 'resource';
        $token = 'token';
        $urlParams = ['url' => 'foo'];
        $postParams = ['post' => 'bar'];
        $method = 'GET';
        $expected = ['foobar' => 'test'];
        $response = new Response(200, [], json_encode($expected));
        $url = 'http://example.com/test';

        $headers = ['Authorization' => 'Bearer '.$token, 'Content-Type' => 'application/json', 'x-li-format' => 'json'];

        $generator = $this->getMock('Usman\Reddit\Http\UrlGenerator', ['getUrl']);
        $generator->expects($this->once())->method('getUrl')->with(
            $this->equalTo('api'),
            $this->equalTo($resource),
            $this->equalTo([
                'url' => 'foo',
                'format' => 'json',
            ]))
            ->willReturn($url);

        $requestManager = $this->getMock('Usman\Reddit\Http\RequestManager', ['sendRequest']);
        $requestManager->expects($this->once())->method('sendRequest')->with(
                $this->equalTo($method),
                $this->equalTo($url),
                $this->equalTo($headers),
                $this->equalTo(json_encode($postParams)))
            ->willReturn($response);

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['getAccessToken', 'getUrlGenerator', 'getRequestManager'], [self::APP_ID, self::APP_SECRET]);

        $reddit->expects($this->once())->method('getAccessToken')->willReturn($token);
        $reddit->expects($this->once())->method('getUrlGenerator')->willReturn($generator);
        $reddit->expects($this->once())->method('getRequestManager')->willReturn($requestManager);

        $result = $reddit->api($method, $resource, ['query' => $urlParams, 'json' => $postParams]);
        $this->assertEquals($expected, $result);
    }

    public function testIsAuthenticated()
    {
        $reddit = $this->getMock('Usman\Reddit\Reddit', ['getAccessToken'], [self::APP_ID, self::APP_SECRET]);
        $reddit->expects($this->once())->method('getAccessToken')->willReturn(null);
        $this->assertFalse($reddit->isAuthenticated());

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['api', 'getAccessToken'], [self::APP_ID, self::APP_SECRET]);
        $reddit->expects($this->once())->method('getAccessToken')->willReturn('token');
        $reddit->expects($this->once())->method('api')->willReturn(['id' => 4711]);
        $this->assertTrue($reddit->isAuthenticated());

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['api', 'getAccessToken'], [self::APP_ID, self::APP_SECRET]);
        $reddit->expects($this->once())->method('getAccessToken')->willReturn('token');
        $reddit->expects($this->once())->method('api')->willReturn(['foobar' => 4711]);
        $this->assertFalse($reddit->isAuthenticated());
    }

    /**
     * Test a call to getAccessToken when there is no token.
     */
    public function testAccessTokenAccessors()
    {
        $token = 'token';

        $auth = $this->getMock('Usman\Reddit\Authenticator', ['fetchNewAccessToken'], [], '', false);
        $auth->expects($this->once())->method('fetchNewAccessToken')->will($this->returnValue($token));

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['getAuthenticator'], [], '', false);
        $reddit->expects($this->once())->method('getAuthenticator')->willReturn($auth);

        // Make sure we go to the authenticator only once
        $this->assertEquals($token, $reddit->getAccessToken());
        $this->assertEquals($token, $reddit->getAccessToken());
    }

    public function testGeneratorAccessors()
    {
        $get = new \ReflectionMethod('Usman\Reddit\Reddit', 'getUrlGenerator');
        $get->setAccessible(true);
        $reddit = new Reddit(self::APP_ID, self::APP_SECRET);

        // test default
        $this->assertInstanceOf('Usman\Reddit\Http\UrlGenerator', $get->invoke($reddit));

        $object = $this->getMock('Usman\Reddit\Http\UrlGenerator');
        $reddit->setUrlGenerator($object);
        $this->assertEquals($object, $get->invoke($reddit));
    }

    public function testHasError()
    {
        $reddit = new Reddit(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        $this->assertFalse($reddit->hasError());

        $_GET['error'] = 'foobar';
        $this->assertTrue($reddit->hasError());
    }

    public function testGetError()
    {
        $reddit = new Reddit(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        unset($_GET['error_description']);

        $this->assertNull($reddit->getError());

        $_GET['error'] = 'foo';
        $_GET['error_description'] = 'bar';

        $this->assertEquals('foo', $reddit->getError()->getName());
        $this->assertEquals('bar', $reddit->getError()->getDescription());
    }

    public function testGetErrorWithMissingDescription()
    {
        $reddit = new Reddit(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        unset($_GET['error_description']);

        $_GET['error'] = 'foo';

        $this->assertEquals('foo', $reddit->getError()->getName());
        $this->assertNull($reddit->getError()->getDescription());
    }

    public function testFormatAccessors()
    {
        $get = new \ReflectionMethod('Usman\Reddit\Reddit', 'getFormat');
        $get->setAccessible(true);
        $reddit = new Reddit(self::APP_ID, self::APP_SECRET);

        //test default
        $this->assertEquals('json', $get->invoke($reddit));

        $format = 'foo';
        $reddit->setFormat($format);
        $this->assertEquals($format, $get->invoke($reddit));
    }

    public function testLoginUrl()
    {
        $currentUrl = 'currentUrl';
        $loginUrl = 'result';

        $generator = $this->getMock('Usman\Reddit\Http\UrlGenerator', ['getCurrentUrl']);
        $generator->expects($this->once())->method('getCurrentUrl')->willReturn($currentUrl);

        $auth = $this->getMock('Usman\Reddit\Authenticator', ['getLoginUrl'], [], '', false);
        $auth->expects($this->once())->method('getLoginUrl')
            ->with($generator, ['redirect_uri' => $currentUrl])
            ->will($this->returnValue($loginUrl));

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['getAuthenticator', 'getUrlGenerator'], [], '', false);
        $reddit->expects($this->once())->method('getAuthenticator')->willReturn($auth);
        $reddit->expects($this->once())->method('getUrlGenerator')->willReturn($generator);

        $reddit->getLoginUrl();
    }

    public function testLoginUrlWithParameter()
    {
        $loginUrl = 'result';
        $otherUrl = 'otherUrl';

        $generator = $this->getMock('Usman\Reddit\Http\UrlGenerator');

        $auth = $this->getMock('Usman\Reddit\Authenticator', ['getLoginUrl'], [], '', false);
        $auth->expects($this->once())->method('getLoginUrl')
            ->with($generator, ['redirect_uri' => $otherUrl])
            ->will($this->returnValue($loginUrl));

        $reddit = $this->getMock('Usman\Reddit\Reddit', ['getAuthenticator', 'getUrlGenerator'], [], '', false);
        $reddit->expects($this->once())->method('getAuthenticator')->willReturn($auth);
        $reddit->expects($this->once())->method('getUrlGenerator')->willReturn($generator);

        $reddit->getLoginUrl(['redirect_uri' => $otherUrl]);
    }
}
