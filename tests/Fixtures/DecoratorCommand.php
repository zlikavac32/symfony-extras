<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Fixtures;

class DecoratorCommand implements Command
{

    private Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function run(): void
    {

    }
}
