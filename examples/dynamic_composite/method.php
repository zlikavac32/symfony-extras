<?php

declare(strict_types=1);

use Ds\Map;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicComposite\CompositeMethodArgumentResolver;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicCompositePass;

require_once __DIR__ . '/../../vendor/autoload.php';

class MethodCompositeService
{

    /**
     * @var array
     */
    private $services = [];

    public function add($service)
    {
        $this->services[] = $service;
    }

    public function dumpServices(): void
    {
        echo Symfony\Component\VarDumper\VarDumper::dump($this->services);
    }
}

class CustomArgumentMethodCompositeService
{

    /**
     * @var array
     */
    private $services = [];

    public function add(int $priority, $service)
    {
        $this->services[] = [$priority, $service];
    }

    public function dumpServices(): void
    {
        echo Symfony\Component\VarDumper\VarDumper::dump($this->services);
    }
}

$container = new ContainerBuilder();
/**
 * We want to have some custom argument resolving for method injection. In this example,
 * for tag custom_argument_method_tag, we want priority from the tag.
 */
$container->addCompilerPass(new DynamicCompositePass('dynamic_composite', new Map([
    'custom_argument_method_tag' => new class implements CompositeMethodArgumentResolver
    {

        public function resolveFor(ContainerBuilder $container, string $serviceId, Map $tagProperties): array
        {
            return ['$priority' => $tagProperties->get('priority')];
        }

        public function finish(): void
        {

        }
    },
])));

/**
 * Registers composite service that gets dependent services injected through method in the argument on position 0.
 *
 * Method user is add()
 */
$container->register('unnamed_method', MethodCompositeService::class)
    ->setPublic(true)
    ->addTag('dynamic_composite', [
        'tag'    => 'unnamed_method_tag',
        'method' => 'add',
    ]);

/**
 * Registers composite service for named argument ($service).
 */
$container->register('named_method', MethodCompositeService::class)
    ->setPublic(true)
    ->addTag('dynamic_composite', [
        'tag'      => 'named_method_tag',
        'argument' => '$service',
        'method'   => 'add',
    ]);

/**
 * Registers composite service that has more than one argument in method call. In this example,
 * we take priority value from tag. Since we probably don't have to order method calls, we
 * can mark this as not prioritized.
 */
$container->register('custom_argument_method', CustomArgumentMethodCompositeService::class)
    ->setPublic(true)
    ->addTag('dynamic_composite', [
        'tag'         => 'custom_argument_method_tag',
        'argument'    => '$service',
        'method'      => 'add',
        'prioritized' => false,
    ]);

/**
 * Bar service will be injected into composite services defined by the tags bellow.
 */
$container->register('bar_service', stdClass::class)
    ->setProperty('bar', 64)
    ->addTag('unnamed_method_tag')
    ->addTag('named_method_tag', [
        'priority' => 8,
    ])
    ->addTag('custom_argument_method_tag', [
        'priority' => 33,
    ]);

/**
 * Foo service will be injected into composite services defined by the tags bellow.
 */
$container->register('foo_service', stdClass::class)
    ->setProperty('foo', 32)
    ->addTag('unnamed_method_tag')
    ->addTag('named_method_tag')
    ->addTag('custom_argument_method_tag', [
        'priority' => 12,
    ]);

$container->compile();

$compositeServices = [
    'unnamed_method',
    'named_method',
    'custom_argument_method',
];

foreach ($compositeServices as $service) {
    VarDumper::dump($service);
    VarDumper::dump($container->get($service));
    echo "\n";
}

/*
Output:

"unnamed_method"
MethodCompositeService {#6
  -services: array:2 [
    0 => {#113
      +"bar": 64
    }
    1 => {#109
      +"foo": 32
    }
  ]
}

"named_method"
MethodCompositeService {#148
  -services: array:2 [
    0 => {#109
      +"foo": 32
    }
    1 => {#113
      +"bar": 64
    }
  ]
}

"custom_argument_method"
CustomArgumentMethodCompositeService {#147
  -services: array:2 [
    0 => array:2 [
      0 => 33
      1 => {#113
        +"bar": 64
      }
    ]
    1 => array:2 [
      0 => 12
      1 => {#109
        +"foo": 32
      }
    ]
  ]
}
 */
