<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use Symfony\Component\DependencyInjection\ContainerBuilder;

class ProxiedDecoratorExistsFor extends DecoratorExistsFor
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
    private $proxyFqn;
    /**
     * @var string
     */
    private $decoratorServiceId;

    public function __construct(
        string $serviceId,
        string $decoratorTag,
        string $decoratorTemplateId,
        string $proxyFqn,
        $decoratorArgument,
        int $priority
    ) {
        parent::__construct($serviceId, $decoratorTag, $decoratorTemplateId, $decoratorArgument, $priority);

        $this->decoratorServiceId = $serviceId . '.' . $decoratorTag;
        $this->serviceId = $serviceId;
        $this->proxyFqn = $proxyFqn;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!parent::matches($other)) {
            return false;
        }

        assert($other instanceof ContainerBuilder);

        $gotProxyFqn = $other->findDefinition($this->decoratorServiceId)->getClass();

        if ($gotProxyFqn !== $this->proxyFqn) {
            $this->message = sprintf('Expected proxy FQN %s but got %s', $this->proxyFqn, $gotProxyFqn ?? 'null');

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
        return parent::toString() . sprintf('  (as proxy with FQN %s)', $this->proxyFqn);
    }
}
