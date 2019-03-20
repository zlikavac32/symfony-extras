<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass;

class ServiceLinkerPassTest extends TestCase
{

    /**
     * @var ServiceLinkerPass
     */
    private $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new ServiceLinkerPass();
    }

    protected function tearDown(): void
    {
        unset($this->compilerPass);
    }

    /**
     * @test
     */
    public function provider_based_linking_should_link_service(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            [new Reference('bar')],
            $container->findDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function linking_is_idempotent(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            [new Reference('bar')],
            $container->findDefinition('foo')
                ->getArguments()
        );

        $createdReference = $container->findDefinition('foo')->getArgument(0);

        $this->compilerPass->process($container);

        self::assertSame($createdReference, $container->findDefinition('foo')->getArgument(0));
    }

    /**
     * @test
     */
    public function linking_can_be_done_in_multiple_passes(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_provider_2',
                [
                    'provides' => 'test_group_2',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            [new Reference('bar')],
            $container->findDefinition('foo')
                ->getArguments()
        );

        $container->findDefinition('foo')
            ->addTag('linker', [
                'provider_tag' => 'test_provider_2',
                'provider' => 'test_group_2',
                'argument' => 1
            ]);

        $this->compilerPass->process($container);


        self::assertEquals(
            [new Reference('bar'), new Reference('baz')],
            $container->findDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function provider_based_linking_should_link_service_on_custom_argument(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                    'argument'     => '$arg',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$arg' => new Reference('bar')],
            $container->findDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function consumer_can_be_linked_with_provider(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_argument_resolver',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$arg' => new Reference('bar')],
            $container->findDefinition('baz')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function consumer_can_be_linked_with_param(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('test_param', 32);

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_argument_resolver',
                [
                    'param' => 'test_param',
                ]
            );

        $this->compilerPass->process($container);

        self::assertSame(
            ['$arg' => 32],
            $container->findDefinition('baz')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function consumer_can_be_linked_with_service(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_argument_resolver',
                [
                    'service' => 'test_service',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$arg' => new Reference('test_service')],
            $container->findDefinition('baz')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function multiple_consumers_can_be_defined_on_service(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('test_param', 32);

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver_1',
                    'argument'              => '$arg1',
                ]
            )
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver_2',
                    'argument'              => '$arg2',
                ]
            )
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver_3',
                    'argument'              => '$arg3',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_provider',
                [
                    'provides' => 'test_group',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_argument_resolver_1',
                [
                    'service' => 'test_service',
                ]
            )
            ->addTag(
                'test_argument_resolver_2',
                [
                    'param' => 'test_param',
                ]
            )
            ->addTag(
                'test_argument_resolver_3',
                [
                    'provider_tag' => 'test_provider',
                    'provider'     => 'test_group',
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$arg1' => new Reference('test_service'), '$arg2' => 32, '$arg3' => new Reference('bar')],
            $container->findDefinition('baz')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function same_argument_can_not_be_defined_twice(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            )
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Argument $arg already defined on service foo');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function direct_service_link_must_have_provider_defined(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_argument_resolver',
                    'provider'     => 'test_provider',
                ]
            );
        $container->register('bar')
            ->addTag('test_argument_resolver', [
                'provides' => 'test_group',
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No service provides test_provider (tag test_argument_resolver)');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function resolver_service_link_must_have_provider_defined(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                ]
            );

        $container->register('bar')
            ->addTag('test_provider', [
                'provides' => 'test_group',
            ]);

        $container->register('baz')
            ->addTag('test_argument_resolver', [
                'provider'     => 'test',
                'provider_tag' => 'test_provider',
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No service provides test (tag test_provider)');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function direct_service_link_must_have_provider_tag_defined(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider_tag' => 'test_argument_resolver',
                    'provider'     => 'test_provider',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No providers with tag test_argument_resolver found');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function resolver_service_link_must_have_provider_tag_defined(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                ]
            );

        $container->register('baz')
            ->addTag('test_argument_resolver', [
                'provider'     => 'test',
                'provider_tag' => 'test_provider',
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No providers with tag test_provider found');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function two_providers_must_not_provide_same_group(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'provider'     => 'test_group',
                    'provider_tag' => 'test_provider',
                ]
            );

        $container->register('bar')
            ->addTag('test_provider', [
                'provides' => 'test_group',
            ]);

        $container->register('baz')
            ->addTag('test_provider', [
                'provides' => 'test_group',
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Another service (bar) already provides test_group (tag test_provider)');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function only_one_source_can_be_defined_on_consumer(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'linker',
                [
                    'argument_resolver_tag' => 'test_argument_resolver',
                    'argument'              => '$arg',
                ]
            );

        $container->register('baz')
            ->addTag(
                'test_argument_resolver',
                [
                    'service' => 'test_service',
                    'param'   => 'test_param',
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Expected only one of [provider, param, service] to be of type string on service baz for tag test_argument_resolver');

        $this->compilerPass->process($container);
    }
}
