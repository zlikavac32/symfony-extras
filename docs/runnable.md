# Symfony Console Runnable

To create Symfony command, one must first extend `\Symfony\Component\Console\Command\Command` and implement method `execute()` (also `configure()` if needed). The command name is passed through the constructor.

By extending `Command` class, we inherit all of the logic in it and that can cause troubles when trying to decorate commands.

For example, we can have various services injected into command to provide lock file functionality. But that means we have to inject them into command and call them from the command.

Other way would be to have a decorating command which wraps decorated command within lock file block. By doing so, initial command no longer knows it must be run exclusively. That has been delegated to the decorating command.

Writing unit test is also something where we can encounter issues. Command itself does a lot of processing behind the curtain which means we have to mock/stub everything properly.

By preferring composition over inheritance, those issues are mitigated. Therefore, this library ads one level to the Symfony commands and that level is `runnable`.

Runnable is defined with `\Zlikavac32\SymfonyExtras\Command\Runnable\Runnable` interface which has two methods, `configure(\Symfony\Component\Console\Input\InputDefinition $inputDefinition)` and `execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)`.

Method `configure()` is responsible for input interface configuration, while method `run()` has same semantic as `\Symfony\Component\Console\Command\Command::execute()`.

Command `\Zlikavac32\SymfonyExtras\Command\Runnable\RunnableCommand` wraps runnable and runs it as a command.

Additional interfaces that support further integration with Symfony commands are listed below.

- `\Zlikavac32\SymfonyExtras\Command\Runnable\ConsoleApplicationAwareRunnable`
- `\Zlikavac32\SymfonyExtras\Command\Runnable\HelperSetAwareRunnable`
- `\Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithDescription`
- `\Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithHelp`
- `\Zlikavac32\SymfonyExtras\Command\Runnable\RunnableWithVisibility`

Idea is to start minimal, and define what extra stuff is needed.

## Compiler pass

Compiler pass that can register runnable as command is provided by `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnablePass`.

Runnable services can be tagged with `console_runnable` (or some other defined tag) and provide either a command name, or a mapper that maps FQN to the command name.

Mapper is useful when runnable services are registered as resources.

Simple example using directly command name is provided below.

```yaml
# will be registered as demo:command
Demo\Runnable:
    tags:
        - { name: console_runnable, command: demo:command }
```

Mappers can be used as following:

```php
class DemoMapper extends \Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ConsoleRunnable\DsMapRunnableToNameMapper
{
    public function __construct() {
        parent::__construct(new \Ds\Map([
            \Demo\Runnable::class => 'demo:command'
        ]));
    }
}
```

```yaml
# will be registered as demo:command
Demo\Runnable:
    tags:
        - { name: console_runnable, mapper: DemoMapper }
```
