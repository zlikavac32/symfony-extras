<?php

declare(strict_types=1);

namespace Zlikavac32\SymfonyExtras\Command\Runnable;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runnable part of the command. Method configure() is responsible for configuring input and
 * method run() has same semantics as \Symfony\Component\Console\Command\Command::execute().
 */
interface Runnable
{

    public function configure(InputDefinition $inputDefinition): void;

    public function run(InputInterface $input, OutputInterface $output): int;
}
