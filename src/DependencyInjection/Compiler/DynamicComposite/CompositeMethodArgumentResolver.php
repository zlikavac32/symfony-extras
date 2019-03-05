<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicComposite;

use Ds\Map;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Instances are responsible for configuring additional arguments in method injection.
 *
 * Resolved argument names must not be in conflict with configured compiler pass argument.
 * So if compiler pass uses argument $arg, implementation used there must never return
 * $arg key. Failure to do so will result with the exception from the compiler pass.
 *
 * Implementations must not have a required value in their constructor since dynamically
 * constructed without arguments.
 */
interface CompositeMethodArgumentResolver
{

    public function resolveFor(ContainerBuilder $container, string $serviceId, Map $tagProperties): array;

    public function finish(): void;
}
