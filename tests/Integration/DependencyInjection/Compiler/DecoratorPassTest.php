<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\DecoratorExistsFor;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\DecoratorExistsForArgument;
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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', 0, 0)
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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'decorator-2', 'baz', 0, 0)
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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', 0, 8)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'decorator-2', 'baz', 0, 88)
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
        $this->expectExceptionMessage('Tag decorator-1 already provided by foo (issue found on service baz)');

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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'decorator-2', 'foo', 0, 0)
        );
    }

    /**
     * @test
     */
    public function decorator_is_ignored_when_no_reference_exists_to_it(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $this->compilerPass->process($container);

        $this->assertTrue(true);
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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', 0, 0)
        );
        self::assertThat(
            $container,
            new DecoratorExistsFor('baz', 'decorator-1', 'foo', 0, 0)
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
        $this->expectExceptionMessage('Only one tag decorator-1 allowed on service bar');

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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', '$foo', 0)
        );
    }

    /**
     * @test
     */
    public function decoration_works_in_multiple_passes(): void
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
            new DecoratorExistsFor('bar', 'decorator-1', 'foo', '$foo', 0)
        );

        $currentFooDecoratorDefinition = $container->getDefinition('bar.decorator-1');

        $container->register('baz')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag'      => 'decorator-2',
                'argument' => '$baz',
            ]);
        $container->getDefinition('bar')
            ->addTag('decorator-2');

        $this->compilerPass->process($container);

        self::assertSame($currentFooDecoratorDefinition, $container->getDefinition('bar.decorator-1'));

        self::assertThat(
            $container,
            new DecoratorExistsFor('bar', 'decorator-2', 'baz', '$baz', 0)
        );
    }

    /**
     * @test
     */
    public function service_argument_can_be_decorated(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->setArgument('$baz', new Reference('baz'))
            ->addTag('decorator-1', [
                'argument' => '$baz'
            ]);

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsForArgument('bar', 'decorator-1', 'foo', '$baz', 0, 0)
        );
    }

    /**
     * @test
     */
    public function service_argument_can_be_decorated_with_multiple_decorators(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-2',
                'argument' => '$bar'
            ]);

        $container->register('baz')
            ->setArgument('$emo', new Reference('emo'))
            ->addTag('decorator-1', [
                'argument' => '$emo'
            ])
            ->addTag('decorator-2', [
                'argument' => '$emo',
                'priority' => 5
            ]);

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsForArgument('baz', 'decorator-1', 'foo', '$emo', 0, 0)
        );

        self::assertThat(
            $container,
            new DecoratorExistsForArgument('baz', 'decorator-2', 'bar', '$emo', '$bar', 5)
        );
    }

    /**
     * @test
     */
    public function multiple_arguments_can_be_decorated_with_same_decorator_on_same_service(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->setArgument('$baz', new Reference('baz'))
            ->addTag('decorator-1', [
                'argument' => '$baz'
            ])
            ->setArgument('$bazTwo', new Reference('baz'))
            ->addTag('decorator-1', [
                'argument' => '$bazTwo'
            ]);

        $this->compilerPass->process($container);

        self::assertThat(
            $container,
            new DecoratorExistsForArgument('bar', 'decorator-1', 'foo', '$baz', 0, 0)
        );

        self::assertThat(
            $container,
            new DecoratorExistsForArgument('bar', 'decorator-1', 'foo', '$bazTwo', 0, 0)
        );
    }

    /**
     * @test
     */
    public function service_argument_must_be_explicitly_declared(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->addTag('decorator-1', [
                'argument' => '$baz'
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Argument $baz must be explicitly defined to reference some service (issue on service bar)');

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function only_one_decorator_per_service_argument_allowed(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setAbstract(true)
            ->addTag('decorator', [
                'tag' => 'decorator-1',
            ]);

        $container->register('bar')
            ->setArgument('$baz', new Reference('bar'))
            ->addTag('decorator-1', [
                'argument' => '$baz'
            ])
            ->addTag('decorator-1', [
                'argument' => '$baz'
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only one tag decorator-1 allowed on service bar and argument $baz');

        $this->compilerPass->process($container);
    }
}
