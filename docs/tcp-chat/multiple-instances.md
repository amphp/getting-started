---
title: Running Multiple Instances
permalink: /tcp-chat/multiple-instances
---
[Previously](parsing), we successfully parsed messages and added some commands, but our server was still limited to one server process to handle all clients. We'll now add [Redis](https://redis.io/) as a Pub/Sub tool to broadcast messages over multiple server instances.

The first things we need to do for that is installing a Redis and a client. You can find installation instructions for Redis on the internet.

```
composer require amphp/redis
```

We will publish all messages to a `chat` channel on Redis and broadcast all received messages to all connected clients.

{:.note}
> You can find the [code for this tutorial on GitHub](https://github.com/amphp/getting-started/tree/master/5-multiple-instances).

```php
$redisClient = new SubscribeClient("tcp://localhost:6379");

do {
    try {
        $subscription = yield $redisClient->subscribe("chat");

        while (yield $subscription->advance()) {
            $message = $subscription->getCurrent();
            $this->broadcast($message);
        }
    } catch (RedisException $e) {
        // reconnect in case the connection breaks, wait a second before doing so
        yield new Delayed(1000);
    }
} while (true);
```

Subscriptions in `amphp/redis` follow [Amp's `Iterator` interface](http://amphp.org/amp/iterators/).

We will replace our current `$this->broadcast()` calls with `$redisClient->publish()`, the messages will be sent when received from Redis.

You can find the [complete code in the GitHub repository](https://github.com/amphp/getting-started/tree/master/5-multiple-instances).

This is the end of our TCP chat server series. Feel free to refactor and extend the code. We'd be glad to hear which cool features you added! If you're looking for real-world use cases not using raw TCP, you might want to have a look at [our HTTP server](https://github.com/amphp/http-server) with WebSocket support.
