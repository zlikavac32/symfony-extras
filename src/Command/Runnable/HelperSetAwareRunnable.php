<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

use Symfony\Component\Console\Helper\HelperSet;

interface HelperSetAwareRunnable extends Runnable
{

    public function useHelperSet(HelperSet $helperSet): void;
}
