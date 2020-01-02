<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass;

require_once __DIR__ . '/../../vendor/autoload.php';

interface Logger
{

    public function log(): void;
}

class FooLogger implements Logger
{

    public function log(): void
    {
        echo VarDumper::dump(__METHOD__);
    }
}

class BarLogger implements Logger
{

    public function log(): void
    {
        echo VarDumper::dump(__METHOD__);
    }
}

class Service
{

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function doSomething(): void
    {
        $this->logger->log();
    }
}

$container = new ContainerBuilder();
$container->addCompilerPass(new DecoratorPass());
$container->addCompilerPass(new ServiceLinkerPass());

/**
 * Register two providers. They have same tag and specify identifier in provides property.
 */
$container->register(BarLogger::class, BarLogger::class)
    ->addTag('logger_provider', [
        'provides' => 'bar',
    ]);
$container->register(FooLogger::class, FooLogger::class)
    ->addTag('logger_provider', [
        'provides' => 'foo',
    ]);

/**
 * Although this is mostly useful in combination with decorators, this example
 * demonstrates what happens behind the curtain with tag passing.
 *
 * Linker now defines tag that will resolve concrete value for the argument. In this example,
 * argument is resolved from the providers. Parameter or service could have been used as well.
 */
$container->register(Service::class, Service::class)
    ->setPublic(true)
    ->addTag('linker', [
        'argument_resolver_tag' => 'service_arg_resolver',
    ])
    ->addTag('service_arg_resolver', [
        'provider_tag' => 'logger_provider',
        'provider'     => 'bar',
    ]);

$container->compile();

$container->get(Service::class)
    ->doSomething();

/*
Output:

"BarLogger::log"
 */
