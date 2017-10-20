---
title: Parsing Line-Delimited Messages
permalink: /tcp-chat/parsing
---
[Previously](broadcasting), we successfully completed the basic chat server. In this section we'll parse the input stream into lines.

Our previous code for reading from the client and passing messages to other clients looked like that:

{:.note}
> You can find the [code for this tutorial on GitHub](https://github.com/amphp/getting-started/tree/master/4-parsing).

```php
while (null !== $chunk = yield $socket->read()) {
    $this->broadcast($remoteAddr . " says: " . trim($chunk) . PHP_EOL);
}
```

Using coroutines, it's quite simple to extend this to parse the stream into separate lines.

```php
$buffer = "";

while (null !== $chunk = yield $socket->read()) {
    $buffer .= $chunk;

    while (($pos = strpos($buffer, "\n")) !== false) {
        $this->broadcast($remoteAddr . " says: " . substr($buffer, 0, $pos) . PHP_EOL);
        $buffer = substr($buffer, $pos + 1);
    }
}
```

We create a `$buffer` variable to store the current buffer content. If we can't find a newline character in the `$buffer`, we just continue reading. If we find one, we broadcast the message to all clients as before and remove the message we just broadcasted from the buffer. We use a `while` loop here, as a client also send multiple messages in a single packet.

## Parsing Commands

This section is about parsing, but just parsing newlines is boring, right? Let's add some commands to our server. Commands are special messages that will be treated differently by the server and their result will not be broadcasted to all clients.

All commands in our server will start with a `/`, so let's parse them. We will modify our above code to separate message handling from parsing the stream into lines.

```php
$buffer = "";

while (null !== $chunk = yield $socket->read()) {
    $buffer .= $chunk;

    while (($pos = strpos($buffer, "\n")) !== false) {
        $this->handleMessage($socket, substr($buffer, 0, $pos));
        $buffer = substr($buffer, $pos + 1);
    }
}
```

```php
function handleMessage(ServerSocket $socket, string $message) {
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

            default:
                $socket->write("Unknown command: {$name}" . PHP_EOL);
                break;
        }

        return;
    }

    $this->broadcast($socket->getRemoteAddress() . " says: " . $message . PHP_EOL);
}
```

We have three commands now: `time`, `up` and `down`. `time` reports the current server time to the client, while `up` and `down` change the rest of the message to upper / lower case and return the result to the client.

As you can see, adding commands is pretty easy now. Let's add another one to allow the client to exit (you can do that via `Ctrl + C` in `nc` anyway).

```php
case "exit":
    $socket->end("Bye." . PHP_EOL);
    break;
```

`$socket->end()` sends a final message before closing the socket.

## Adding Usernames

As we already have commands now, why not add a command that let's a client choose its username? Currently we just used the socket address as a username. Let's add a new `nick` command.

```php
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

    $this->broadcast($oldnick . " is now " . $nick . PHP_EOL);
    break;
```

We also need to change our `broadcast` calls now to use the username, and also need to unset the username when the client disconnects.

```php
$remoteAddr = $socket->getRemoteAddress();
$user = $this->usernames[$remoteAddr] ?? $remoteAddr;
$this->broadcast($user . " says: " . $message . PHP_EOL);
```

## Adding Authentication

Adding authentication is a task left to you, we'll just give some hints. You could for example create a `register` command that accepts a name and password and save that somewhere using `password_hash`. You could then extend `nick` with the same mechanism and require the right password using `password_verify` other otherwise disallow changing to that name.

In the next step we will use [Redis](https://redis.io/) for Pub/Sub to broadcast messages over multiple instances.

[Continue with the next section covering multiple instances](multiple-instances).
