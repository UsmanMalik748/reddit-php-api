<?php

namespace Usman\Reddit\Storage;

use Illuminate\Support\Facades\Session;

/**
 * Class SessionStorageTest.
 *
 * @author Usman Malik <malikdevelopers81@gmail.com>
 */
class IlluminateSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Usman\Reddit\Storage\SessionStorage storage
     */
    protected $storage;

    protected $prefix = 'reddit_';

    public function setUp()
    {
        $this->storage = new IlluminateSessionStorage();
    }

    public function testSet()
    {
        Session::shouldReceive('put')->once()->with($this->prefix.'code', 'foobar');

        $this->storage->set('code', 'foobar');
    }

    /**
     * @expectedException \Usman\Reddit\Exception\InvalidArgumentException
     */
    public function testSetFail()
    {
        $this->storage->set('foobar', 'baz');
    }

    public function testGet()
    {
        $expected = 'foobar';
        Session::shouldReceive('get')->once()->with($this->prefix.'code')->andReturn($expected);
        $result = $this->storage->get('code');
        $this->assertEquals($expected, $result);

        Session::shouldReceive('get')->once()->with($this->prefix.'state')->andReturn(null);
        $result = $this->storage->get('state');
        $this->assertNull($result);
    }

    public function testClear()
    {
        Session::shouldReceive('forget')->once()->with($this->prefix.'code')->andReturn(true);
        $this->storage->clear('code');
    }

    /**
     * @expectedException \Usman\Reddit\Exception\InvalidArgumentException
     */
    public function testClearFail()
    {
        $this->storage->clear('foobar');
    }
}
