<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Map;
use Ds\Set;
use LogicException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertDefinitionIsAbstract;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertOnlyOneTagPerService;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertValueIsOfType;
use function Zlikavac32\SymfonyExtras\DependencyInjection\reconstructTags;

/**
 * Registers dynamic decorators and decorates services that requested decoration.
 *
 * Tag must have a property tag which is tag used by services that need decoration.
 *
 * Decorations can be prioritized using priority property.
 *
 * Additional tags that should be passed into decorating service can be provided.
 */
class DecoratorPass implements CompilerPassInterface
{

    /**
     * @var string
     */
    private $tag;
    /**
     * @var Map|Set[]|string[][]
     */
    private $tagToServicesMap;
    /**
     * @var Map|DecoratorDefinition[]
     */
    private $decoratorDefinitions;

    public function __construct(string $tag = 'decorator')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container): void
    {
        $this->tagToServicesMap = new Map();

        $this->buildMapOfTagsAndServices($container);

        try {
            if (!$this->tagToServicesMap->hasKey($this->tag)) {
                return;
            }

            // used to share state for multiple passes and will be removed in compile stage
            // since nobody references it
            $decoratorDefinitions = self::class . '.decorator_definitions';

            if (!$container->has($decoratorDefinitions)) {
                $container->set($decoratorDefinitions, new Map());
            }

            $this->decoratorDefinitions = $container->get($decoratorDefinitions);

            $this->buildMapOfDecorators($container);

            $this->decorateServices($container);
        } finally {
            $this->tagToServicesMap = null;
            $this->decoratorDefinitions = null;
        }
    }

    private function buildMapOfTagsAndServices(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            foreach (array_keys($definition->getTags()) as $tagName) {
                if (!$this->tagToServicesMap->hasKey($tagName)) {
                    $this->tagToServicesMap->put($tagName, new Set());
                }

                $this->tagToServicesMap->get($tagName)
                    ->add($serviceId);
            }
        }
    }

    private function buildMapOfDecorators(ContainerBuilder $container): void
    {
        foreach ($this->tagToServicesMap->get($this->tag) as $serviceId) {
            $definition = $container->findDefinition($serviceId);
            $tags = $definition->getTags()[$this->tag];

            foreach ($tags as $tag) {
                assertDefinitionIsAbstract($definition, $serviceId);

                /** @var string $tagName */
                $tagName = $tag['tag'] ?? null;

                assertValueIsOfType($tagName, new Set(['string']), 'tag', $serviceId);

                $argument = $tag['argument'] ?? 0;

                assertValueIsOfType($argument, new Set(['string', 'integer']), 'argument', $serviceId);

                if (!$this->decoratorDefinitions->hasKey($tagName)) {
                    $this->decoratorDefinitions->put($tagName, new DecoratorDefinition($serviceId, $argument));

                    continue;
                }

                $decoratorDefinition = $this->decoratorDefinitions->get($tagName);
                assert($decoratorDefinition instanceof DecoratorDefinition);

                if ($decoratorDefinition->serviceId() !== $serviceId) {
                    throw new LogicException(
                        sprintf('Tag %s already provided by %s (issue found on service %s)', $tagName, $decoratorDefinition->serviceId(), $serviceId)
                    );
                }
            }
        }
    }

    private function decorateServices(ContainerBuilder $container): void
    {
        $usedDecorators = $this->decoratorDefinitions->filter(function (string $tagName): bool {
            return $this->tagToServicesMap->hasKey($tagName);
        });

        foreach ($usedDecorators as $tagName => $decoratorDefinition) {
            assert($decoratorDefinition instanceof DecoratorDefinition);

            foreach ($this->tagToServicesMap->get($tagName)
                         ->diff($decoratorDefinition->processedServices()) as $serviceToProcess) {

                $this->decorateService(
                    $container,
                    $container->findDefinition($serviceToProcess),
                    $serviceToProcess
                );
            }
        }
    }

    private function decorateService(ContainerBuilder $container, Definition $definition, string $serviceId): void
    {
        foreach ($definition->getTags() as $serviceTagName => $tags) {
            if (!$this->decoratorDefinitions->hasKey($serviceTagName)) {
                continue;
            }

            assertOnlyOneTagPerService($tags, $serviceTagName, $serviceId);

            $decoratorDefinition = $this->decoratorDefinitions->get($serviceTagName);
            assert($decoratorDefinition instanceof DecoratorDefinition);

            if ($decoratorDefinition->processedServices()->contains($serviceId)) {
                continue;
            }

            $tag = $tags[0];

            $this->decorateServiceForTag(
                $container,
                $serviceTagName,
                $decoratorDefinition->serviceId(),
                $decoratorDefinition->argument(),
                $serviceId,
                $tag
            );

            $decoratorDefinition->processedServices()->add($serviceId);
        }
    }

    private function decorateServiceForTag(
        ContainerBuilder $container,
        string $tagName,
        string $templateServiceId,
        $argument,
        string $serviceId,
        array $tag
    ): void {
        $decoratingPriority = $tag['priority'] ?? 0;

        assertValueIsOfType($decoratingPriority, new Set(['integer']), 'priority', $serviceId);

        $decoratingServiceId = sprintf('%s.%s', $serviceId, $tagName);

        $decoratingService = new ChildDefinition($templateServiceId);

        $newTags = $container->findDefinition($templateServiceId)
            ->getTags();

        unset($newTags[$this->tag]);

        $decoratingService
            ->setArgument($argument, new Reference(sprintf('%s.inner', $decoratingServiceId)))
            ->setDecoratedService($serviceId, null, $decoratingPriority)
            ->setTags(
                $newTags
            );

        foreach (reconstructTags($tag) as $newTagName => $newTagDefinitions) {
            foreach ($newTagDefinitions as $newTagDefinition) {
                $decoratingService->addTag($newTagName, $newTagDefinition);
            }
        }

        $container->setDefinition($decoratingServiceId, $decoratingService);
    }
}

/**
 * @internal
 */
class DecoratorDefinition {

    /**
     * @var string
     */
    private $serviceId;
    private $argument;
    /**
     * @var Set
     */
    private $processedServices;

    public function __construct(string $serviceId, $argument)
    {
        $this->serviceId = $serviceId;
        $this->argument = $argument;
        $this->processedServices = new Set();
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function argument()
    {
        return $this->argument;
    }

    public function processedServices(): Set
    {
        return $this->processedServices;
    }
}
