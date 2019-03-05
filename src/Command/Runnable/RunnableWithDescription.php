<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

interface RunnableWithDescription extends Runnable
{

    public function description(): string;
}
