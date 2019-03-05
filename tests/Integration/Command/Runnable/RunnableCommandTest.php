<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Integration\Command\Runnable;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Zlikavac32\SymfonyExtras\Command\Runnable\ConsoleApplicationAwareRunnable;
use Zlikavac32\SymfonyExtras\Command\Runnable\HelperSetAwareRunnable;
use Zlikavac32\SymfonyExtras\Command\Runnable\Runnable;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableCommand;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithDescription;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithHelp;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithVisibility;

class RunnableCommandTest extends TestCase
{

    /**
     * @test
     */
    public function runnable_is_properly_run_from_the_command(): void
    {
        $runnable = new StubRunnable();

        $command = new RunnableCommand($runnable, 'foo');

        self::assertSame($command->getName(), 'foo');

        $input = new ArrayInput(
            [
                '--bar' => 'baz',
            ]
        );

        $output = new BufferedOutput();

        self::assertSame(
            1,
            $command->run($input, $output)
        );
        self::assertSame(
            'baz',
            $output->fetch()
        );
    }

    /**
     * @test
     */
    public function runnable_with_help_sets_command_help(): void
    {
        $runnable = new class extends StubRunnable implements RunnableWithHelp
        {

            public function help(): string
            {
                return 'test help';
            }
        };

        $command = new RunnableCommand($runnable, 'foo');

        self::assertSame($command->getHelp(), 'test help');
    }

    /**
     * @test
     */
    public function runnable_with_description_sets_command_description(): void
    {
        $runnable = new class extends StubRunnable implements RunnableWithDescription
        {

            public function description(): string
            {
                return 'test description';
            }
        };

        $command = new RunnableCommand($runnable, 'foo');

        self::assertSame($command->getDescription(), 'test description');
    }

    /**
     * @test
     */
    public function runnable_with_visibility_sets_command_visibility(): void
    {
        $runnable = new class extends StubRunnable implements RunnableWithVisibility
        {

            public function isHidden(): bool
            {
                return true;
            }
        };

        $command = new RunnableCommand($runnable, 'foo');

        self::assertTrue($command->isHidden());
    }

    /**
     * @test
     */
    public function application_can_be_injected_into_runnable(): void
    {
        $runnable = new class extends StubRunnable implements ConsoleApplicationAwareRunnable
        {

            public $application;

            public function useApp(Application $application): void
            {
                $this->application = $application;
            }
        };

        $application = new Application();

        $command = new RunnableCommand($runnable, 'foo');
        $command->setApplication($application);

        $input = new ArrayInput(
            [
                '--bar' => 'baz',
            ]
        );

        $output = new BufferedOutput();

        $command->run($input, $output);

        self::assertSame($application, $runnable->application);
    }

    /**
     * @test
     */
    public function helper_set_can_be_injected_into_runnable(): void
    {
        $runnable = new class extends StubRunnable implements HelperSetAwareRunnable
        {

            public $helperSet;

            public function useHelperSet(HelperSet $helperSet): void
            {
                $this->helperSet = $helperSet;
            }
        };

        $application = new Application();

        $command = new RunnableCommand($runnable, 'foo');
        $command->setApplication($application);

        $input = new ArrayInput(
            [
                '--bar' => 'baz',
            ]
        );

        $output = new BufferedOutput();

        $command->run($input, $output);

        self::assertSame($application->getHelperSet(), $runnable->helperSet);
    }
}

class StubRunnable implements Runnable
{

    public function configure(InputDefinition $inputDefinition): void
    {
        $inputDefinition->addOption(new InputOption('bar', null, InputOption::VALUE_REQUIRED));
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $output->write($input->getOption('bar'));

        return 1;
    }
}
