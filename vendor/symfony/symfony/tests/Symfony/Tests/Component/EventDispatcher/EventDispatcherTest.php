<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\EventDispatcher;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventDispatcherTest extends \PHPUnit_Framework_TestCase
{
    /* Some pseudo events */
    const preFoo = 'pre.foo';
    const postFoo = 'post.foo';
    const preBar = 'pre.bar';
    const postBar = 'post.bar';

    private $dispatcher;

    private $listener;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->listener = new TestEventListener();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->listener = null;
    }

    public function testInitialState()
    {
        $this->assertEquals(array(), $this->dispatcher->getListeners());
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddListener()
    {
        $this->dispatcher->addListener('pre.foo', array($this->listener, 'preFoo'));
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'));
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->assertEquals(1, count($this->dispatcher->getListeners(self::preFoo)));
        $this->assertEquals(1, count($this->dispatcher->getListeners(self::postFoo)));
        $this->assertEquals(2, count($this->dispatcher->getListeners()));
    }

    public function testGetListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener1->name = '1';
        $listener2->name = '2';
        $listener3->name = '3';

        $this->dispatcher->addListener('pre.foo', array($listener1, 'preFoo'), -10);
        $this->dispatcher->addListener('pre.foo', array($listener2, 'preFoo'), 10);
        $this->dispatcher->addListener('pre.foo', array($listener3, 'preFoo'));

        $expected = array(
            array($listener2, 'preFoo'),
            array($listener3, 'preFoo'),
            array($listener1, 'preFoo'),
        );

        $this->assertSame($expected, $this->dispatcher->getListeners('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();
        $listener4 = new TestEventListener();
        $listener5 = new TestEventListener();
        $listener6 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->addListener('post.foo', $listener4, -10);
        $this->dispatcher->addListener('post.foo', $listener5);
        $this->dispatcher->addListener('post.foo', $listener6, 10);

        $expected = array(
            'pre.foo'  => array($listener3, $listener2, $listener1),
            'post.foo' => array($listener6, $listener5, $listener4),
        );

        $this->assertSame($expected, $this->dispatcher->getListeners());
    }

    public function testDispatch()
    {
        $this->dispatcher->addListener('pre.foo', array($this->listener, 'preFoo'));
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'));
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertTrue($this->listener->preFooInvoked);
        $this->assertFalse($this->listener->postFooInvoked);
    }

    public function testDispatchForClosure()
    {
        $invoked = 0;
        $listener = function () use (&$invoked) {
            $invoked++;
        };
        $this->dispatcher->addListener('pre.foo', $listener);
        $this->dispatcher->addListener('post.foo', $listener);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(1, $invoked);
    }

    public function testStopEventPropagation()
    {
        $otherListener = new TestEventListener();

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->listener to be called first
        $this->dispatcher->addListener('post.foo', array($this->listener, 'postFoo'), 10);
        $this->dispatcher->addListener('post.foo', array($otherListener, 'preFoo'));
        $this->dispatcher->dispatch(self::postFoo);
        $this->assertTrue($this->listener->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority()
    {
        $invoked = array();
        $listener1 = function () use (&$invoked) {
            $invoked[] = '1';
        };
        $listener2 = function () use (&$invoked) {
            $invoked[] = '2';
        };
        $listener3 = function () use (&$invoked) {
            $invoked[] = '3';
        };
        $this->dispatcher->addListener('pre.foo', $listener1, -10);
        $this->dispatcher->addListener('pre.foo', $listener2);
        $this->dispatcher->addListener('pre.foo', $listener3, 10);
        $this->dispatcher->dispatch(self::preFoo);
        $this->assertEquals(array('3', '2', '1'), $invoked);
    }

    public function testRemoveListener()
    {
        $this->dispatcher->addListener('pre.bar', $this->listener);
        $this->assertTrue($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('pre.bar', $this->listener);
        $this->assertFalse($this->dispatcher->hasListeners(self::preBar));
        $this->dispatcher->removeListener('notExists', $this->listener);
    }

    public function testAddSubscriber()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testAddSubscriberWithPriorities()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);

        $listeners = $this->dispatcher->getListeners('pre.foo');
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertEquals(2, count($listeners));
        $this->assertInstanceOf('Symfony\Tests\Component\EventDispatcher\TestEventSubscriberWithPriorities', $listeners[0][0]);
    }

    public function testRemoveSubscriber()
    {
        $eventSubscriber = new TestEventSubscriber();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->assertTrue($this->dispatcher->hasListeners(self::postFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
        $this->assertFalse($this->dispatcher->hasListeners(self::postFoo));
    }

    public function testRemoveSubscriberWithPriorities()
    {
        $eventSubscriber = new TestEventSubscriberWithPriorities();
        $this->dispatcher->addSubscriber($eventSubscriber);
        $this->assertTrue($this->dispatcher->hasListeners(self::preFoo));
        $this->dispatcher->removeSubscriber($eventSubscriber);
        $this->assertFalse($this->dispatcher->hasListeners(self::preFoo));
    }
}

class TestEventListener
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo(Event $e)
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(Event $e)
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('pre.foo' => 'preFoo', 'post.foo' => 'postFoo');
    }
}

class TestEventSubscriberWithPriorities implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'pre.foo' => array('preFoo', 10),
            'post.foo' => array('postFoo'),
            );
    }
}
