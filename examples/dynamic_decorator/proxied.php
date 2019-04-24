<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zlikavac32\NSBDecorators\Proxy;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;

require_once __DIR__ . '/../../vendor/autoload.php';

interface Command
{

    public function run(): void;
}

interface CommandWithHelp extends Command
{

    public function help(): string;
}

class ConcreteCommand implements CommandWithHelp
{

    public function run(): void
    {
        echo __METHOD__, "\n";
    }

    public function help(): string
    {
        return 'help';
    }
}

class DecoratorCommand implements Command
{

    /**
     * @var Command
     */
    private $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function run(): void
    {
        echo __METHOD__, "\n";

        $this->command->run();
    }
}

$container = new ContainerBuilder();
$container->addCompilerPass(new DecoratorPass());

$container->register('decorator')
    ->setClass(DecoratorCommand::class)
    ->setAbstract(true)
    ->addTag('decorator', [
        'tag' => 'decorator-1'
    ]);

$container->register('service')
    ->setClass(ConcreteCommand::class)
    ->setPublic(true)
    ->addTag('decorator-1', [
        'proxy' => true
    ]);


spl_autoload_register(Proxy::class . '::loadFQN');

$container->compile();

echo $container->get('service')->help(), "\n";

$container->get('service')->run();

/*
Output:

help
DecoratorCommand::run
ConcreteCommand::run
 */
