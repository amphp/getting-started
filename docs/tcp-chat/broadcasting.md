---
title: Broadcasting Messages to Connected Clients
permalink: /tcp-chat/broadcasting
---

[Previously](basic-echo-server), we learned how to handle multiple clients concurrently. We will now extend what we already have and send a client's messages to all other connected clients.

The first thing we need for that is something that keeps track of all active connections. As we need to share this state between all clients, we'll encapsulate that state into a class.

{:.note}
> You can find the [code for this tutorial on GitHub](https://github.com/amphp/getting-started/tree/master/3-broadcasting).

```php
<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket encapsulated in a class.

use Amp\Loop;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

Loop::run(function () {
    $server = new class {
        private $uri = "tcp://127.0.0.1:1337";

        public function listen() {
            asyncCall(function () {
                $server = Amp\Socket\listen($this->uri);

                while ($socket = yield $server->accept()) {
                    $this->handleClient($socket);
                }
            });
        }

        public function handleClient(ServerSocket $socket) {
            asyncCall(function () use ($socket) {
                while (null !== $chunk = yield $socket->read()) {
                    yield $socket->write($chunk);
                }
            });
        }
    };

    $server->listen();
});
```

All we did there is rewriting the previous example by removing the comments and putting it inside an anonymous class. We can simply add a property to that class now keeping track of our connections. We will also add some output on the server-side when a client connects or disconnects.

```php
<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket keeping track of connections.

use Amp\Loop;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

Loop::run(function () {
    $server = new class {
        private $uri = "tcp://127.0.0.1:1337";

        // We use a property to store a map of $clientAddr => $client
        private $clients = [];

        public function listen() {
            asyncCall(function () {
                $server = Amp\Socket\listen($this->uri);

                print "Listening on " . $server->getAddress() . " ..." . PHP_EOL;

                while ($socket = yield $server->accept()) {
                    $this->handleClient($socket);
                }
            });
        }

        private function handleClient(ServerSocket $socket) {
            asyncCall(function () use ($socket) {
                $remoteAddr = $socket->getRemoteAddress();

                // We print a message on the server and send a message to each client
                print "Accepted new client: {$remoteAddr}". PHP_EOL;
                $this->broadcast($remoteAddr . " joined the chat." . PHP_EOL);

                // We only insert the client afterwards, so it doesn't get its own join message
                $this->clients[$remoteAddr] = $socket;

                while (null !== $chunk = yield $socket->read()) {
                    $this->broadcast($remoteAddr . " says: " . trim($chunk) . PHP_EOL);
                }

                // We remove the client again once it disconnected.
                // It's important, otherwise we'll leak memory.
                unset($this->clients[$remoteAddr]);

                // Inform other clients that that client disconnected and also print it in the server.
                print "Client disconnected: {$remoteAddr}" . PHP_EOL;
                $this->broadcast($remoteAddr . " left the chat." . PHP_EOL);
            });
        }

        private function broadcast(string $message) {
            foreach ($this->clients as $client) {
                // We don't yield the promise returned from $client->write() here as we don't care about
                // other clients disconnecting and thus the write failing.
                $client->write($message);
            }
        }
    };

    $server->listen();
});
```

## Next Steps

We have a working chat server now... well, kind of working. We currently just take every chunk we receive from a client as a message. If a user writes a long message, that message might not be sent as a single packet and we won't receive it in one chunk. We also don't have an usernames or authentication yet. It only works with a single process on the server side, what if we have a lot of clients and can't handle them all in a single process? We will cover those topics in the coming sections, extending our simple project. We won't post all code in the coming sections, but only the interesting / changing parts.

[Continue with the next section about parsing](parsing).
