<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket keeping track of connections.

use Amp\Loop;
use Amp\Socket\Socket;
use function Amp\asyncCall;

Loop::run(function () {
    $server = new class {
        private $uri = "tcp://127.0.0.1:1337";

        // We use a property to store a map of $clientAddr => $client
        private $clients = [];

        public function listen() {
            asyncCall(function () {
                $server = Amp\Socket\Server::listen($this->uri);

                print "Listening on " . $server->getAddress() . " ..." . PHP_EOL;

                while ($socket = yield $server->accept()) {
                    $this->handleClient($socket);
                }
            });
        }

        private function handleClient(Socket $socket) {
            asyncCall(function () use ($socket) {
                $remoteAddr = $socket->getRemoteAddress();

                // We print a message on the server and send a message to each client
                print "Accepted new client: {$remoteAddr}". PHP_EOL;
                $this->broadcast($remoteAddr . " joined the chat." . PHP_EOL);

                // We only insert the client afterwards, so it doesn't get its own join message
                $this->clients[(string) $remoteAddr] = $socket;

                while (null !== $chunk = yield $socket->read()) {
                    $this->broadcast($remoteAddr . " says: " . trim($chunk) . PHP_EOL);
                }

                // We remove the client again once it disconnected.
                // It's important, otherwise we'll leak memory.
                unset($this->clients[(string) $remoteAddr]);

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
