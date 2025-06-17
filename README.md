# Neos event store subscription engine adapter

[neos/eventstore](https://github.com/neos/eventstore) adapter for [wwwision/subscription-engine](https://github.com/bwaidelich/subscription-engine)

## Usage

```php
$eventStore = new DoctrineEventStore($dbalConnection, eventTableName: 'events');

$subscriptionEngine = new SubscriptionEngine(
    eventStoreAdapter: new NeosEventStoreAdapter($eventStore),
    subscriptionStore: new DoctrineSubscriptionStore($dbalConnection, tableName: 'subscriptions'),
    subscribers: Subscribers::fromArray([
        Subscriber::create(
            id: 'some-projection',
            handler: fn (EventEnvelope $eventEnvelope) => print($eventEnvelope->event->type->value),
            reset: fn () => print('resetting projection for replay'),
        ),
        Subscriber::create(
            id: 'some-process',
            handler: fn (EventEnvelope $eventEnvelope) => print('invoking process...'),
            runMode: RunMode::FROM_NOW,
            setup: fn () => print('setting up process...'),
        ),
    ])
);
```

## Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/subscription-engine-neos-adapter/issues) or [pull requests](https://github.com/bwaidelich/subscription-engine-neos-adapter/pulls) are highly appreciated

## License

See [LICENSE](./LICENSE)