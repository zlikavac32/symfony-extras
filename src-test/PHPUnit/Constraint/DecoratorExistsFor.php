<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorExistsFor extends Constraint
{

    /**
     * @var string
     */
    private $message = null;
    /**
     * @var string
     */
    private $serviceId;
    /**
     * @var string
     */
    private $decoratorServiceId;
    private $decoratorArgument;
    /**
     * @var int
     */
    private $priority;
    /**
     * @var string
     */
    private $decoratorTemplateId;

    public function __construct(
        string $serviceId,
        string $decoratorTag,
        string $decoratorTemplateId,
        $decoratorArgument,
        int $priority
    ) {
        parent::__construct();

        $this->serviceId = $serviceId;
        $this->decoratorServiceId = $serviceId . '.' . $decoratorTag;
        $this->decoratorArgument = $decoratorArgument;
        $this->priority = $priority;
        $this->decoratorTemplateId = $decoratorTemplateId;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!$other instanceof ContainerBuilder) {
            return false;
        }

        if (!$other->hasDefinition($this->decoratorServiceId)) {
            $this->message = sprintf('decorator service %s exist', $this->decoratorServiceId);

            return false;
        }

        $decoratorDefinition = $other->getDefinition($this->decoratorServiceId);

        if (!$decoratorDefinition instanceof ChildDefinition) {
            $this->message = sprintf('%s is not a child definition', $this->decoratorServiceId);

            return false;
        }

        if ($decoratorDefinition->getParent() !== $this->decoratorTemplateId) {
            $this->message = sprintf(
                '%s decorator service does not have %s as parent (has %s)',
                $this->decoratorTemplateId,
                $this->decoratorServiceId,
                $decoratorDefinition->getParent()
            );

            return false;
        }

        $arguments = $decoratorDefinition->getArguments();

        if (!isset($arguments[$this->decoratorArgument])) {
            $this->message = sprintf('%s has argument %s', $this->decoratorServiceId, $this->decoratorArgument);

            return false;
        }

        $argument = $arguments[$this->decoratorArgument];

        $expectedReference = sprintf('%s.inner', $this->decoratorServiceId);

        if (!$argument instanceof Reference || $expectedReference !== (string)$argument) {
            $this->message = sprintf('argument %s is reference to %s', $this->decoratorArgument, $expectedReference);

            return false;
        }

        if ($decoratorDefinition->getDecoratedService() !== [$this->serviceId, null, $this->priority]) {
            $this->message = sprintf(
                '%s does not decorate %s (decorates: %s)',
                $this->decoratorServiceId,
                $this->serviceId,
                $this->exporter->export($decoratorDefinition->getDecoratedService())
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
            'container contains decorator %s (argument %s) for %s',
            $this->decoratorServiceId,
            $this->decoratorArgument,
            $this->serviceId
        );
    }

    public function toString(): string
    {
        return sprintf(
            'contains decorator %s (argument %s) for %s',
            $this->decoratorServiceId,
            $this->decoratorArgument,
            $this->serviceId
        );
    }
}
