<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\EventDispatcherPass;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\MethodCallExistsFor;

class EventDispatcherPassTest extends TestCase
{

    /**
     * @var EventDispatcherPass
     */
    private $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new EventDispatcherPass();
    }

    protected function tearDown(): void
    {
        unset($this->compilerPass);
    }

    /**
     * @test
     */
    public function listener_should_be_wired_correctly(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $container->register('bar')
            ->setClass(MockListener::class)
            ->addTag(
                'test_listener',
                [
                    'event'  => 'test_event',
                    'method' => 'onAction',
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'addListener',
                [
                    'test_event',
                    [new ServiceClosureArgument(new Reference('bar')), 'onAction'],
                    0,
                ],
                1
            )
        );
    }

    /**
     * @test
     */
    public function subscriber_should_be_wired_correctly(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $container->register('bar')
            ->setClass(MockSubscriber::class)
            ->addTag(
                'test_subscriber'
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'addListener',
                [
                    'test_event',
                    [new ServiceClosureArgument(new Reference('bar')), 'onAction'],
                    0,
                ],
                1
            )
        );
    }

    /**
     * @test
     */
    public function listener_tag_must_be_provided(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid/missing tag option "listener_tag" on service "foo"');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function subscriber_tag_must_be_provided(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag' => 'test_listener',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid/missing tag option "subscriber_tag" on service "foo"');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function listener_and_subscriber_tag_must_not_be_same(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_listener',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Values for listener_tag and subscriber_tag are the same on service foo');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function same_tag_can_not_be_provided_by_multiple_services(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $container->register('baz')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tag test_listener already used by foo');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function processing_is_idempotent(): void {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass(EventDispatcher::class)
            ->addTag(
                'event_dispatcher',
                [
                    'listener_tag'   => 'test_listener',
                    'subscriber_tag' => 'test_subscriber',
                ]
            );

        $container->register('bar')
            ->setClass(MockListener::class)
            ->addTag(
                'test_listener',
                [
                    'event'  => 'test_event',
                    'method' => 'onAction',
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'addListener',
                [
                    'test_event',
                    [new ServiceClosureArgument(new Reference('bar')), 'onAction'],
                    0,
                ],
                1
            )
        );

        $methodCalls = $container->findDefinition('foo')->getMethodCalls();

        $this->compilerPass->process($container);

        $this->assertSame($methodCalls, $container->findDefinition('foo')->getMethodCalls());
    }
}

class MockListener
{

    public function onAction(): void
    {

    }
}

class MockSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return ['test_event' => 'onAction'];
    }

    public function onAction(): void
    {

    }
}
