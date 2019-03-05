<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\VarDumper\VarDumper;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass;

require_once __DIR__ . '/../../vendor/autoload.php';

interface Service
{

    public function run(): void;
}

class ConcreteService implements Service
{

    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function run(): void
    {
        echo VarDumper::dump($this->name);
    }
}

class FooDecorator implements Service
{

    /**
     * @var string
     */
    private $param;
    /**
     * @var Service
     */
    private $service;

    public function __construct(string $param, Service $service)
    {
        $this->param = $param;
        $this->service = $service;
    }

    public function run(): void
    {
        echo VarDumper::dump($this->param);
        $this->service->run();
    }
}

$container = new ContainerBuilder();
$container->addCompilerPass(new DecoratorPass());
$container->addCompilerPass(new ServiceLinkerPass());

$container->setParameter('demo_param', 'demo_param_1');

/**
 * Idea is to have different parameters injected into decorator, depending on which service is being decorated.
 * Default argument will be demo_param_2.
 *
 * First decorator tag is defined which describes tag that will be used to invoke decorator and argument in which
 * decorated service should be injected.
 *
 * Linker tag defines tag for indirect linking. Concrete decorator will define tag foo_decorator_param_argument
 * which describes to which argument is resolved.
 */
$container->register('foo_decorator', FooDecorator::class)
    ->setAbstract(true)
    ->setArgument('$param', 'demo_param_2')
    ->addTag(
        'decorator',
        [
            'tag'      => 'foo_decorator_tag',
            'argument' => '$service',
        ]
    )
    ->addTag('linker', [
        'argument_resolver_tag' => 'foo_decorator_param_argument',
        'argument'              => '$param',
    ]);

/**
 * Concrete decorator is created from this template and that newly created decorator must provide tag
 * foo_decorator_param_argument.
 *
 * Since tag values can only be scalar values, tags are linearly represented in the format i(group)_(property).
 * (group) is used to (property) values together. Reconstructed tags are sorted by (group) in ascending order.
 *
 * This results in tag - { name: foo_decorator_param_argument, param: demo_param }
 *
 * Instead of param, providers or services can be injected.
 *
 * This decorator will be linked with value demo_param_1
 */
$container->register('first_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('First')
    ->addTag('foo_decorator_tag', [
        'i0_name'  => 'foo_decorator_param_argument',
        'i0_param' => 'demo_param',
    ]);

$container->register('second_service', ConcreteService::class)
    ->setPublic(true)
    ->addArgument('Second')
    ->addTag('foo_decorator_tag');

$container->compile();

$compositeServices = [
    'first_service',
    'second_service',
];

foreach ($compositeServices as $service) {
    echo VarDumper::dump($service);
    $container->get($service)
        ->run();
    echo "\n";
}

/*
Output:

"first_service"
"demo_param_1"
"First"

"second_service"
"demo_param_2"
"Second"
 */
