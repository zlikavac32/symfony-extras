<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorExistsForArgument extends DecoratorExistsFor
{

    private ?string $message = null;
    private $serviceArgument;

    private string $serviceId;

    public function __construct(
        string $serviceId,
        string $decoratorServiceId,
        string $decoratorTemplateId,
        $serviceArgument,
        $decoratorArgument,
        int $priority
    ) {
        parent::__construct($serviceId . '.' . sha1((string)$serviceArgument), $decoratorServiceId,
            $decoratorTemplateId, $decoratorArgument, $priority);

        $this->serviceArgument = $serviceArgument;
        $this->serviceId = $serviceId;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!parent::matches($other)) {
            return false;
        }

        assert($other instanceof ContainerBuilder);

        $expectedReference = $this->serviceId . '.' . sha1((string)$this->serviceArgument);

        $existingArgument = $other->findDefinition($this->serviceId)
            ->getArgument($this->serviceArgument);

        if (!$existingArgument instanceof Reference) {
            $this->message = sprintf('argument %s is of type %s on service %s', $this->serviceArgument,
                Reference::class, $this->serviceId);

            return false;
        }

        $existingArgument = (string)$existingArgument;

        if ($existingArgument !== $expectedReference) {
            $this->message = sprintf('%s has modified argument %s (expected %s but got %s)', $this->serviceId,
                $this->serviceArgument, $expectedReference, $existingArgument);

            return false;
        }

        return true;
    }

    protected function failureDescription($other): string
    {
        if (null !== $this->message) {
            return $this->message;
        }

        return parent::failureDescription($other);
    }

    public function toString(): string
    {
        return parent::toString() . sprintf('  (on argument %s)', $this->serviceArgument);
    }
}
