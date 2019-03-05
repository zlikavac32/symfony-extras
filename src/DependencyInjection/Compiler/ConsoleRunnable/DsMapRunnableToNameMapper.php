<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable;

use Ds\Map;
use LogicException;

/**
 * Mapper that uses \Ds\Map as input map. Keys are runnable FQNs and values
 * are command names (strings).
 */
abstract class DsMapRunnableToNameMapper implements RunnableToNameMapper
{

    /**
     * @var Map
     */
    private $map;

    /**
     * @param Map|string[] $map
     */
    public function __construct(Map $map)
    {
        $this->map = $map;
    }

    public function map(string $fqn): string
    {
        if (!$this->map->hasKey($fqn)) {
            throw new LogicException(sprintf('Runnable %s not mapped', $fqn));
        }

        return $this->map->get($fqn);
    }
}
