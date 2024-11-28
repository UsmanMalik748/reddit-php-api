<?php

namespace Usman\Reddit\Exception;

/**
 * Class LoginErrorTest.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
class LoginErrorTest extends \PHPUnit_Framework_TestCase
{
    public function testGetters()
    {
        $error = new LoginError('foo', 'bar');

        $this->assertEquals('foo', $error->getName());
        $this->assertEquals('bar', $error->getDescription());
    }
}
