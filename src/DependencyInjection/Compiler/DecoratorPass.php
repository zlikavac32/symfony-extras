<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Hashable;
use Ds\Map;
use Ds\Sequence;
use Ds\Set;
use Ds\Vector;
use LogicException;
use ReflectionClass;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\NSBDecorators\Proxy;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertDefinitionIsAbstract;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertValueIsOfType;
use function Zlikavac32\SymfonyExtras\DependencyInjection\buildMapOfTagsAndServiceIds;
use function Zlikavac32\SymfonyExtras\DependencyInjection\processedItemsSetFromContainer;
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

    private string $tag;
    /**
     * @var Set[]|string[][]
     */
    private ?Map $tagToServicesMap;
    /**
     * @var DecoratorDefinition[]
     */
    private ?Map $decoratorDefinitions;
    /**
     * @var TagReference[]
     */
    private ?Set $processedTags;

    public function __construct(string $tag = 'decorator')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container): void
    {
        $this->tagToServicesMap = buildMapOfTagsAndServiceIds($container);

        $this->decoratorDefinitions = new Map();

        try {
            if (!$this->tagToServicesMap->hasKey($this->tag)) {
                return;
            }

            $this->processedTags = processedItemsSetFromContainer($container, self::class, $this->tag, 'processed_tags');

            $this->buildMapOfDecorators($container);

            $tagsToProcess = $this->resolveTagsToProcess($container);

            $this->decorateServices($container, $tagsToProcess);
        } finally {
            $this->tagToServicesMap = null;
            $this->decoratorDefinitions = null;
            $this->processedTags = null;
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
            ->map(function (array $tags) use ($serviceId): Sequence {
                $collectedTagReferences = new Vector();

                $processedTagReferences = new Set();

                foreach ($tags[1] as $tag) {
                    $decoratingPriority = $tag['priority'] ?? 0;

                    assertValueIsOfType($decoratingPriority, new Set(['integer']), 'priority', $serviceId);

                    unset($tag['priority']);

                    $argument = null;

                    if (isset($tag['argument'])) {
                        $argument = $tag['argument'];

                        assertValueIsOfType($argument, new Set(['integer', 'string']), 'argument', $serviceId);

                        unset($tag['argument']);
                    }

                    $isProxied = $tag['proxy'] ?? false;

                    assertValueIsOfType($isProxied, new Set(['boolean']), 'proxy', $serviceId);

                    if ($isProxied && !class_exists(Proxy::class)) {
                        throw new LogicException(sprintf(
                            'Class %s missing. Please install zlikavac32/nsb-decorators or don\'t use proxied decorators',
                            Proxy::class
                        ));
                    }

                    $tagReference = new TagReference($tags[0], $serviceId, $decoratingPriority, $isProxied, $argument,
                        $tag);

                    if ($processedTagReferences->contains($tagReference)) {
                        throw new LogicException(
                            sprintf('Only one tag %s allowed on service %s', $tagReference->tagName(), $tagReference->serviceId()) .
                            ($tagReference->hasArgument() ? sprintf(' and argument %s', $tagReference->argument()) : '')
                        );
                    }

                    $collectedTagReferences->push($tagReference);
                    $processedTagReferences->add($tagReference);
                }

                return $collectedTagReferences;
            })
            ->reduce(function (Vector $collected, Vector $tagReferences): Sequence {
                return $collected->merge($tagReferences);
            }, new Vector())
            ->filter(function (TagReference $tagReference): bool {
                return !$this->processedTags->contains($tagReference);
            });
    }

    private function decorateServices(ContainerBuilder $container, Map $tagsToProcess): void
    {
        foreach ($tagsToProcess as $tagReferences) {
            assert($tagReferences instanceof Sequence);

            foreach ($tagReferences as $tagReference) {
                assert($tagReference instanceof TagReference);

                $serviceToDecorate = $tagReference->serviceId();

                if ($tagReference->hasArgument()) {
                    $serviceToDecorate = $this->resolveAliasForServiceArgument($container, $tagReference);
                }

                $this->decorateServiceForTag(
                    $container,
                    $serviceToDecorate,
                    $this->decoratorDefinitions->get($tagReference->tagName()),
                    $tagReference
                );

                $this->processedTags->add($tagReference);
            }
        }
    }

    private function resolveAliasForServiceArgument(ContainerBuilder $container, TagReference $tagReference): string
    {
        $aliasServiceId = $tagReference->serviceId() . '.' . sha1((string) $tagReference->argument());

        if ($container->hasAlias($aliasServiceId)) {
            return $aliasServiceId;
        }

        $serviceDefinition = $container->findDefinition($tagReference->serviceId());

        $arguments = $serviceDefinition->getArguments();

        $decoratedArgument = $tagReference->argument();

        if (
            !isset($arguments[$decoratedArgument])
            ||
            !$arguments[$decoratedArgument] instanceof Reference
        ) {
            throw new LogicException(
                sprintf(
                    'Argument %s must be explicitly defined to reference some service (issue on service %s)',
                    $decoratedArgument,
                    $tagReference->serviceId()
                )
            );
        }

        $container->setAlias($aliasServiceId, (string)$arguments[$decoratedArgument]);
        $serviceDefinition->setArgument($decoratedArgument, new Reference($aliasServiceId));

        return $aliasServiceId;
    }

    private function decorateServiceForTag(
        ContainerBuilder $container,
        string $decoratedServiceId,
        DecoratorDefinition $decoratorDefinition,
        TagReference $tagReference
    ): void {
        $decoratingServiceId = sprintf('%s.%s', $decoratedServiceId, $tagReference->tagName());

        $decoratingService = new ChildDefinition($decoratorDefinition->serviceId());

        $decoratorServiceDefinition = $container->findDefinition($decoratorDefinition->serviceId());

        if ($tagReference->isProxied()) {
            $decoratorClass = $decoratorServiceDefinition->getClass();
            $subjectClass = $container->findDefinition($decoratedServiceId)
                ->getClass();

            $toAssert = [
                $decoratorDefinition->serviceId() => $decoratorClass,
                $decoratedServiceId               => $subjectClass,
            ];

            foreach ($toAssert as $serviceId => $class) {
                if ($class) {
                    continue;
                }

                throw new LogicException(sprintf('Class must be defined on service %s when using proxied decorators',
                    $serviceId));
            }

            $argument = $this->resolveArgumentNameFromClass($decoratorClass, $decoratorDefinition);

            $decoratingService->setClass(Proxy::createFQNForProxyClass($decoratorClass, $subjectClass, $argument));
        }

        $newTags = $decoratorServiceDefinition->getTags();

        unset($newTags[$this->tag]);

        $decoratingService
            ->setArgument($decoratorDefinition->argument(), new Reference(sprintf('%s.inner', $decoratingServiceId)))
            ->setDecoratedService($decoratedServiceId, null, $tagReference->priority())
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

    private function resolveArgumentNameFromClass(string $fqn, DecoratorDefinition $decoratorDefinition): string
    {
        $argument = $decoratorDefinition->argument();

        $constructorMethod = (new ReflectionClass($fqn))
            ->getConstructor();

        $parameters = null === $constructorMethod ? [] : $constructorMethod->getParameters();

        if (is_int($argument)) {
            if (!isset($parameters[$argument])) {
                throw new LogicException(sprintf('Argument %d not found on service %s (class %s)', $argument,
                    $decoratorDefinition->serviceId(), $fqn));
            }

            return $parameters[$argument]->getName();
        }

        if (strlen($argument) < 2 || $argument[0] !== '$') {
            throw new LogicException(sprintf('Argument %s is not valid on service %s (class %s)', $argument,
                $decoratorDefinition->serviceId(), $fqn));
        }

        $argument = substr($argument, 1);

        $foundParam = array_filter($parameters, function (ReflectionParameter $parameter) use ($argument): bool {
            return $parameter->getName() === $argument;
        });

        if (count($foundParam) !== 1) {
            throw new LogicException(sprintf('Argument $%s not found on service %s (class %s)', $argument, $decoratorDefinition->serviceId(), $fqn));
        }

        return $argument;
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
    private $argument;
    /**
     * @var bool
     */
    private $isProxied;

    public function __construct(
        string $tagName,
        string $serviceId,
        int $priority,
        bool $isProxied,
        $argument,
        array $remainingProperties
    ) {
        assert(is_null($argument) || is_int($argument) || is_string($argument));

        $this->tagName = $tagName;
        $this->serviceId = $serviceId;
        $this->priority = $priority;
        $this->hash = sha1($tagName . ':' . $serviceId . ':' . $argument);
        $this->remainingProperties = $remainingProperties;
        $this->argument = $argument;
        $this->isProxied = $isProxied;
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

    public function isProxied(): bool
    {
        return $this->isProxied;
    }

    /**
     * @return int|string
     */
    public function argument()
    {
        if (null === $this->argument) {
            throw new LogicException('Argument does not exist for this reference. Forgot to call hasArgument()?');
        }

        return $this->argument;
    }

    public function hasArgument(): bool
    {
        return null !== $this->argument;
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
            $obj->serviceId === $this->serviceId
            &&
            $obj->argument === $this->argument;
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
