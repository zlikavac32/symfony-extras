<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\EventDispatcherPass;

require_once __DIR__ . '/../vendor/autoload.php';

class FooListener
{

    public function onFoo(): void
    {
        echo VarDumper::dump(__METHOD__);
    }
}

class FooSubscriber implements EventSubscriberInterface
{

    public function fooAction(): void
    {
        echo VarDumper::dump(__METHOD__);
    }

    public static function getSubscribedEvents()
    {
        return ['foo_event' => 'fooAction'];
    }
}

$container = new ContainerBuilder();
$container->addCompilerPass(new EventDispatcherPass());

/**
 * Registers service as event dispatcher. Tag to be used for listeners is demo_listener
 * and for subscribers is demo_subscriber.
 */
$container->register(EventDispatcher::class, EventDispatcher::class)
    ->setPublic(true)
    ->addTag(
        'event_dispatcher',
        [
            'listener_tag'   => 'demo_listener',
            'subscriber_tag' => 'demo_subscriber',
        ]
    );

$container->register(FooListener::class, FooListener::class)
    ->addTag(
        'demo_listener',
        [
            'event'    => 'foo_event',
            'method'   => 'onFoo',
            'priority' => -32,
        ]
    );

$container->register(FooSubscriber::class, FooSubscriber::class)
    ->addTag('demo_subscriber', []);

$container->compile();

$dispatcher = $container->get(EventDispatcher::class);
assert($dispatcher instanceof EventDispatcherInterface);

$dispatcher->dispatch('foo_event');

/*
Output:

"FooSubscriber::fooAction"
"FooListener::onFoo"
 */
