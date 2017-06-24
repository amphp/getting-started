---
title: Building a TCP Chat
permalink: /tcp-chat
---
In this tutorial we're going to create a simple TCP chat server based on Amp that allows many users to connect and exchange messages concurrently. We'll start by building a TCP server which uses blocking I/O like it's traditionally done in PHP and see the limitations of it.

Get started by creating a new directory for our new project and create a simple `server.php` file with the following content:

```php
<?php

// Blocking PHP implementation that accepts one socket at a time and echos all input back
// as long as that socket is connected.

$uri = "tcp://127.0.0.1:1337";

$flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
$serverSocket = @stream_socket_server($uri, $errno, $errstr, $flags);

if (!$serverSocket || $errno) {
    die(sprintf("Could not create server: %s: [Errno: #%d] %s", $uri, $errno, $errstr));
}

while ($client = stream_socket_accept($serverSocket, -1)) {
    while (($line = fgets($client)) !== false) {
        fputs($client, $line);
    }
}
```

It creates a simple server socket and accepts connections. Once a connection is accepted, it listens for new input lines and echo's them back to the client. You can run the server by executing `php server.php`.

Let's open another terminal window and connect to the server using netcat: `nc localhost 1337`. You can enter a few characters and hit enter, the same line will appear again as the server echos the same text back. Seems to work pretty well, doesn't it?

Now open yet another terminal window and execute `nc localhost 1337` again, while keeping the other command running. If you submit some text there, there won't be an answer. That's because once the server accepts a client, it only reads from that single client as long as it's alive. Only when that client dies, a new client will be accepted by the server. Just it `Ctrl + C` in the first window, you'll see that it now responded in the second window as the second client got accepted and its message was read and echoed.

It's one of the first things you have to learn when writing asynchrnous / event-based programs: Any blocking operation will block everything, so try to keep blocking operations out of the event loop where possible. Either off-load these to another process (maybe using a queue) or replace them with non-blocking APIs where possible.

In the [next part](./basic-echo-server) we're going to cover the exact same example, but built on top of Amp and its libraries.
