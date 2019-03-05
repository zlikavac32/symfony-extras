<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Map;
use Ds\PriorityQueue;
use Ds\Set;
use Ds\Vector;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicComposite\CompositeMethodArgumentResolver;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertValueIsOfType;

/**
 * Registers services as composite services that require injection of other services.
 *
 * Injection can be performed into constructor or method. Variadic arguments for methods
 * must be explicitly marked.
 */
class DynamicCompositePass implements CompilerPassInterface
{

    /**
     * @var string
     */
    private $tag;
    /**
     * @var Map|CompositeMethodArgumentResolver[]
     */
    private $argumentResolvers;

    public function __construct(string $tag = 'dynamic_composite', ?Map $argumentResolvers = null)
    {
        $this->tag = $tag;
        $this->argumentResolvers = $argumentResolvers ?? new Map();
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds($this->tag) as $serviceId => $tags) {
            $this->registerDynamicTraversers($container, $serviceId, $tags);
        }
    }

    private function registerDynamicTraversers(ContainerBuilder $container, string $serviceId, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagName = $tag['tag'] ?? null;
            $method = $tag['method'] ?? '__construct';
            $argument = $tag['argument'] ?? 0;
            $isPrioritized = $tag['prioritized'] ?? true;

            assertValueIsOfType($tagName, new Set(['string']), 'tag', $serviceId);
            /** @var string $tagName */

            assertValueIsOfType($method, new Set(['string']), 'method', $serviceId);
            assertValueIsOfType($argument, new Set(['string', 'integer']), 'argument', $serviceId);
            assertValueIsOfType($isPrioritized, new Set(['boolean']), 'prioritized', $serviceId);

            $servicesToInject = $this->collectServicesToInject($container, $tagName, $isPrioritized);

            $this->registerDynamicComposite(
                $container,
                $tagName,
                $serviceId,
                $servicesToInject,
                $method,
                $argument,
                );
        }
    }

    private function collectServicesToInject(ContainerBuilder $container, string $tag, bool $isPrioritized): Vector
    {
        $priorityQueue = new PriorityQueue();

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            foreach ($tags as $singleTag) {
                $priority = 0;

                if ($isPrioritized) {
                    $priority = $singleTag['priority'] ?? 0;

                    assertValueIsOfType($priority, new Set(['integer']), 'priority', $serviceId);
                }

                $priorityQueue->push([new Reference($serviceId), new Map($singleTag)], -$priority);
            }
        }

        return new Vector($priorityQueue);
    }

    private function registerDynamicComposite(
        ContainerBuilder $container,
        string $tagName,
        string $serviceId,
        Vector $servicesToInject,
        ?string $method,
        $argument
    ): void {
        $serviceDefinition = $container->findDefinition($serviceId);

        if ('__construct' === $method) {
            $this->registerDynamicCompositeForConstructor(
                $serviceDefinition,
                $servicesToInject,
                $argument
            );

            return;
        }

        $this->registerDynamicCompositeForMethod(
            $container,
            $serviceDefinition,
            $tagName,
            $serviceId,
            $servicesToInject,
            $method,
            $argument
        );
    }

    private function registerDynamicCompositeForConstructor(
        Definition $serviceDefinition,
        Vector $servicesToInject,
        $argument
    ): void {
        $serviceDefinition->setArgument(
            $argument,
            $servicesToInject->map(
                function (array $arguments): Reference {
                    return $arguments[0];
                }
            )
                ->toArray()
        );
    }

    private function registerDynamicCompositeForMethod(
        ContainerBuilder $container,
        Definition $serviceDefinition,
        string $tagName,
        string $serviceId,
        Vector $servicesToInject,
        ?string $method,
        $argument
    ): void {
        $argumentResolver = $this->argumentResolvers->hasKey($tagName) ? $this->argumentResolvers->get(
            $tagName
        ) : new class implements CompositeMethodArgumentResolver
        {

            public function resolveFor(ContainerBuilder $container, string $serviceId, Map $tagProperties): array
            {
                return [];
            }

            public function finish(): void
            {

            }
        };

        assert($argumentResolver instanceof CompositeMethodArgumentResolver);

        foreach ($servicesToInject as $arguments) {
            $resolvedArguments = $argumentResolver->resolveFor($container, $serviceId, new Map($arguments[1]));

            if (isset($resolvedArguments[$argument])) {
                throw new LogicException(sprintf('Argument %s already defined by the resolver', $argument));
            }

            $resolvedArguments[$argument] = $arguments[0];

            $serviceDefinition->addMethodCall(
                $method,
                $resolvedArguments
            );
        }

        $argumentResolver->finish();
    }
}
