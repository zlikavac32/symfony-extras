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
use function Zlikavac32\SymfonyExtras\DependencyInjection\buildMapOfTagsAndServiceIds;
use function Zlikavac32\SymfonyExtras\DependencyInjection\processedItemsSetFromContainer;

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
    /**
     * @var Map|string[]
     */
    private $processedTags;
    /**
     * @var Set|string[]
     */
    private $globallyProcessedTags;
    /**
     * @var Map|Set[]|string[][]
     */
    private $tagToServicesMap;

    public function __construct(string $tag = 'dynamic_composite', ?Map $argumentResolvers = null)
    {
        $this->tag = $tag;
        $this->argumentResolvers = $argumentResolvers ?? new Map();
    }

    public function process(ContainerBuilder $container): void
    {
        $this->tagToServicesMap = buildMapOfTagsAndServiceIds($container);

        $this->processedTags = new Map();
        $this->globallyProcessedTags = processedItemsSetFromContainer($container, self::class, $this->tag, 'composite_tags');

        try {
            if (!$this->tagToServicesMap->hasKey($this->tag)) {
                return ;
            }

            foreach ($this->tagToServicesMap->get($this->tag) as $serviceId) {
                $this->registerDynamicTraversers(
                    $container,
                    $serviceId,
                    $container->findDefinition($serviceId)
                        ->getTag($this->tag)
                );
            }
        } finally {
            $this->processedTags = null;
            $this->globallyProcessedTags = null;
            $this->tagToServicesMap = null;
        }
    }

    private function registerDynamicTraversers(ContainerBuilder $container, string $serviceId, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagName = $tag['tag'] ?? null;

            assertValueIsOfType($tagName, new Set(['string']), 'tag', $serviceId);
            /** @var string $tagName */

            if ($this->processedTags->hasKey($tagName)) {
                throw new LogicException(sprintf('Tag %s already provided by service %s', $tagName, $this->processedTags->get($tagName)));
            }

            $this->processedTags->put($tagName, $serviceId);

            if ($this->globallyProcessedTags->contains($tagName)) {
                continue ;
            }

            $method = $tag['method'] ?? '__construct';
            $argument = $tag['argument'] ?? 0;
            $isPrioritized = $tag['prioritized'] ?? true;

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
                $argument
            );

            $this->globallyProcessedTags->add($tagName);
        }
    }

    private function collectServicesToInject(ContainerBuilder $container, string $tag, bool $isPrioritized): Vector
    {
        $priorityQueue = new PriorityQueue();

        if (!$this->tagToServicesMap->hasKey($tag)) {
            return new Vector($priorityQueue);
        }

        foreach ($this->tagToServicesMap->get($tag) as $serviceId) {
            foreach ($container->findDefinition($serviceId)->getTag($tag) as $singleTag) {
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
