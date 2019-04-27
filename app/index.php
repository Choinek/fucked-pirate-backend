<?php

$bindAddress = '0.0.0.0';
$bindPort = 80;

$server = new swoole_websocket_server($bindAddress, $bindPort);

$server->on('open', function($server, $req) {
    echo "connection open: {$req->fd}\n";
});

$server->on('message', function($server, $frame) {
    echo "received message: {$frame->data}\n";
    $server->push($frame->fd, json_encode(["hello", "world"]));
});

$server->on('close', function($server, $fd) {
    echo "connection close: {$fd}\n";
});

echo "Websocket is listening on ws://$bindAddress:$bindPort\n";
$server->start();
