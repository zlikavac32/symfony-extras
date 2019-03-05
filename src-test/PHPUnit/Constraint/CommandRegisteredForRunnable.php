<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zlikavac32\SymfonyExtras\Command\Runnable\RunnableCommand;

class CommandRegisteredForRunnable extends Constraint
{

    /**
     * @var string
     */
    private $runnableService;
    /**
     * @var string
     */
    private $expectedName;
    /**
     * @var string
     */
    private $message = null;

    public function __construct(string $runnableService, string $expectedName)
    {
        parent::__construct();

        $this->runnableService = $runnableService;
        $this->expectedName = $expectedName;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!$other instanceof ContainerBuilder) {
            return false;
        }

        $expectedCommandDefinition = sprintf('%s.command', $this->runnableService);

        if (!$other->hasDefinition($expectedCommandDefinition)) {
            $this->message = sprintf('contains %s definition', $expectedCommandDefinition);

            return false;
        }

        $definition = $other->getDefinition($expectedCommandDefinition);

        if (!$definition->hasTag('console.command')) {
            $this->message = sprintf('%s contains tag console.command', $expectedCommandDefinition);

            return false;
        }

        if ($definition->getClass() !== RunnableCommand::class) {
            $this->message = sprintf('%s is instance of class %s', $expectedCommandDefinition, RunnableCommand::class);

            return false;
        }

        $arguments = $definition->getArguments();

        if (!isset($arguments[0]) || !isset($arguments[1]) || count($arguments) !== 2) {
            $this->message = sprintf('%s has two arguments', $expectedCommandDefinition);

            return false;
        }

        $reference = $arguments[0];

        if (!$reference instanceof Reference || $this->runnableService !== (string)$reference) {
            $this->message = sprintf(
                '%s has reference to %s as first argument',
                $expectedCommandDefinition,
                $this->runnableService
            );

            return false;
        }

        $name = $arguments[1];

        if ($name !== $this->expectedName) {
            $this->message = sprintf(
                '%s has value %s as second argument',
                $expectedCommandDefinition,
                $this->expectedName
            );

            return false;
        }

        return true;
    }

    protected function failureDescription($other): string
    {
        if (null !== $this->message) {
            return $this->message;
        }

        return sprintf(
            'container contains command %s for runnable %s',
            $this->expectedName,
            $this->runnableService
        );
    }

    public function toString(): string
    {
        return sprintf('contains command %s for runnable %s', $this->expectedName, $this->runnableService);
    }
}
