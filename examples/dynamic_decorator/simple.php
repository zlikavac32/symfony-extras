<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;

require_once __DIR__ . '/../../vendor/autoload.php';

interface Service
{

    public function run(): void;
}

class ConcreteService implements Service
{

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function run(): void
    {
        echo VarDumper::dump($this->name);
    }
}

class WrappedService {

    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function doSomething(): void
    {
        echo VarDumper::dump(get_class($this));

        $this->service->run();
    }
}

abstract class Decorator implements Service
{

    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function run(): void
    {
        echo VarDumper::dump(get_class($this));
        $this->service->run();
    }
}

class FooDecorator extends Decorator
{

}

class BarDecorator extends Decorator
{

}

class BazDecorator extends Decorator
{

}

$container = new ContainerBuilder();
$container->addCompilerPass(new DecoratorPass());

/**
 * Following three services are templates for decorators. They must be abstract, and no two decorators can use
 * same tag.
 */
$container->register(FooDecorator::class, FooDecorator::class)
    ->setAbstract(true)
    ->addTag('decorator', [
        'tag' => 'decorator.service.foo',
    ]);
$container->register(BarDecorator::class, BarDecorator::class)
    ->setAbstract(true)
    ->addTag('decorator', [
        'tag' => 'decorator.service.bar',
    ]);
$container->register(BazDecorator::class, BazDecorator::class)
    ->setAbstract(true)
    ->addTag('decorator', [
        'tag' => 'decorator.service.baz',
    ]);

/**
 * Decorate service with BarDecorator and then with FooDecorator. Unless priority is specified,
 * tags for decorators that are encountered earlier, decorate service earlier.
 */
$container->register('first_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('First')
    ->addTag('decorator.service.bar')
    ->addTag('decorator.service.foo');

/**
 * Decorate service with FooDecorator, then BarDecorator and in the with with BazDecorator
 */
$container->register('second_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('Second')
    ->addTag('decorator.service.foo')
    ->addTag('decorator.service.bar')
    ->addTag('decorator.service.baz');

/**
 * When priority is used, it respects semantic of Symfony decoration priority. In other words, higher number, earlier
 * decoration. Default priority is 0 and within same priority, tag order is respected.
 */
$container->register('third_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('Third')
    ->addTag('decorator.service.foo')
    ->addTag('decorator.service.bar', [
        "priority" => 32,
    ])
    ->addTag('decorator.service.baz');

/**
 * We can also decorate just some arguments in constructor. Method decoration is not yet supported
 */
$container->register('fourth_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('Fourth');

$container->register('wrapped_service', WrappedService::class)
    ->setPublic(true)
    ->addArgument(new Reference('fourth_service'))
    ->addTag('decorator.service.foo', [
        'argument' => 0
    ])
    ->addTag('decorator.service.baz', [
        'argument' => 0
    ]);

$container->compile();

$compositeServices = [
    'first_service',
    'second_service',
    'third_service',
    'fourth_service',
];

foreach ($compositeServices as $service) {
    echo VarDumper::dump($service);
    $container->get($service)
        ->run();
    echo "\n";
}

$service = 'wrapped_service';

echo VarDumper::dump($service);
$container->get($service)
    ->doSomething();

echo "\n";

/*
Output:

"first_service"
"FooDecorator"
"BarDecorator"
"First"

"second_service"
"BazDecorator"
"BarDecorator"
"FooDecorator"
"Second"

"third_service"
"BazDecorator"
"FooDecorator"
"BarDecorator"
"Third"

"fourth_service"
"Fourth"

"wrapped_service"
"WrappedService"
"BazDecorator"
"FooDecorator"
"Fourth"
 */
