<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicCompositePass;

require_once __DIR__ . '/../../vendor/autoload.php';

class ConstructorCompositeService
{

    private array $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function dumpServices(): void
    {
        echo Symfony\Component\VarDumper\VarDumper::dump($this->services);
    }
}

class VariadicConstructorCompositeService
{

    private array $services;

    public function __construct(...$services)
    {
        $this->services = $services;
    }

    public function dumpServices(): void
    {
        echo Symfony\Component\VarDumper\VarDumper::dump($this->services);
    }
}

$container = new ContainerBuilder();
$container->addCompilerPass(new DynamicCompositePass());

/**
 * Register composite for tag unnamed_arg_composite_service_tag that accepts services as unnamed argument
 * on position 0 (default)
 */
$container->register('unnamed_arg_composite_service', ConstructorCompositeService::class)
    ->setPublic(true)
    ->addTag(
        'dynamic_composite',
        [
            'tag' => 'unnamed_arg_composite_service_tag',
        ]
    );

/**
 * Register composite for tag named_arg_composite_service_tag that accepts services as named argument $services
 */
$container->register('named_arg_composite_service', ConstructorCompositeService::class)
    ->setPublic(true)
    ->addTag(
        'dynamic_composite',
        [
            'argument' => '$services',
            'tag'      => 'named_arg_composite_service_tag',
        ]
    );

/**
 * Register composite for tag named_arg_variadic_composite_service_tag that accepts services as variadic named
 * argument $services.
 *
 * Variadic arguments are currently only possible in the constructor for named arguments for now.
 */
$container->register('named_arg_variadic_composite_service', VariadicConstructorCompositeService::class)
    ->setPublic(true)
    ->addTag(
        'dynamic_composite',
        [
            'argument' => '$services',
            'tag'      => 'named_arg_variadic_composite_service_tag',
        ]
    );

/**
 * Registers service that is injected into composite service for tags bellow.
 *
 * Services are by default sorted by priority in ascending order. Default priority is 0.
 */
$container->register('bar_service', stdClass::class)
    ->setProperty('bar', 64)
    ->addTag('unnamed_arg_composite_service_tag')
    ->addTag('named_arg_composite_service_tag')
    ->addTag('named_arg_variadic_composite_service_tag',
        [
            'priority' => 8,
        ]);
/**
 * Registers service that is injected info composite service for tags bellow.
 */
$container->register('foo_service', stdClass::class)
    ->setProperty('foo', 32)
    ->addTag('unnamed_arg_composite_service_tag')
    ->addTag('named_arg_composite_service_tag')
    ->addTag('named_arg_variadic_composite_service_tag');

$container->compile();

$compositeServices = [
    'unnamed_arg_composite_service',
    'named_arg_composite_service',
    'named_arg_variadic_composite_service',
];

foreach ($compositeServices as $service) {
    VarDumper::dump($service);
    VarDumper::dump($container->get($service));
    echo "\n";
}

/*
Output:

"unnamed_arg_composite_service"
ConstructorCompositeService {#5
  -services: array:2 [
    0 => {#113
      +"bar": 64
    }
    1 => {#118
      +"foo": 32
    }
  ]
}

"named_arg_composite_service"
ConstructorCompositeService {#103
  -services: array:2 [
    0 => {#113
      +"bar": 64
    }
    1 => {#118
      +"foo": 32
    }
  ]
}

"named_arg_variadic_composite_service"
VariadicConstructorCompositeService {#117
  -services: array:2 [
    0 => {#118
      +"foo": 32
    }
    1 => {#113
      +"bar": 64
    }
  ]
}
 */