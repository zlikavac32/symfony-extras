<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

use Symfony\Component\Console\Application;

interface ConsoleApplicationAwareRunnable extends Runnable
{

    public function useApp(Application $application): void;
}
