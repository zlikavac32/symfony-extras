<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnablePass;
use Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint\CommandRegisteredForRunnable;

class ConsoleRunnablePassTest extends TestCase
{

    private ?ConsoleRunnablePass $compilerPass;

    protected function setUp(): void
    {
        $this->compilerPass = new ConsoleRunnablePass();
    }

    protected function tearDown(): void
    {
        $this->compilerPass = null;
    }

    /**
     * @test
     */
    public function runnable_with_command_name_in_tag_should_be_registered(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'console_runnable',
                [
                    'command' => 'cmd:foo',
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat($container, new CommandRegisteredForRunnable('foo', 'cmd:foo'));
    }

    /**
     * @test
     */
    public function runnable_with_mapper_class_in_tag_should_be_registered(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                [
                    'mapper' => MockMapper::class,
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat($container, new CommandRegisteredForRunnable('foo', 'cmd:foo'));
    }

    /**
     * @test
     */
    public function mapper_must_exist_for_runnable(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Mapper IDoNotExist does not exist for service foo');

        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                [
                    'mapper' => 'IDoNotExist',
                ]
            );

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function mapper_must_implement_proper_interface(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            sprintf('Mapper stdClass does not implement %s (defined on service foo)',
                \Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable\RunnableToNameMapper::class)
        );

        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                [
                    'mapper' => stdClass::class,
                ]
            );

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function exception_should_be_thrown_when_both_command_and_mapper_are_defined(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tag console_runnable on service foo has both command and mapper options');

        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                [
                    'mapper'  => MockMapper::class,
                    'command' => 'cmd:foo',
                ]
            );

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function exception_should_be_thrown_when_none_of_command_and_mapper_is_defined(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tag console_runnable on service foo does not have a command or mapper option');

        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                []
            );

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function exception_should_be_thrown_when_multiple_runnable_tags_exist_for_same_service(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Service foo has multiple console_runnable tags which is not allowed');

        $container = new ContainerBuilder();

        $container->register('foo')
            ->setClass('bar')
            ->addTag(
                'console_runnable',
                []
            )
            ->addTag(
                'console_runnable',
                []
            );

        $this->compilerPass->process($container);
    }

    /**
     * @test
     */
    public function processing_is_idempotent(): void
    {
        $container = new ContainerBuilder();

        $container->register('foo')
            ->addTag(
                'console_runnable',
                [
                    'command' => 'cmd:foo',
                ]
            );

        $this->compilerPass->process($container);

        self::assertThat($container, new CommandRegisteredForRunnable('foo', 'cmd:foo'));

        $createdReference = $container->findDefinition('foo.command')->getArgument(0);

        $this->compilerPass->process($container);

        self::assertSame($createdReference, $container->findDefinition('foo.command')->getArgument(0));
    }
}

class MockMapper implements \Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable\RunnableToNameMapper
{

    public function map(string $fqn): string
    {
        if ($fqn !== 'bar') {
            throw new LogicException(sprintf('Expected bar as fqn but got %s', $fqn));
        }

        return 'cmd:foo';
    }
}