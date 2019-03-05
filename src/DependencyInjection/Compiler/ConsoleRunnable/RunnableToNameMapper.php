<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable;

/**
 * When runnable instances are registered as resources, instance of this interface
 * maps runnable FQN into command name.
 */
interface RunnableToNameMapper
{

    public function map(string $fqn): string;
}
