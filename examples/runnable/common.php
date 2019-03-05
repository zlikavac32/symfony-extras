<?php

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

function createContainer(): ContainerBuilder
{
    $container = new ContainerBuilder();

    $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);

    return $container;
}

function createApplication(ContainerInterface $container): Application
{
    $application = new Application();

    foreach ($container->getParameter('console.command.ids') as $commandId) {
        $application->add($container->get($commandId));
    }

    return $application;
}
