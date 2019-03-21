# Dynamic Decorator

Symfony provides out of the box way to decorate services through `decorates` key in service definition. Manually defining all of the decorators can be a bit messy and time consuming. Therefore this library provides a compiler pass to automate that process to some degree.

Idea is to define abstract services as templates for decorators. Services that wish to be decorated are tagged with decorator tags and compiler pass `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DecoratorPass` takes care of the messy and time consuming part.

By default, tag is `decorator`, but it can be changed through the constructor.

Services tagged with `decorator` are template services. One example could be:

```yaml
Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }
```

Service `Demo\FooDecorator` represents template for the decorator that can be requested using tag `decorator.domain_foo`.

Services tagged with `decorator.domain_foo` will be decorated using `Demo\FooDecorator`.

```yaml
Demo\ConcreteService:
    tags:
        - { name: decorator.domain_foo }
```

By default, argument with index `0` is used. It can be changed in `decorator` tag using `argument` property. Assuming that `Demo\FooDecorator` has argument `$foo`, it can be used as well.

```yaml
Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo, argument: $foo }
```

Multiple decorators can be applied and with different priorities. Priority respects semantic of Symfony DIC decorator priority, meaning that priorities with higher values are first to decorate service.

For decorators with same priority, order in which they are defined on service is order in which they are processed, same as Symfony DIC would do with service definitions.

```yaml
Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }

Demo\BarDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_bar }


# BarDecorator(FooDecorator(DemoConcreteService)))
Demo\ConcreteServiceOne:
    tags:
        - { name: decorator.domain_bar }
        - { name: decorator.domain_foo, priority: 32 }

# FooDecorator(BarDecorator(DemoConcreteService)))
Demo\ConcreteServiceTwo:
    tags:
        - { name: decorator.domain_bar }
        - { name: decorator.domain_foo }
```


## Decorating arguments

Sometimes it's useful to decorate certain argument without affecting referenced service. For example, we want to decorate certain service as immutable before injecting it.

This can be accomplished with `argument` attribute on the template decorator tag (not on the `decorator` tag itself).

**Decorated arguments must be explicitly declared**. This is due to the fact that auto-wiring is done i na later stage of the compilation process.

```yaml
Demo\FooDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }

Demo\ConcreteService: ~

// ServiceThatRequiresImmutableConcreteService(FooDecorator(ConcreteService))
// but Demo\ConcreteService is still the same
Demo\ServiceThatRequiresImmutableConcreteService:
    arguments:
        - '@Demo\ConcreteService'
    tags:
        - { decorator.domain_foo, argument: 0 }
```

Internally, for every argument, an alias is created onto which decorators are applied.

## Passing tags

Sometimes decorators have other dependencies that are different depending on the service that is being decorated. For example, certain decorator might log some data and which logger is used, depends on which service is being decorated.

That can be resolved with tags as well, but, since tags can contain only scalar values, option is either to use some string format to describe new tags or to represent them linearly. This library uses second option.

Tags are in format `i(group)_(property)` where `(group)` is an `integer >= 0` used to group same properties. `(property)` is property name. Every `(group)` must have at least `i(group)_name` property defined since it represents new tag name.

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
Demo\ConcreteService.decorator.domain_bar:
    decorates: Demo\ConcreteService
    arguments:
        - '@Demo\ConcreteService.decorator.domain_bar.inner'
        - '@logger'
    tags:
        - { name: monolog.logger, channel: foo }
```

Do keep note on the compiler pass order. Due to the nature how compiler passes work, passing tags that are processed before decorator pass is triggered, will result in those tags being ignored.

## Linker

Passing tags is useful when combined with the `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass` to link various services from templates.

Check [linker.md](linker.md) and [../examples/dynamic_decorator/linker.php](../examples/dynamic_decorator/linker.php) for more info.
