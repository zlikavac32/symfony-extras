# Symfony extras

This repository adds a few extra functionality that I miss in the Symfony components. Functionality is currently only DIC and console related.

## Table of contents

1. [Introduction](#introduction)
1. [Installation](#installation)
1. [Usage](#usage)
    1. [Runnable](#runnable)
    1. [Dynamic service decorators and service linker](#dynamic-service-decorators-and-service-linker)
    1. [Dynamic event dispatchers](#dynamic-event-dispatchers)
    1. [Dynamic composite services](#dynamic-composite-services)
1. [Examples](#examples)

## Introduction

Idea of this library to use what you need. No dependency is explicitly required as no code is run by default. It provides few things, like various compiler passes to make a life a bit easier.

## Installation

Recommended installation is through Composer.

```bash
composer require zlikavac32/symfony-extras
```

## Usage

Different concepts exist in this library.

### Runnable

[symfony/console](https://github.com/symfony/console) is a great package, but one can get into trouble when trying to decorate commands. This library defines `\Zlikavac32\SymfonyExtras\Command\Runnable\Runnable` interface which is then injected into `\Zlikavac32\SymfonyExtras\Command\Runnable\RunnableCommand`.

DIC compiler pass that automates this is provided in `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnablePass`.

Read more about this concept in [docs/runnable.md](docs/runnable.md).

### Dynamic service decorators and service linker

Symfony provides out of the box service decoration, but it can get messy when there is to much decoration involved. This library offers two compiler passes to tackle that. First is `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass` and the second is `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass`.

Idea is to define template services that are decorators, and then tag services that need decoration. Since certain decorators need other services or arguments, service linker is used to link services through tags.

Read more about this concept in [docs/dynamic-decorator.md](docs/dynamic-decorator.md) and [docs/linker.md](docs/linker.md).

### Dynamic event dispatchers

Symfony provides `\Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass` to register event dispatchers. Compiler pass that can automate that process is provided in `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\EventDispatcherPass`.

Read more about this concept in [docs/dynamic-event-dispatcher.md](docs/dynamic-event-dispatcher.md).

### Dynamic composite services

Certain services have composite nature in a sense that they require other services either in their constructor, or through method injection. Symfony provides solution in some degree through [tagged service injection](https://symfony.com/blog/new-in-symfony-3-4-simpler-injection-of-tagged-services). This library provides compiler pass in `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicCompositePass` that provides a bit more functionality.

Read more about this concept in [docs/dynamic-composite.md](docs/dynamic-composite.md).

One might say that here is to much `magic` involved, but if you understand how it all works, there is hardly any magic left.

## Examples

Check [examples](examples) folder for examples.
