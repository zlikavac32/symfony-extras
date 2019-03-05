<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunnableCommand extends Command
{

    /**
     * @var Runnable
     */
    private $runnable;

    public function __construct(Runnable $runnable, string $name)
    {
        // not a "mistake". It must be before __construct since configure() is called from the parent constructor
        $this->runnable = $runnable;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->runnable->configure($this->getDefinition());

        if ($this->runnable instanceof RunnableWithDescription) {
            $this->setDescription($this->runnable->description());
        }

        if ($this->runnable instanceof RunnableWithHelp) {
            $this->setHelp($this->runnable->help());
        }

        if ($this->runnable instanceof RunnableWithVisibility) {
            $this->setHidden($this->runnable->isHidden());
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->runnable instanceof ConsoleApplicationAwareRunnable) {
            $this->runnable->useApp($this->getApplication());
        }

        if ($this->runnable instanceof HelperSetAwareRunnable) {
            $this->runnable->useHelperSet($this->getHelperSet());
        }

        return $this->runnable->run($input, $output);
    }
}
