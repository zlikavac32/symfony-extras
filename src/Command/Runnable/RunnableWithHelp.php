<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

interface RunnableWithHelp extends Runnable
{

    public function help(): string;
}
