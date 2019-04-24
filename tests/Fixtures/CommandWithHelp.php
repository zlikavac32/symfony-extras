<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Tests\Fixtures;

interface CommandWithHelp extends Command
{

    public function help(): string;
}
