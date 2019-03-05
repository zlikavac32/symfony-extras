<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\TestHelper\PHPUnit\Constraint;

use PHPUnit\Framework\Constraint\Constraint;

class KeyAppearsBeforeOtherKey extends Constraint
{

    /**
     * @var string
     */
    private $message = null;
    private $firstKey;
    private $secondKey;

    public function __construct($firstKey, $secondKey)
    {
        parent::__construct();

        $this->firstKey = $firstKey;
        $this->secondKey = $secondKey;
    }

    protected function matches($other): bool
    {
        $this->message = null;

        if (!is_array($other)) {
            $this->message = sprintf('passed value is array (got %s)', gettype($other));

            return false;
        }

        $indexOfFirstKey = -1;
        $indexOfSecondKey = -2;

        $i = 0;

        foreach (array_keys($other) as $key) {
            if ($this->firstKey === $key) {
                $indexOfFirstKey = $i;
            } elseif ($this->secondKey === $key) {
                $indexOfSecondKey = $i;
            }

            $i++;
        }

        if ($indexOfFirstKey < 0) {
            $this->message = sprintf('%s exists in array', $this->firstKey);

            return false;
        } elseif ($indexOfSecondKey < 0) {
            $this->message = sprintf('%s exists in array', $this->secondKey);

            return false;
        } elseif ($indexOfSecondKey < $indexOfFirstKey) {
            $this->message = sprintf(
                '%s (position %d) is before %s (%d)',
                $this->firstKey,
                $indexOfFirstKey,
                $this->secondKey,
                $indexOfSecondKey
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
            'array has key %s before key %s',
            $this->firstKey,
            $this->secondKey
        );
    }

    public function toString(): string
    {
        return sprintf(
            'key %s is before key %s',
            $this->firstKey,
            $this->secondKey
        );
    }
}
