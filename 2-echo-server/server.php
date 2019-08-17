<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket.

use Amp\Loop;
use Amp\Socket\Socket;
use function Amp\asyncCall;

// Amp\Loop::run() runs the event loop and executes the passed callback right after starting
Loop::run(function () {
    $uri = "tcp://127.0.0.1:1337";

    // $clientHandler will be executed for each client that connects
    $clientHandler = function (Socket $socket) {
        // ServerSocket::read() returns a promise that resolves to a string if new data is available or `null` if the
        // socket has been closed.
        while (null !== $chunk = yield $socket->read()) {
            // Yielding a write() waits until the data is fully written to the OS's internal buffer, it might not have
            // been received by the client when the promise returned from write() returns.
            yield $socket->write($chunk);
        }
    };

    // listen() is a small wrapper around stream_socket_server() returning a Server object
    $server = Amp\Socket\Server::listen($uri);

    // Like in the previous example, we accept each client as soon as we can
    // Server::accept() returns a promise. The coroutine will be interrupted and continued once the promise resolves.
    while ($socket = yield $server->accept()) {
        // Call $clientHandler without returning a promise. If an error happens in the callback, it will be passed to
        // the global error handler, we don't have to care about it here.
        asyncCall($clientHandler, $socket);
    }
});
