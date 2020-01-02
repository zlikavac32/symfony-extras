<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MethodCallExistsFor extends Constraint
{

    private ?string $message = null;

    private string $serviceId;

    private string $expectedMethod;

    private array $expectedArguments;

    private int $positionOfTheCall;

    public function __construct(
        string $serviceId,
        string $expectedMethod,
        array $expectedArguments,
        int $positionOfTheCall
    ) {
        parent::__construct();

        assert($positionOfTheCall > 0);

        $this->serviceId = $serviceId;
        $this->expectedMethod = $expectedMethod;
        $this->expectedArguments = $expectedArguments;
        $this->positionOfTheCall = $positionOfTheCall;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!$other instanceof ContainerBuilder) {
            return false;
        }

        if (!$other->hasDefinition($this->serviceId)) {
            $this->message = sprintf('service %s exists', $this->serviceId);

            return false;
        }

        $methodCalls = $other->getDefinition($this->serviceId)
            ->getMethodCalls();

        if (!isset($methodCalls[$this->positionOfTheCall - 1])) {
            $this->message = sprintf(
                '%d nd/th call exists for service %s',
                $this->positionOfTheCall,
                $this->serviceId
            );

            return false;
        }

        [$method, $arguments] = $methodCalls[$this->positionOfTheCall - 1];

        if ($method !== $this->expectedMethod) {
            $this->message = sprintf(
                'method call %s (got %s) exists on position %d for service %s',
                $this->expectedMethod,
                $method,
                $this->positionOfTheCall,
                $this->serviceId
            );

            return false;
        }

        if ($arguments != $this->expectedArguments) {
            $this->message = sprintf(
                'arguments %s are same as %s',
                $this->exporter->export($arguments),
                $this->exporter->export($this->expectedArguments)
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

        $msg = sprintf(
            'container contains method call %s on service %s with arguments %s',
            $this->expectedMethod,
            $this->serviceId,
            $this->exporter->export($this->expectedArguments)
        );

        if (null === $this->positionOfTheCall) {
            return $msg;
        }

        return $msg . sprintf(' as %d nd/st call', $this->positionOfTheCall);
    }

    public function toString(): string
    {
        $msg = sprintf(
            'contains method call %s on service %s with arguments %s',
            $this->expectedMethod,
            $this->serviceId,
            $this->exporter->export($this->expectedArguments)
        );

        if (null === $this->positionOfTheCall) {
            return $msg;
        }

        return $msg . sprintf(' as %d nd/st call', $this->positionOfTheCall);
    }
}
