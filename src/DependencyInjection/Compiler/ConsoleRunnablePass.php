<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\DependencyInjection\Compiler;

use Ds\Set;
use LogicException;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableCommand;
use Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable\RunnableToNameMapper;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertOnlyOneTagPerService;
use function Zlikavac32\SymfonyExtras\DependencyInjection\assertValueIsOfType;

/**
 * Compiler pass that registers Symfony commands from existing runnable services.
 *
 * Tag must have a property command or mapper, but not both.
 *
 * When command is used, it must be string and with that value Symfony command is
 * registered.
 *
 * When mapper is used, it's expected to be FQN that implements
 * \Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable\RunnableToNameMapper.
 * Instance is dynamically constructed.
 */
class ConsoleRunnablePass implements CompilerPassInterface
{

    /**
     * @var RunnableToNameMapper[]
     */
    private $mappers;
    /**
     * @var string
     */
    private $tag;

    public function __construct(string $tag = 'console_runnable')
    {
        $this->tag = $tag;
    }

    public function process(ContainerBuilder $container): void
    {
        try {
            $this->mappers = [];

            foreach ($container->findTaggedServiceIds($this->tag, true) as $serviceId => $tagDefinition) {
                assertOnlyOneTagPerService($tagDefinition, $this->tag, $serviceId);

                $this->assertValidTagDefinition($tagDefinition[0], $serviceId);

                $this->createCommandService(
                    $serviceId,
                    $this->resolveCommandName($serviceId, $tagDefinition[0], $container),
                    $container
                );
            }
        } finally {
            $this->mappers = null;
        }
    }

    private function resolveCommandName(string $id, array $tagDefinition, ContainerBuilder $container): string
    {
        if (isset($tagDefinition['command'])) {
            return $tagDefinition['command'];
        }

        $mapperClass = $tagDefinition['mapper'];

        if (!isset($this->mappers[$mapperClass])) {
            $this->mappers[$mapperClass] = $this->createMapper($mapperClass, $id, $container);
        }

        $runnableFqn = $container->findDefinition($id)
            ->getClass();

        return $this->mappers[$mapperClass]->map($runnableFqn);
    }

    private function createMapper(
        string $mapper,
        string $serviceId,
        ContainerBuilder $container
    ): RunnableToNameMapper {
        if (!class_exists($mapper)) {
            throw new LogicException(sprintf('Mapper %s does not exist for service %s', $mapper, $serviceId));
        }

        if (!is_subclass_of($mapper, RunnableToNameMapper::class)) {
            throw new LogicException(
                sprintf(
                    'Mapper %s does not implement %s (defined on service %s)',
                    $mapper,
                    RunnableToNameMapper::class,
                    $serviceId
                )
            );
        }

        $container->addResource(
            new FileResource((new ReflectionClass($mapper))->getFileName())
        );

        return new $mapper();
    }

    private function assertValidTagDefinition(array $definition, string $serviceId): void
    {
        $hasCmd = isset($definition['command']);
        $hasMapper = isset($definition['mapper']);

        switch (true) {
            case !$hasCmd && !$hasMapper:
                throw new LogicException(
                    sprintf('Tag %s on service %s does not have a command or mapper option', $this->tag, $serviceId)
                );
            case $hasCmd && $hasMapper:
                throw new LogicException(
                    sprintf('Tag %s on service %s has both command and mapper options', $this->tag, $serviceId)
                );
            case $hasCmd && !$hasMapper:
                assertValueIsOfType($definition['command'], new Set(['string']), 'command', $serviceId);

                break;
            case !$hasCmd && $hasMapper:
                assertValueIsOfType($definition['mapper'], new Set(['string']), 'mapper', $serviceId);

                break;
        }
    }

    private function createCommandService(string $serviceId, string $commandName, ContainerBuilder $container)
    {
        $definition = (new Definition())
            ->setClass(RunnableCommand::class)
            ->setArguments(
                [
                    new Reference($serviceId),
                    $commandName,
                ]
            )
            ->setTags(
                [
                    'console.command' => [
                        [],
                    ],
                ]
            );

        $container->setDefinition(sprintf('%s.command', $serviceId), $definition);
    }
}
