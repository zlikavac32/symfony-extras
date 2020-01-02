<?php

declare(strict_types=1);

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zlikavac32\SymfonyExtras\Command\Runnable\HelperSetAwareRunnable;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithDescription;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithHelp;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnablePass;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/common.php';

/**
 * Runnable that uses more functionality available in the console.
 */
class FooRunnable implements HelperSetAwareRunnable, RunnableWithDescription, RunnableWithHelp
{

    private HelperSet $helperSet;

    public function configure(InputDefinition $inputDefinition): void
    {
        $inputDefinition->addArgument(
            new InputArgument('name', InputArgument::REQUIRED, 'Name that is printed back')
        );
    }

    public function run(
        InputInterface $input,
        OutputInterface $output
    ): int {
        assert($this->helperSet !== null);

        $formatterHelper = $this->helperSet->get('formatter');
        assert($formatterHelper instanceof FormatterHelper);

        $output->writeln(
            $formatterHelper->formatSection('Input argument', $input->getArgument('name'))
        );

        return 0;
    }

    public function useHelperSet(HelperSet $helperSet): void
    {
        $this->helperSet = $helperSet;
    }

    public function description(): string
    {
        return 'Foo description';
    }

    public function help(): string
    {
        return 'Foo help';
    }
}

$container = createContainer();
$container->addCompilerPass(new ConsoleRunnablePass());

$container->register(FooRunnable::class, FooRunnable::class)
    ->addTag(
        'console_runnable',
        [
            'command' => 'foo',
        ]
    );

$container->compile();

$application = createApplication($container);

$application->run();

/*
Output for "foo Foo":

[Input argument] Foo
 */

/*
Output for "foo --help":

Description:
  Foo description

Usage:
  foo <name>

Arguments:
  name                  Name that is printed back

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Foo help
 */
