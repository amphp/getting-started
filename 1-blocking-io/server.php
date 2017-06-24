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
