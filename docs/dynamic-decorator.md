# Dynamic Decorator

Symfony provides out of the box way to decorate services through `decorates` key in service definition. Manually defining all of the decorators can be a bit messy and time consuming. Therefore this library provides a way to have dynamic decorators.

Idea is to define abstract service as templates for decorators. Services that wish to be decorated are tagged with decorator tags and compiler pass `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass` takes care of the messy and time consuming part.

By default, tag is `decorator`, but it can be changed when compiler pass is being constructed.

Services tagged with `decorator` are template services. One example could be:

```yaml
\Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }
```

Service `\Demo\FooDecorator` represents template for the decorator that can be requested using tag `decorator.domain_foo`.

Services tagged with `decorator.domain_foo` will be decorated using `\Demo\FooDecorator`.

```yaml
\Demo\ConcreteService:
    tags:
        - { name: decorator.domain_foo }
```

By default, argument with index `0` is used. It can be changed in `decorator` tag using `argument` property. Assuming that `\Demo\FooDecorator` has argument `$foo`, it can be used as well.

```yaml
\Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo, argument: $foo }
```

Arguments are only replaced which fits with Symfony auto-wire functionality.

Multiple decorators can be applied and with different priorities. Priority respects semantic of Symfony DIC decorator priority, meaning that priorities with higher values are first to decorate service.

```yaml
\Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }

\Demo\BarDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_bar }


# BarDecorator(FooDecorator(DemoConcreteService)))
\Demo\ConcreteService:
    tags:
        - { name: decorator.domain_bar }
        - { name: decorator.domain_foo, priority: 32 }
```

## Passing tags

Sometimes decorating services have other dependencies that are different depending on the service that is being decorated. For example, certain decorator might log some data and which logger is used, depends on which service is being decorated.

That can be resolved with tags as well, but, since tags can contain only scalar values, option is either to use some string format to describe new tags or to represent them linearly. This library uses second option.

Tags are in format `i(group)_(property)` where `(group)` is an integer >= 0 used to group same properties. `(property)` is property name. Every `(group)` must have at least `i(group)_name` property defined since it represents new tag name.

Linear representation of the

```yaml
- { name: foo }
- { name: bar, some-property: 32}
```

is

```yaml
{ i0_name: foo, i1_name: bar, i1_some-property: 32 }
```


Order doesn't have to be respected, reconstruction will work correctly. Final tags are sorted by the `(group)` in the ascending order.

For example, to inject certain logger in the decorator, following configuration could be used:

```yaml
\Demo\ConcreteService:
    tags:
        - { name: decorator.domain_bar, i0_name: monolog.logger, i0_channel: foo }
```

Example above, after compiler pass finishes looks something like this:

```yaml
\Demo\ConcreteService.decorator.domain_bar:
    decorates: \Demo\ConcreteService
    arguments:
        - '@\Demo\ConcreteService.decorator.domain_bar.inner'
        - '@logger'
    tags:
        - { name: monolog.logger, channel: foo }
```

Do keep note on the compiler pass order. Due to the nature how compiler passes work, passing tags that are processed before decorator pass is triggered, will result in those tags being ignored.

## Linker

Passing tags is useful when combined with the `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass` to link various services from templates.

Check [linker.md](linker.md) and [../examples/dynamic_decorator/linker.php](../examples/dynamic_decorator/linker.php) for more info.
