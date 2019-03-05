<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection;

use Ds\Set;
use LogicException;
use Symfony\Component\DependencyInjection\Definition;

function assertOnlyOneTagPerService(array $tags, string $tag, string $id): void
{
    if (count($tags) === 1) {
        return;
    }

    throw new LogicException(sprintf('Service %s has multiple %s tags which is not allowed', $id, $tag));
}

function assertDefinitionIsAbstract(Definition $definition, string $serviceId): void
{
    if ($definition->isAbstract()) {
        return;
    }

    throw new LogicException(sprintf('Expected service %s to be defined as abstract', $serviceId));
}

function assertDefinitionIsNotAbstract(Definition $definition, string $serviceId): void
{
    if ($definition->isAbstract()) {
        throw new LogicException(sprintf('Expected service %s to be defined as non-abstract', $serviceId));
    }

}

function assertValueIsOfType($value, Set $types, string $sourceName, string $serviceId): void
{
    if ($types->contains(gettype($value))) {
        return;
    }

    throw new LogicException(
        sprintf('Expected %s to be any of [%s] in service %s', $sourceName, $types->join(', '), $serviceId)
    );
}
