<?php

require __DIR__ . "/vendor/autoload.php";

// Non-blocking server implementation based on amphp/socket keeping track of connections.

use Amp\Loop;
use Amp\Delayed;
use Amp\Redis\Client;
use Amp\Redis\SubscribeClient;
use Amp\Redis\RedisException;
use Amp\Socket\ServerSocket;
use function Amp\asyncCall;

Loop::run(function () {
    $server = new class {
        private $uri = "tcp://127.0.0.1:0";

        // We use a property to store a map of $clientAddr => $client
        private $clients = [];

        // Store a $clientAddr => $username map
        private $usernames = [];

        private $redisClient;

        public function listen() {
            asyncCall(function () {
                $this->redisClient = new Client("tcp://localhost:6379");

                $server = Amp\Socket\listen($this->uri);
                $this->listenToRedis();

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
                yield $this->redisClient->publish("chat", $remoteAddr . " joined the chat." . PHP_EOL);

                // We only insert the client afterwards, so it doesn't get its own join message
                $this->clients[$remoteAddr] = $socket;

                $buffer = "";

                while (null !== $chunk = yield $socket->read()) {
                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $this->handleMessage($socket, substr($buffer, 0, $pos));
                        $buffer = substr($buffer, $pos + 1);
                    }
                }

                // We remove the client again once it disconnected.
                // It's important, otherwise we'll leak memory.
                // We also have to unset our new usernames.
                unset($this->clients[$remoteAddr], $this->usernames[$remoteAddr]);

                // Inform other clients that that client disconnected and also print it in the server.
                print "Client disconnected: {$remoteAddr}" . PHP_EOL;
                $message = ($this->usernames[$remoteAddr] ?? $remoteAddr) . " left the chat." . PHP_EOL;
                yield $this->redisClient->publish("chat", $message);
            });
        }

        private function handleMessage(ServerSocket $socket, string $message) {
            if ($message === "") {
                // ignore all empty messages
                return;
            }

            if ($message[0] === "/") {
                // message is a command
                $message = substr($message, 1); // remove slash
                $args = explode(" ", $message); // parse message into parts separated by space
                $name = strtolower(array_shift($args)); // the first arg is our command name

                switch ($name) {
                    case "time":
                        $socket->write(date("l jS \of F Y h:i:s A") . PHP_EOL);
                        break;

                    case "up":
                        $socket->write(strtoupper(implode(" ", $args)) . PHP_EOL);
                        break;

                    case "down":
                        $socket->write(strtolower(implode(" ", $args)) . PHP_EOL);
                        break;

                    case "exit":
                        $socket->end("Bye." . PHP_EOL);
                        break;

                    case "nick":
                        $nick = implode(" ", $args);

                        if (!preg_match("(^[a-z0-9-.]{3,15}$)i", $nick)) {
                            $error = "Username must only contain letters, digits and " .
                                     "its length must be between 3 and 15 characters.";
                            $socket->write($error . PHP_EOL);
                            return;
                        }

                        $remoteAddr = $socket->getRemoteAddress();
                        $oldnick = $this->usernames[$remoteAddr] ?? $remoteAddr;
                        $this->usernames[$remoteAddr] = $nick;

                        $this->redisClient->publish("chat", $oldnick . " is now " . $nick . PHP_EOL);
                        break;

                    default:
                        $socket->write("Unknown command: {$name}" . PHP_EOL);
                        break;
                }

                return;
            }

            $remoteAddr = $socket->getRemoteAddress();
            $user = $this->usernames[$remoteAddr] ?? $remoteAddr;
            $this->redisClient->publish("chat", $user . " says: " . $message . PHP_EOL);
        }

        private function broadcast(string $message) {
            foreach ($this->clients as $client) {
                // We don't yield the promise returned from $client->write() here as we don't care about
                // other clients disconnecting and thus the write failing.
                $client->write($message);
            }
        }

        private function listenToRedis() {
            asyncCall(function () {
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
            });
        }
    };

    $server->listen();
});
