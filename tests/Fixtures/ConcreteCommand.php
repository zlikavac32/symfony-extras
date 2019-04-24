<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Fixtures;

class ConcreteCommand implements CommandWithHelp
{

    public function run(): void
    {

    }

    public function help(): string
    {
        return 'help';
    }
}
