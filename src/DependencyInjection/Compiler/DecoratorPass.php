<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Hashable;
use Ds\Map;
use Ds\Sequence;
use Ds\Set;
use Ds\Vector;
use LogicException;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    /**
     * @var Set|TagReference[]
     */
    private $processedTags;

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

            $processedTags = self::class . '.processed_tags';

            if (!$container->has($processedTags)) {
                $container->set($processedTags, new Set());
            }

            $this->processedTags = $container->get($processedTags);

            $this->buildMapOfDecorators($container);

            $tagsToProcess = $this->resolveTagsToProcess($container);

            $this->decorateServices($container, $tagsToProcess);
        } finally {
            $this->tagToServicesMap = null;
            $this->decoratorDefinitions = null;
            $this->processedTags = null;
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

    /**
     * @param ContainerBuilder $container
     *
     * @return Map|Vector[]
     */
    private function resolveTagsToProcess(ContainerBuilder $container): Map
    {
        $servicesToProcess = new Set();

        foreach ($this->decoratorDefinitions->keys() as $tagName) {
            if ($this->tagToServicesMap->hasKey($tagName)) {
                $servicesToProcess = $servicesToProcess->merge($this->tagToServicesMap->get($tagName));
            }
        }

        $tagsToProcess = new Map();

        foreach ($servicesToProcess as $serviceId) {
            $tagsToProcess->put($serviceId, $this->resolveTagsToProcessFromService($container, $serviceId));
        }

        return $tagsToProcess->filter(function (string $serviceId, Sequence $tagReferences): bool {
            return $tagReferences->isEmpty() === false;
        });
    }

    private function resolveTagsToProcessFromService(ContainerBuilder $container, string $serviceId): Sequence
    {
        $tags = new Vector();

        foreach ($container->findDefinition($serviceId)
                     ->getTags() as $serviceTagName => $serviceTags) {
            $tags->push([$serviceTagName, $serviceTags]);
        }

        return $tags
            ->filter(function (array $tags): bool {
                return $this->decoratorDefinitions->hasKey($tags[0]);
            })
            ->map(function (array $tags) use ($serviceId): TagReference {
                assertOnlyOneTagPerService($tags[1], $tags[0], $serviceId);

                $tag = $tags[1][0];

                $decoratingPriority = $tag['priority'] ?? 0;

                assertValueIsOfType($decoratingPriority, new Set(['integer']), 'priority', $serviceId);

                unset($tag['priority']);

                return new TagReference($tags[0], $serviceId, $decoratingPriority, $tag);
            })
            ->filter(function (TagReference $tagReference): bool {
                return !$this->processedTags->contains($tagReference);
            });
    }

    private function decorateServices(ContainerBuilder $container, Map $tagsToProcess): void
    {
        foreach ($tagsToProcess as $serviceId => $tagReferences) {
            foreach ($tagReferences as $tagReference) {
                assert($tagReference instanceof TagReference);

                $this->decorateServiceForTag($container, $this->decoratorDefinitions->get($tagReference->tagName()),
                    $tagReference);

                $this->processedTags->add($tagReference);
            }
        }
    }

    private function decorateServiceForTag(
        ContainerBuilder $container,
        DecoratorDefinition $decoratorDefinition,
        TagReference $tagReference
    ): void {
        $decoratingServiceId = sprintf('%s.%s', $tagReference->serviceId(), $tagReference->tagName());

        $decoratingService = new ChildDefinition($decoratorDefinition->serviceId());

        $newTags = $container->findDefinition($decoratorDefinition->serviceId())
            ->getTags();

        unset($newTags[$this->tag]);

        $decoratingService
            ->setArgument($decoratorDefinition->argument(), new Reference(sprintf('%s.inner', $decoratingServiceId)))
            ->setDecoratedService($tagReference->serviceId(), null, $tagReference->priority())
            ->setTags(
                $newTags
            );

        foreach (reconstructTags($tagReference->remainingProperties()) as $newTagName => $newTagDefinitions) {
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
class TagReference implements Hashable
{

    /**
     * @var string
     */
    private $tagName;
    /**
     * @var string
     */
    private $serviceId;
    /**
     * @var int
     */
    private $priority;
    /**
     * @var string
     */
    private $hash;
    /**
     * @var array
     */
    private $remainingProperties;

    public function __construct(string $tagName, string $serviceId, int $priority, array $remainingProperties)
    {
        $this->tagName = $tagName;
        $this->serviceId = $serviceId;
        $this->priority = $priority;
        $this->hash = sha1($tagName . ':' . $serviceId);
        $this->remainingProperties = $remainingProperties;
    }

    public function remainingProperties(): array
    {
        return $this->remainingProperties;
    }

    public function tagName(): string
    {
        return $this->tagName;
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritdoc
     */
    function hash(): string
    {
        return $this->hash;
    }

    /**
     * @inheritdoc
     */
    function equals($obj): bool
    {
        if (!$obj instanceof self) {
            return false;
        }

        return $obj->tagName === $this->tagName
            &&
            $obj->serviceId === $this->serviceId;
    }
}

/**
 * @internal
 */
class DecoratorDefinition
{

    /**
     * @var string
     */
    private $serviceId;
    private $argument;

    public function __construct(string $serviceId, $argument)
    {
        $this->serviceId = $serviceId;
        $this->argument = $argument;
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function argument()
    {
        return $this->argument;
    }
}
