<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\VarDumper\VarDumper;
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

    private string $foo;

    public function __construct(string $foo, Logger $logger)
    {
        $this->logger = $logger;
        $this->foo = $foo;
    }

    public function doSomething(): void
    {
        echo VarDumper::dump($this->foo);
        $this->logger->log();
    }
}

$container = new ContainerBuilder();
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
 * Service can be linked to an arbitrary argument with symbolic name.
 */
$container->register(Service::class, Service::class)
    ->setPublic(true)
    ->setArgument(0, 'some-param')
    ->addTag('linker', [
        'provider_tag' => 'logger_provider',
        'provider'     => 'foo',
        'argument'     => '$logger',
    ]);

$container->compile();

$container->get(Service::class)
    ->doSomething();

/*
Output:

"some-param"
"FooLogger::log"
 */
