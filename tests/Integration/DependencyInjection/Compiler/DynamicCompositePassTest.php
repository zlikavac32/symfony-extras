<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use Ds\Map;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicComposite\CompositeMethodArgumentResolver;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicCompositePass;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\MethodCallExistsFor;

class DynamicCompositePassTest extends TestCase
{

    /**
     * @var DynamicCompositePass
     */
    private $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new DynamicCompositePass();
    }

    protected function tearDown(): void
    {
        unset($this->compilerPass);
    }

    /**
     * @test
     */
    public function first_argument_in_method_can_be_linked(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'    => 'test_composite',
                    'method' => 'compositeMethod',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'compositeMethod', [new Reference('bar')], 1
            )
        );
    }

    /**
     * @test
     */
    public function named_argument_in_method_can_be_linked(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'      => 'test_composite',
                    'method'   => 'compositeMethod',
                    'argument' => '$arg',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'compositeMethod', ['$arg' => new Reference('bar')], 1
            )
        );
    }

    /**
     * @test
     */
    public function first_argument_in_constructor_can_be_linked(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag' => 'test_composite',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->compilerPass->process($container);

        self::assertEquals(
            [[new Reference('bar')]],
            $container->getDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function named_argument_in_constructor_can_be_linked(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'      => 'test_composite',
                    'argument' => '$arg',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$arg' => [new Reference('bar')]],
            $container->getDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function method_call_with_custom_method_argument_resolver_can_be_linked(): void
    {
        $this->compilerPass = new DynamicCompositePass(
            'dynamic_composite', new Map(
                [
                    'test_composite' => new MockMethodResolver(0, 'custom_value', 'foo'),
                ]
            )
        );

        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'      => 'test_composite',
                    'method'   => 'compositeMethod',
                    'argument' => 1,
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_composite',
                [
                    'custom_value' => 32,
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'compositeMethod', [32, new Reference('bar')], 1
            )
        );
    }

    /**
     * @test
     */
    public function service_priority_is_respected_for_constructor(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'      => 'test_composite',
                    'argument' => '$foo',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_composite',
                [
                    'priority' => 64,
                ]
            );
        $container->register('baz')
            ->addTag(
                'test_composite',
                [
                    'priority' => 32,
                ]
            );

        $this->compilerPass->process($container);

        self::assertEquals(
            ['$foo' => [new Reference('baz'), new Reference('bar')]],
            $container->getDefinition('foo')
                ->getArguments()
        );
    }

    /**
     * @test
     */
    public function service_priority_is_respected_for_method_call(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'    => 'test_composite',
                    'method' => 'compositeMethod',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_composite',
                [
                    'priority' => 64,
                ]
            );
        $container->register('baz')
            ->addTag(
                'test_composite',
                [
                    'priority' => 32,
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor('foo', 'compositeMethod', [new Reference('baz')], 1)
        );
        self::assertThat(
            $container,
            new MethodCallExistsFor('foo', 'compositeMethod', [new Reference('bar')], 2)
        );
    }

    /**
     * @test
     */
    public function non_prioritized_service_has_access_to_priority_value(): void
    {
        $this->compilerPass = new DynamicCompositePass(
            'dynamic_composite', new Map(
                [
                    'test_composite' => new MockMethodResolver(1, 'priority', 'foo'),
                ]
            )
        );

        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'         => 'test_composite',
                    'method'      => 'compositeMethod',
                    'prioritized' => false,
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_composite',
                [
                    'priority' => 32,
                ]
            );
        $container->register('baz')
            ->addTag(
                'test_composite',
                [
                    'priority' => 64,
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'compositeMethod', [new Reference('bar'), 32], 1
            )
        );
        self::assertThat(
            $container,
            new MethodCallExistsFor(
                'foo', 'compositeMethod', [new Reference('baz'), 64], 2
            )
        );
    }

    /**
     * @test
     */
    public function resolved_arguments_and_service_reference_must_not_have_conflicting_key(): void
    {
        $this->compilerPass = new DynamicCompositePass(
            'dynamic_composite', new Map(
                [
                    'test_composite' => new MockMethodResolver(0, 'custom_value', 'foo'),
                ]
            )
        );

        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'    => 'test_composite',
                    'method' => 'compositeMethod',
                ]
            );

        $container->register('bar')
            ->addTag(
                'test_composite',
                [
                    'custom_value' => 32,
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Argument 0 already defined by the resolver');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function exception_is_thrown_when_multiple_services_use_same_tag(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'    => 'test_composite',
                    'method' => 'compositeMethod',
                ]
            );

        $container->register('baz')
            ->addTag(
                'dynamic_composite',
                [
                    'tag'    => 'test_composite',
                    'method' => 'compositeMethod',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tag test_composite already provided by service foo');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function linking_composite_elements_is_idempotent(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'dynamic_composite',
                [
                    'tag' => 'test_composite',
                ]
            );

        $container->register('bar')
            ->addTag('test_composite');

        $this->compilerPass->process($container);

        self::assertEquals(
            [[new Reference('bar')]],
            $container->getDefinition('foo')
                ->getArguments()
        );

        $currentReference = $container->getDefinition('foo')->getArgument(0);

        $this->compilerPass->process($container);

        self::assertSame($currentReference, $container->getDefinition('foo')->getArgument(0));
    }
}

class MockMethodResolver implements CompositeMethodArgumentResolver
{

    /**
     * @var string
     */
    private $returnKeFromTag;
    private $argument;
    /**
     * @var string
     */
    private $serviceId;

    public function __construct($argument, string $returnKeFromTag, string $serviceId)
    {
        $this->returnKeFromTag = $returnKeFromTag;
        $this->argument = $argument;
        $this->serviceId = $serviceId;
    }

    public function resolveFor(
        ContainerBuilder $container,
        string $serviceId,
        Map $tagProperties
    ): array {
        if ($this->serviceId !== $serviceId) {
            throw new LogicException(sprintf('Expected service foo but got %s', $serviceId));
        }

        return [$this->argument => $tagProperties->get($this->returnKeFromTag)];
    }

    public function finish(): void
    {

    }
}
