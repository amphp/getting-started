---
title: Basic TCP Echo Server
permalink: /tcp-chat/basic-echo-server
---
[Previously](./), we started by creating a simple TCP server with blocking I/O. We'll rewrite `server.php` on top of `amphp/socket` now.

Create a new [Composer](https://getcomposer.org/) project in a new directory by running `composer init`. Use whatever name and description you want, don't require any libraries yet.

Then run `composer require amphp/socket` to install the latest `amphp/socket` release.

{:.note}
> You can find the [code for this tutorial on GitHub](https://github.com/amphp/getting-started/tree/master/2-echo-server).

```php
<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket.

use Amp\Loop;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

Loop::run(function () {
    $uri = "tcp://127.0.0.1:1337";

    $clientHandler = function (ServerSocket $socket) {
        while (null !== $chunk = yield $socket->read()) {
            yield $socket->write($chunk);
        }
    };

    $server = Amp\Socket\listen($uri);

    while ($socket = yield $server->accept()) {
        asyncCall($clientHandler, $socket);
    }
});
```

All we do here is accepting the clients and echoing their input back as before, but it happens concurrently now. While just a few lines of code, there are a lot of new concepts in there.

What happens there? `Amp\Loop::run()` runs the event loop and executes the passed callback right after starting. `Amp\Socket\listen()` is a small wrapper around `stream_socket_server()` creating a server socket and returning it as `Server` object. Like in the previous example, we accept each client as soon as we can. `Server::accept()` returns a promise. `yield` will interrupt the coroutine and continue once the promise resolves. It then asynchronously calls `$clientHandler` for each accepted client. `$clientHandler` reads from the socket and directly writes the read contents to the socket again.

## What is the Event Loop?

Good question! The event loop is the main scheduler of every asynchronous program. In it's simplest form, it's a while loop calling `stream_select`. The following pseudo-code might help you to understand what's going on.

```php
<?php

while ($running) {
    $readStreams = getStreamsToWatchForReadEvents();
    $writeStreams = getStreamsToWatchForWriteEvents();

    $actionableStreams = stream_select($readStreams, $writeStreams);

    foreach ($actionableStreams as $stream) {
        call_event_handler_for_stream($stream);
    }
}
```

That's a simple event loop that can call callbacks when there are new events for certain streams. In reality this gets a bit more complicated, because the event loop also supports other events than streams such as timers and signals. If you want, you can have a look at the [`dispatch()` method of Amp's `NativeDriver`](https://github.com/amphp/amp/blob/5b2f54707ca5d6d1e541ceeafa8b4904e5ea4837/lib/Loop/NativeDriver.php#L64-L124).

It's not important to understand the inner implementation details, but it's useful to know how the basics work. The event loop always needs to run, so it's best to just do everything within `Loop::run(function () { ... })`. You might enjoy watching [Philip Roberts: What the heck is the event loop anyway?](https://www.youtube.com/watch?v=8aGhZQkoFbQ), a talk about the same concept in JS. It doesn't entirely reflect how things work in Amp, but it's a good starting point.

## What's a `Promise`?

A [`Promise`](https://github.com/amphp/amp/blob/master/lib/Promise.php) is the basic unit of concurrency in Amp. It's a placeholder for a future result of a function call. As the result of a function call is delivered asynchronously, we can't simply return the result right away as a return value, but instead use these placeholders. Such a `Promise` can either fail with an exception or succeed successfully.

While other libraries use `then()` and callbacks, Amp favors coroutines, because they allow linear code flow like synchronous programs.

## What's `yield`?

Never seen that? It's awesome. A `yield` in a function turns this function into a `Generator` when called. [The PHP manual describes Generators as an easy way to implement simple iterators](http://php.net/manual/en/language.generators.overview.php). While that's one use case, it's the boring one. You can not only iterate over a `Generator`, you can also send values into it using `Generator::send()` and throw exceptions into it using `Generator::throw()`. We can recommend reading [NikiC's blog post about cooperative multitasking using coroutines](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html).

Basically what happens is that: The coroutine is paused when a promise is yielded. The coroutine runner (Amp) then subscribes to the promise automatically and continues the coroutine once the yielded promise resolves. If the promise fails, Amp throws the exception into the `Generator`. If the promise succeeds, Amp sends the resolution value into the `Generator`. This makes consuming promises without callbacks possible and allows for ordinary `try` / `catch` blocks for error handling.

You can read `yield` inside a coroutine just like `await`. While the specific coroutine is waiting at that point, other coroutines and event handlers can be executed. A coroutine can be seen as lightweight thread, but if it blocks, everything else within the same process is also blocked.

If you want to integrate ReactPHP libraries (as you can do by using our [`react-adapter`](https://github.com/amphp/react-adapter)), you can `yield` ReactPHP's promises just like any `Amp\Promise` instance.

## What did we gain now?

Remember that we could only handle a single client at a time before? Try connecting from multiple terminal windows now and you'll see that all connections are handled concurrently.

In the next section we'll send messages to all connected clients instead of just the sending client.

[Continue with the next section about broadcasting to all clients](broadcasting).
