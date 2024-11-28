# Reddit API client in PHP


A PHP library to handle authentication and communication with Reddit API. The library/SDK helps you to get an access
token and when authenticated it helps you to send API requests. You will not get *everything* for free though... You
have to read the [Reddit documentation][api-doc-core] to understand how you should query the API. 

To get an overview what this library actually is doing for you. Take a look at the authentication page from
the [API docs][api-doc-authentication].

## Features

Here is a list of features that might convince you to choose this Reddit client over some of our competitors'.

* Flexible and easy to extend
* Developed with modern PHP standards
* Not developed for a specific framework. 
* Handles the authentication process
* Respects the CSRF protection

## Installation

**TL;DR**
```bash
composer require php-http/curl-client guzzlehttp/psr7 php-http/message usman/reddit-php-api
```

This library does not have a dependency on Guzzle or any other library that sends HTTP requests. We use the awesome 
HTTPlug to achieve the decoupling. We want you to choose what library to use for sending HTTP requests. Consult this list 
of packages that support [php-http/client-implementation](https://packagist.org/providers/php-http/client-implementation) 
find clients to use. For more information about virtual packages please refer to 
[HTTPlug](http://docs.php-http.org/en/latest/httplug/users.html). Example:

```bash
composer require php-http/guzzle6-adapter
```

You do also need to install a PSR-7 implementation and a factory to create PSR-7 messages (PSR-17 whenever that is 
released). You could use Guzzles PSR-7 implementation and factories from php-http:

```bash
composer require guzzlehttp/psr7 php-http/message 
```

Now you may install the library by running the following:

```bash
composer require usman/reddit-php-api
```
## Usage

In order to use this API client (or any other Reddit clients) you have to [register your application][register-app]
with Reddit to receive an API key. Once you've registered your Reddit app, you will be provided with
an *API Key* and *Secret Key*.

### Reddit login

This example below is showing how to login with Reddit.

```php 
<?php

/**
 * This demonstrates how to authenticate with Reddit and send api requests
 */

/*
 * First you need to make sure you've used composers auto load. You have is probably 
 * already done this before. You usually don't bother..
 */
//require_once "vendor/autoload.php";

$reddit=new Usman\Reddit\Reddit('client_id', 'client_secret');

if ($reddit->isAuthenticated()) {
    //we know that the user is authenticated now. Start query the API
    $user=$reddit->get('v2/userinfo');
    echo "Welcome ".$user['name'];

    exit();
} elseif ($reddit->hasError()) {
    echo "User canceled the login.";
    exit();
}

//if not authenticated
$url = $reddit->getLoginUrl();
echo "<a href='$url'>Login with Reddit</a>";

```

### How to post on Reddit wall

The example below shows how you can post on a users wall. The access token is fetched from the database. 

```php
$reddit=new Usman\Reddit\Reddit('app_id', 'app_secret');
$reddit->setAccessToken('access_token_from_db');

$options = array('json'=>
    array(
        'comment' => 'Im testing Usman Reddit Api! https://github.com/usman/Reddit-API',
        'visibility' => array(
            'code' => 'anyone'
        )
    )
);

$result = $reddit->post('v2/people/~/shares', $options);

var_dump($result);

// Prints: 
// array (size=2)
//   'updateKey' => string 'UPDATE-01234567-0123456789012345678' (length=35)
//   'updateUrl' => string 'https://www.Reddit.com/updates?discuss=&scope=01234567&stype=M&topic=0123456789012345678&type=U&a=mVKU' (length=104)

```

You may of course do the same in xml. Use the following options array.
```php
$options = array(
'format' => 'xml',
'body' => '<share>
 <comment>Im testing Usman Reddit client! https://github.com/usmanmalik/Reddit-API</comment>
 <visibility>
   <code>anyone</code>
 </visibility>
</share>');
```

## Configuration

### The api options

The third parameter of `Reddit::api` is an array with options. Below is a table of array keys that you may use. 

| Option name | Description
| ----------- | -----------
| body | The body of a HTTP request. Put your xml string here. 
| format | Set this to 'json', 'xml' or 'simple_xml' to override the default value.
| headers | This is HTTP headers to the request
| json | This is an array with json data that will be encoded to a json string. Using this option you do need to specify a format. 
| response_data_type | To override the response format for one request 
| query | This is an array with query parameters



### Changing request format

The default format when communicating with Reddit API is json. You can let the API do `json_encode` for you. 
The following code shows you how. 

```php
$body = array(
    'comment' => 'Im testing Usman Reddit client! https://github.com/usmanmalik/Reddit-API-client',
    'visibility' => array('code' => 'anyone')
);

$reddit->post('v2/people/~/shares', array('json'=>$body));
$reddit->post('v2/people/~/shares', array('body'=>json_encode($body)));
```

When using `array('json'=>$body)` as option the format will always be `json`. You can change the request format in three ways.

```php
// By constructor argument
$reddit=new Usman\Reddit\Reddit('app_id', 'app_secret', 'xml');

// By setter
$reddit->setFormat('xml');

// Set format for just one request
$reddit->post('v2/people/~/shares', array('format'=>'xml', 'body'=>$body));
```


### Understanding response data type

The data type returned from `Reddit::api` can be configured. You may use the forth construtor argument, the
`Reddit::setResponseDataType` or as an option for `Reddit::api`

```php
// By constructor argument
$reddit=new Usman\Reddit\Reddit('app_id', 'app_secret');


$reddit->get('v2/userinfo');

```

Below is a table that specifies what the possible return data types are when you call `Reddit::api`.

| Type | Description
| ------ | ------------
| array | An assosiative array. This can only be used with the `json` format.
| simple_xml | A SimpleXMLElement. See [PHP manual](http://php.net/manual/en/class.simplexmlelement.php). This can only be used with the `xml` format.
| psr7 | A PSR7 response.
| stream | A file stream.
| string | A plain old string.


### Use different Session classes

You might want to use an other storage than the default `SessionStorage`. If you are using Laravel
you are more likely to inject the `IlluminateSessionStorage`.  
```php
$reddit=new Usman\Reddit\Reddit('app_id', 'app_secret');
$reddit->setStorage(new IlluminateSessionStorage());
```

You can inject any class implementing `DataStorageInterface`. You can also inject different `UrlGenerator` classes.

### Using different scopes

If you want to define special scopes when you authenticate the user you should specify them when you are generating the 
login url. If you don't specify scopes Reddit will use the default scopes that you have configured for the app.  

```php
$scope = 'email,openid,profile,w_member_social';
//or 
$scope = array('email', 'openid', 'profile', 'w_member_social');

$url = $reddit->getLoginUrl(array('scope'=>$scope));
echo "<a href='$url'>Login with Reddit</a>";
```

[register-app]: https://www.Reddit.com/secure/developer
[Reddit-code-samples]: https://developer.Reddit.com/documents/code-samples
[api-doc-authentication]: https://developer.Reddit.com/documents/authentication
[api-doc-core]: https://developer.Reddit.com/core-concepts
