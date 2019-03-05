<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

interface RunnableWithVisibility extends Runnable
{

    public function isHidden(): bool;
}
