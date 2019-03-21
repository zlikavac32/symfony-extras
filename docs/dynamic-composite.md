# Dynamic Composite

Sometimes a group of services has to be injected into some other service, either through a constructor or through method calls.

Symfony provides limited support for that through `tagged` service injection.

Compiler pass `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\DynamicCompositePass` tries to provide more functionality.

Services can be tagged with `dynamic_composite` which describes how other services are injected into them. Property `tag` describers tag used to collect services and additional properties describe how they are injected.

Collected services can be prioritized using `priority` property. Services are sorted in the ascending order in respect to the priority and within same priority to the order of service discovery from the container.

- `method` - can be `__construct` or any other method (default `__construct`)
- `argument` - represents argument that takes collected service (default `0`)
- `prioritized` - if `false`, priority from collected service is ignored and services are injected in order in which they are loaded from the container (default `true`)

Simple configuration could be defined as in the example below.

```yaml
# assuming __construct(array $services) exists, this will result in
# __construct(['@bar_injected_service', '@foo_injected_service'])
Demo\FooComposite:
    tags:
        - { name: dynamic_composite, tag: foo_tag }

foo_injected_service:
    ...
    tags:
        - { name: foo_tag }

# will be before foo_injected_service although is defined after it
bar_injected_service:
    ...
    tags:
        - { name: foo_tag, priority: -5 }
```

## Argument resolver

When using method injection, it could be useful to provide additional arguments into methods. Compiler pass can accept argument resolver for group tag.

For example, lets assume that we have class `\Demo\MethodComposite` with method `add(int $priority, $service)`. We can define custom argument resolver, and provide priority as additional argument.

```php
class FooArgumentResolver implements CompositeMethodArgumentResolver
{
    public function resolveFor(ContainerBuilder $container, string $serviceId, Map $tagProperties): array
    {
        return [$tagProperties->get('priority')];
    }

    public function finish(): void
    {
    }
}

// ...

new DynamicCompositePass('dynamic_composite', new \Ds\Map([
    'foo_tag' => new FooArgumentResolver()
]));
```

Then, when method call is added, additional arguments are resolved.

```yaml
# wil have method call for add(32, '@foo_injected_service')
Demo\MethodComposite:
    tags:
        - { name: dynamic_composite, method: add, tag: foo_tag, argument: 1, prioritized: false }

foo_injected_service:
    ...
    tags:
        - { name: foo_tag, priority: 32 }
```

This will result in a method call `add(32, '@foo_injected_service')` on the service `Demo\MethodComposite`.
