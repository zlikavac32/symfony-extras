<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Map;
use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use function Zlikavac32\SymfonyExtras\DependencyInjection\processedItemsSetFromContainer;

/**
 * Registers service as event dispatcher and links proper
 * listeners and subscribers.
 */
class EventDispatcherPass implements CompilerPassInterface
{

    /**
     * @var string
     */
    private $tag;

    public function __construct(string $tag = 'event_dispatcher')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container)
    {
        $registeredDispatchers = $this->findRegisteredDispatchers($container);

        $processedTags = processedItemsSetFromContainer($container, self::class, $this->tag, 'events');

        foreach ($registeredDispatchers as [$serviceId, $listenerTag, $subscriberTag]) {
            if ($processedTags->contains($listenerTag)) {
                continue;
            }

            (new RegisterListenersPass($serviceId, $listenerTag, $subscriberTag))->process($container);

            $processedTags->add($listenerTag);
        }
    }

    private function findRegisteredDispatchers(ContainerBuilder $container): array
    {
        $dispatchers = [];

        $usedTags = new Map();

        foreach ($container->findTaggedServiceIds($this->tag) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $this->assertValidTagDefinition($tag, $serviceId);

                foreach ([$tag['listener_tag'], $tag['subscriber_tag']] as $newTag) {
                    if ($usedTags->hasKey($newTag)) {
                        throw new LogicException(
                            sprintf('Tag %s already used by %s', $newTag, $usedTags->get($newTag))
                        );
                    }

                    $usedTags->put($newTag, $serviceId);
                }

                $dispatchers[] = [
                    $serviceId,
                    $tag['listener_tag'],
                    $tag['subscriber_tag'],
                ];
            }
        }

        return $dispatchers;
    }

    private function assertValidTagDefinition(array $tag, string $serviceId)
    {
        foreach (['listener_tag', 'subscriber_tag'] as $key) {
            if (isset($tag[$key]) && is_string($key)) {
                continue;
            }

            throw new LogicException(
                sprintf('Invalid/missing tag option "%s" on service "%s"', $key, $serviceId)
            );
        }

        if ($tag['listener_tag'] === $tag['subscriber_tag']) {
            throw new LogicException(
                sprintf('Values for listener_tag and subscriber_tag are the same on service %s', $serviceId)
            );
        }
    }
}
