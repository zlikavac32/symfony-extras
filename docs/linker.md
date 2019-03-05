# Linker

We can inject different loggers in Symfony DIC through the `monolog.logger` tag. By choosing a different channel, different instance will be injected. Tag is used to rewire logger service. It's not uncommon to have many different passes for similar conditional injection.

Linker tries to optimize that part. It's provided in the `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\ServiceLinkerPass`. Main idea is that single tag rewires single argument. Rewire can happen directly or indirectly.

Let's first focus on direct linking. Following our example from the `monolog.logger`, we'd like to have similar functionality for some other service. We can generalize this by introducing one new tag, `linker` which will define tag (property `provider_tag`) used to group similar services and an identifier to identify needed service (property `provider`).

Service that have to be linked into other services can be tagged with provider tag and say what they provide through the `provides` tag property.

In the following example, we'll define two service providers and then link them. Since concept of `monolog.logger` tag is mostly known, it will be represented here through `linker`.

```yaml
foo_logger:
    class: Monolog\Logger
    ...
    tags:
        - { name: dynamic_logger, provides: foo }
bar_logger:
    class: Monolog\Logger
    ...
    tags:
        - { name: dynamic_logger, provides: bar }

# links foo_logger with this service in argument 0
service_needing_logger:
    ...
    tags:
        - { name: linker, provider_tag: dynamic_logger, provider: foo }
```

Different argument can be also specified.

```yaml
# links foo_logger with this service in argument $logger
service_needing_logger:
    ...
    tags:
        - { name: linker, provider_tag: dynamic_logger, provider: foo, argument: $logger }
```

## Indirect linking

Indirect linking uses one more tag to describe how arguments are linked. This is used in combination with service decoration (read [dynamic-decorator.md](dynamic-decorator.md)) to allow decorating service to define what it's linked with.

If direct linking were used, every service that needs decoration would have to know about arguments in the decorator template.

To combat that, linker can be configured to resolve concrete service through resolver tag. Property `argument_resolver_tag` defines tag that will be used to resolve single argument.

Resolved argument can be from a provider, a concrete service or a container parameter.

Example of linking decorated services.

```yaml
\Demo\LoggerDecorator:
    abstract: true
    tags:
        - { name: decorator, tag: decorator.domain_foo }
        - { name: linker, argument_resolver_tag: logger_decorator_logger, argument: $logger }

# LoggerDecorator will use foo_logger
\Demo\ConcreteService:
    tags:
        - { name: decorator.domain_foo, i0_name: logger_decorator_logger, i0_provider_tag: dynamic_logger, i0_provider: foo }
```

Instead of the `provider_tag` and `provider` properties, we could have used `service` and `param` properties. `param` property links with a parameter, while `service` property links with a service from the container.
