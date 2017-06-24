---
title: Basic TCP Echo Server
permalink: /basic-echo-server
---
[Previously](./), we started by creating a simple TCP server with blocking I/O. We'll rewrite `server.php` on top of `amphp/socket` now.

Create a new [Composer](https://getcomposer.org/) project in a new directory by running `composer init`. Use whatever name and description you want, don't require any libraries yet.

Then run `composer require amphp/socket` to install the latest `amphp/socket` release.

```php
<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket.

use Amp\Loop;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

// Amp\Loop::run() runs the event loop and executes the passed callback right after starting
Loop::run(function () {
    $uri = "tcp://127.0.0.1:1337";

    // $clientHandler will be executed for each client that connects
    $clientHandler = function (ServerSocket $socket) {
        // ServerSocket::read() returns a promise that resolves to a string if new data
        // is available or `null` if the socket has been closed.
        while (null !== $chunk = yield $socket->read()) {
            // Yielding a write() waits until the data is fully written to the OS's
            // internal buffer, it might not have been received by the client when the
            // promise returned from write() returns.
            yield $socket->write($chunk);
        }
    };

    // Amp\Socket\listen() is a small wrapper around stream_socket_server()
    $server = Amp\Socket\listen($uri);

    // Like in the previous example, we accept each client as soon as we can Server::accept()
    // returns a promise. The coroutine will be interrupted and continued once the promise resolves.
    while ($socket = yield $server->accept()) {
        // Call $clientHandler without returning a promise. If an error happens in the callback,
        // it will be passed to the global error handler, we don't have to care about it here.
        asyncCall($clientHandler, $socket);
    }
});
```

All we do here is accepting the clients and echoing their input back as before, but it happens concurrently now. While just a few lines of code, there are a lot of new concepts in there.

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

## What's `yield`?

Never seen that? It's awesome. A `yield` in a function turns this function into a `Generator` when called. [The PHP manual describes Generators as an easy way to implement simple iterators](http://php.net/manual/en/language.generators.overview.php). While that's one use case, it's the boring one. You can not only iterate over a `Generator`, you can also send values into it using `Generator::send()` and throw exceptions into it using `Generator::throw()`. We can recommend reading [NikiC's blog post about cooperative multitasking using coroutines](http://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html).

Basically what happens is that: The coroutine is paused when a promise is yielded. The coroutine runner (Amp) then subscribes to the promise automatically and continues the coroutine once the yielded promise resolves. If the promise fails, Amp throws the exception into the `Generator`. If the promise succeeds, Amp sends the resolution value into the `Generator`. This makes consuming promises without callbacks possible and allows for ordinary `try` / `catch` blocks for error handling.

## What did we gain now?

Remember that we could only handle a single client at a time before? Try connecting from multiple terminal windows now and you'll see that all connections are handled concurrently.

In the [next section](./broadcasting) we'll send messages to all connected clients instead of just the sending client.
