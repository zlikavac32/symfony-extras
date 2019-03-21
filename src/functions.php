<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection;

use Ds\Set;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Reconstructs tags from their linear representation. Since tag properties must be scalar values,
 * they are passed in a linear form like i(group)_(tag_property) where group is an integer
 * and tag_property is a name of the property.
 *
 * Tag array [{name: foo}, {name: bar, val: 32}] has linear representation as
 * {i0_name: foo, i1_name: bar, i1_val:32}
 *
 * @param array $tagDefinition
 *
 * @return array Reconstructed symfony tags
 */
function reconstructTags(array $tagDefinition): array
{
    $reconstructionMap = [];

    foreach ($tagDefinition as $k => $v) {
        if (!preg_match('/^i(?<group>\d+)_(?<property>.*)/', $k, $matches)) {
            continue;
        }

        ['group' => $group, 'property' => $property] = $matches;

        if (!isset($reconstructionMap[$group])) {
            $reconstructionMap[$group] = [];
        }

        $reconstructionMap[$group][$property] = $v;
    }

    $tags = [];

    ksort($reconstructionMap);

    foreach ($reconstructionMap as $group => $tag) {
        if (!isset($tag['name'])) {
            throw new LogicException(sprintf('No tag name provided for group %d', $group));
        }

        $name = $tag['name'];

        unset($tag['name']);

        if (!isset($tags[$name])) {
            $tags[$name] = [];
        }

        $tags[$name][] = $tag;
    }

    return $tags;
}

function processedItemsSetFromContainer(
    ContainerBuilder $container,
    string $class,
    string $tag,
    string $sufix
): Set {
    // used to share state for multiple passes and will be removed in compile stage
    // since nobody references it
    $serviceKey = sprintf('%s.%s.%s', $class, $tag, $sufix);

    if (!$container->has($serviceKey)) {
        $container->set($serviceKey, new Set());
    }

    $set = $container->get($serviceKey);
    assert($set instanceof Set);

    return $set;
}
