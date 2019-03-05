<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\DecoratorExistsFor;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\KeyAppearsBeforeOtherKey;

class DecoratorPassTest extends TestCase
{

    /**
     * @var DecoratorPass
     */
    private $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new DecoratorPass();
    }

    protected function tearDown(): void
    {
        unset($this->compilerPass);
    }

    /**
     * @test
     */
    public function service_should_be_decorated(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->addTag('decorator-1');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', 0, 0)
        );
    }

    /**
     * @test
     */
    public function when_no_priority_is_defined_tag_order_is_respected(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('baz')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-2',
            ]);

        $container->register('bar')
            ->addTag('decorator-2')
            ->addTag('decorator-1');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-2', 'baz', 0, 0)
        );

        self::assertThat(
            $container->getDefinitions(),
            new KeyAppearsBeforeOtherKey('bar.decorator-2', 'bar.decorator-1')
        );
    }

    /**
     * @test
     */
    public function decorator_priority_is_respected(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('baz')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-2',
            ]);

        $container->register('bar')
            ->addTag('decorator-1', [
                'priority' => 8,
            ])
            ->addTag('decorator-2', [
                'priority' => 88,
            ]);

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', 0, 8)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-2', 'baz', 0, 88)
        );
    }

    /**
     * @test
     */
    public function exception_should_be_thrown_when_multiple_services_provide_same_tag(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('baz')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->addTag('decorator-1', [
                'priority' => 8,
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tag decorator-1 already provided by foo');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function one_service_can_provide_multiple_decorators(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ])
            ->addTag('decorator', [
                'tag' => 'decorator-2',
            ]);
        $container->register('bar')
            ->addTag('decorator-1')
            ->addTag('decorator-2');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-2', 'foo', 0, 0)
        );
    }

    /**
     * @test
     */
    public function one_decorator_can_be_applied_to_multiple_services(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);
        $container->register('bar')
            ->addTag('decorator-1');
        $container->register('baz')
            ->addTag('decorator-1');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('baz', 'baz.decorator-1', 'foo', 0, 0)
        );
    }

    /**
     * @test
     */
    public function tags_can_be_reconstructed(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);
        $container->register('bar')
            ->addTag('decorator-1', [
                'i1_name' => 'foo_1',
                'i0_name' => 'foo_0',
                'i0_bar'  => 'bar_0',
            ]);

        $this->compilerPass->process($container);

        self::assertSame([
            'foo_0' => [
                [
                    'bar' => 'bar_0',
                ],
            ],
            'foo_1' => [
                [],
            ],
        ], $container->findDefinition('bar.decorator-1')
            ->getTags());
    }

    /**
     * @test
     */
    public function only_one_decorator_tag_per_service_allowed(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);
        $container->register('bar')
            ->addTag('decorator-1')
            ->addTag('decorator-1');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service bar has multiple decorator-1 tags which is not allowed');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function custom_argument_can_be_used(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag'      => 'decorator-1',
                'argument' => '$foo',
            ]);
        $container->register('bar')
            ->addTag('decorator-1');

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'bar.decorator-1', 'foo', '$foo', 0)
        );
    }
}
