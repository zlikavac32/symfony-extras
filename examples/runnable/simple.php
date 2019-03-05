<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zlikavac32\SymfonyExtras\Command\Runnable\Runnable;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnablePass;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/common.php';

class FooRunnable implements Runnable
{

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
        $output->writeln($input->getArgument('name'));

        return 0;
    }
}

$container = createContainer();
$container->addCompilerPass(new ConsoleRunnablePass());

$container->register(FooRunnable::class, FooRunnable::class)
    ->addTag(
        'console_runnable',
        [
            'command' => 'foo', // runnable will be wrapped in a command with name foo
        ]
    );

$container->compile();

$application = createApplication($container);

$application->run();

/*
Output for "foo Foo":

Foo
 */
