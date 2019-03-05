# Dynamic Event Dispatcher

If Symfony application requires more than one event dispatcher, additional dispatchers can be registered with `\Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass`.

Compiler pass that automates that is provided in `\Zlikavac32\SymfonyExtras\DependencyInjection\Compiler\EventDispatcherPass`. Services tagged with `event_dispatcher` (can be changed) are registered as event dispatchers.

Every tag requires `listener_tag` and `subscriber_tag` properties which declare tags used for listeners and subscribers.

In the example below, two dispatchers are registered.

```yaml
# tag listener_foo can be used to add listeners, while subscriber_foo can be used
# to register subscribers
foo_dispatcher:
    class: \Symfony\Component\EventDispatcher\EventDispatcher
    tags:
        - { name: event_dispatcher, listener_tag: listener_foo, subscriber_tag: subscriber_foo }

# tag listener_bar can be used to add listeners, while subscriber_bar can be used
# to register subscribers
bar_dispatcher:
    class: \Symfony\Component\EventDispatcher\EventDispatcher
    tags:
        - { name: event_dispatcher, listener_tag: listener_bar, subscriber_tag: subscriber_bar }
```
