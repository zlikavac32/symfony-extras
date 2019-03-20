<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\An;
use Ds\Hashable;
use Ds\Map;
use Ds\Set;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertDefinitionIsNotAbstract;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertOnlyOneTagPerService;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertValueIsOfType;

/**
 * Links services with other services or parameters. Linker can be defined
 * directly or indirectly.
 *
 * Direct linking links some service defined by the provider with service
 * that defined tag.
 *
 * Indirect linking links some service defined by the provider, any symfony service
 * or some parameter with services that have resolver tag defined.
 *
 * Must be after parameter resolving.
 */
class ServiceLinkerPass implements CompilerPassInterface
{

    /**
     * @var string
     */
    private $tag;
    /**
     * @var Map|Map[]|Reference[][]
     */
    private $providers;
    /**
     * @var Set|ProcessedArgument[]
     */
    private $processedArguments;

    public function __construct(string $tag = 'linker')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container): void
    {
        try {
            $this->providers = new Map();

            $processedArguments = sprintf('%s.%s.linked_arguments', self::class, $this->tag);

            if (!$container->has($processedArguments)) {
                $container->set($processedArguments, new Set());
            }

            $this->processedArguments = $container->get($processedArguments);

            foreach ($container->findTaggedServiceIds($this->tag) as $serviceId => $tags) {
                $this->linkServices($container, $serviceId, $tags);
            }
        } finally {
            $this->processedArguments = null;
            $this->providers = null;
        }
    }

    private function linkServices(ContainerBuilder $container, string $serviceId, array $tags): void
    {
        $existingArguments = new Set();

        foreach ($tags as $tag) {
            $argument = $tag['argument'] ?? 0;

            assertValueIsOfType($argument, new Set(['integer', 'string']), 'argument', $serviceId);

            if ($existingArguments->contains($argument)) {
                throw new LogicException(sprintf('Argument %s already defined on service %s', $argument, $serviceId));
            }

            $existingArguments->add($argument);

            $processedArgument = new ProcessedArgument($serviceId, $argument);

            if ($this->processedArguments->contains($processedArgument)) {
                continue;
            }

            $this->processedArguments->add($processedArgument);

            if (isset($tag['provider_tag'])) {
                $providerTag = $tag['provider_tag'];

                /** @var string $providerTag */
                assertValueIsOfType($providerTag, new Set(['string']), 'provider_tag', $serviceId);

                $this->ensureProvidersDiscoveredFor($container, $providerTag);

                $provider = $tag['provider'] ?? null;

                assertValueIsOfType($provider, new Set(['string']), 'provider', $serviceId);

                /** @var Map|Reference[] $providers */
                $providers = $this->providers->get($providerTag);

                if ($providers->hasKey($provider)) {
                    $container->findDefinition($serviceId)
                        ->setArgument($argument, $providers->get($provider));

                    continue;
                }

                throw new LogicException(sprintf('No service provides %s (tag %s)', $provider, $providerTag));
            }

            $argumentResolverTag = $tag['argument_resolver_tag'] ?? null;

            /** @var string $argumentResolverTag */
            assertValueIsOfType($argumentResolverTag, new Set(['string']), 'argument_resolver_tag', $serviceId);

            $this->linkService(
                $container,
                $argument,
                $argumentResolverTag
            );
        }
    }

    private function linkService(
        ContainerBuilder $container,
        $argument,
        string $consumerTag
    ): void {

        foreach ($container->findTaggedServiceIds($consumerTag) as $serviceId => $tags) {
            $serviceDefinition = $container->findDefinition($serviceId);

            assertDefinitionIsNotAbstract($serviceDefinition, $serviceId);
            assertOnlyOneTagPerService($tags, $consumerTag, $serviceId);

            $this->linkDependingOnStrategy(
                $container,
                $serviceId,
                $argument,
                $consumerTag,
                $tags
            );
        }
    }

    private function linkDependingOnStrategy(
        ContainerBuilder $container,
        string $serviceId,
        $argument,
        string $consumerTag,
        array $tags
    ): void {
        $consumerKey = $tags[0]['provider'] ?? null;
        $consumerParam = $tags[0]['param'] ?? null;
        $consumerService = $tags[0]['service'] ?? null;

        switch (true) {
            case is_string($consumerKey) + is_string($consumerParam) + is_string($consumerService) !== 1:
                throw new LogicException(
                    sprintf(
                        'Expected only one of [provider, param, service] to be of type string on service %s for tag %s',
                        $serviceId,
                        $consumerTag
                    )
                );
            case is_string($consumerKey):
                $providerTag = $tags[0]['provider_tag'] ?? null;

                /** @var string $providerTag */
                assertValueIsOfType($providerTag, new Set(['string']), 'provider_tag', $serviceId);

                $this->ensureProvidersDiscoveredFor($container, $providerTag);

                /** @var Map|Reference[] $providers */
                $providers = $this->providers->get($providerTag);

                if ($providers->hasKey($consumerKey)) {

                    $container->findDefinition($serviceId)
                        ->setArgument(
                            $argument,
                            $providers->get($consumerKey)
                        );

                    break;
                }

                throw new LogicException(sprintf('No service provides %s (tag %s)', $consumerKey, $providerTag));
            case is_string($consumerParam):
                $container->findDefinition($serviceId)
                    ->setArgument(
                        $argument,
                        $container->getParameter($consumerParam)
                    );

                break;
            case is_string($consumerService):
                $container->findDefinition($serviceId)
                    ->setArgument(
                        $argument,
                        new Reference($consumerService)
                    );

                break;
        }
    }

    private function ensureProvidersDiscoveredFor(ContainerBuilder $container, string $providerTag): void
    {
        if ($this->providers->hasKey($providerTag)) {
            return;
        }

        $servicesProviderTags = $container->findTaggedServiceIds($providerTag);

        if (count($servicesProviderTags) === 0) {
            throw new LogicException(sprintf('No providers with tag %s found', $providerTag));
        }

        $providers = new Map();

        foreach ($servicesProviderTags as $serviceId => $tags) {
            $this->discoverProvidersFromTags($providers, $tags, $providerTag, $serviceId);
        }

        $this->providers->put($providerTag, $providers);
    }

    private function discoverProvidersFromTags(
        Map $providers,
        array $tags,
        string $providerTag,
        string $serviceId
    ): void {
        foreach ($tags as $tag) {
            $key = $tag['provides'] ?? null;

            assertValueIsOfType($key, new Set(['string']), 'provides', $serviceId);
            /** @var string $key */

            if ($providers->hasKey($key)) {
                throw new LogicException(
                    sprintf(
                        'Another service (%s) already provides %s (tag %s)',
                        (string)$providers->get($key),
                        $key,
                        $providerTag
                    )
                );
            }

            $providers->put($key, new Reference($serviceId));
        }
    }
}

/**
 * @internal
 */
class ProcessedArgument implements Hashable
{

    /**
     * @var string
     */
    private $serviceId;
    private $argument;

    public function __construct(string $serviceId, $argument)
    {
        assert(is_int($argument) || is_string($argument));

        $this->serviceId = $serviceId;
        $this->argument = $argument;
    }

    /**
     * @inheritdoc
     */
    function hash(): string
    {
        return sha1("{$this->serviceId}:{$this->argument}");
    }

    /**
     * @inheritdoc
     */
    function equals($obj): bool
    {
        if (!$obj instanceof self) {
            return false;
        }

        return $obj->serviceId === $this->serviceId
            &&
            $obj->argument === $this->argument;
    }
}
