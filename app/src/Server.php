<?php

namespace VundorFuckedPirate;

use swoole_websocket_server;
use swoole_http_client;
use swoole_table;

/**
 * Class Server
 * @package VundorFuckedPirate
 */
class Server
{
    static $server;
    static $gameServerId;
    static $table;

    /**
     * Initialize websocket server
     */
    public function run()
    {
        $bindAddress = '0.0.0.0';
        $bindPort = 80;

        //initialize table
        self::$table = new \swoole_table(1024);
        self::$table->column('id', \swoole_table::TYPE_STRING, 50);
        self::$table->create();

        $server = new \swoole_websocket_server($bindAddress, $bindPort);

        $server->on('handshake', function (\swoole_http_request $request, \swoole_http_response $response) use ($server) {
            $secWebSocketKey = $request->header['sec-websocket-key'];
            $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

            if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
                $response->end();

                return false;
            }

            $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            $headers = [
                'Upgrade'               => 'websocket',
                'Connection'            => 'Upgrade',
                'Sec-WebSocket-Accept'  => $key,
                'Sec-WebSocket-Version' => '13',
            ];

            // WebSocket connection to 'ws://127.0.0.1:9502/'
            // failed: Error during WebSocket handshake:
            // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
            if (isset($request->header['sec-websocket-protocol'])) {
                $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
            }

            foreach ($headers as $key => $val) {
                $response->header($key, $val);
            }

            $response->status(101);
            $response->end();
            self::log("New client connected!");

            return true;
        });

        $server->on('start', function ($server) {
            $this->start($server);
        });

        $server->on('message', function ($server, $frame) {
            $this->message($server, $frame);
        });

        $server->on('close', function ($server, $fd) {
            $this->close($server, $fd);
        });

        echo "Websocket is listening on ws://$bindAddress:$bindPort\n";

        $server->start();
    }

    public function start($server)
    {
        echo "Server started\n";
        self::$server = $server;

        $gameServer = new GameServer($server->host, $server->port);
        $gameServer->run();
    }

    public function message($server, $frame)
    {
        self::log("Received message: {$frame->data} from {$frame->fd}");


        $data = json_decode($frame->data, true);
        if (isset($data['gameserver'])) {

            self::setGameserver($frame);

            $server->push($frame->fd, json_encode(['s' => 100]));
        } elseif (isset($data['login'])) {
            self::$table->set('player_' . $frame->fd, [
                'id' => $data['login']
            ]);
            self::log("Player logged as: {$data['login']} from {$frame->fd}");
            $server->push(self::getGameserverId(), json_encode(
                ['login' => $data['login']]
            ));
        } elseif (isset($data['x']) && (isset($data['y']))) {
            if ($playerData = self::$table->get('player_' . $frame->fd)) {
                $server->push(self::getGameserverId(), json_encode([
                    'login' => $playerData['id'],
                    'x'     => $data['x'],
                    'y'     => $data['y']
                ]));
            } else {
                self::log("Player from connection {$frame->fd} not found");
            }
        }
    }

    /**
     * @param $server
     * @param $fd
     */
    public function close($server, $fd)
    {
        echo "connection close: {$fd}\n";
    }

    /**
     * @param $frame
     */
    static function setGameserver($frame)
    {
        self::log("Setting up gameserver: {$frame->fd}");
        self::$table->set('gameserver', [
            'id' => $frame->fd
        ]);
    }

    /**
     * @return int
     */
    static function getGameserverId()
    {
        return self::$table->get('gameserver')['id'];
    }

    /**
     * @param mixed $message
     * @param int $type
     */
    static function log(string $message, int $type = 0)
    {
        echo "[" . date(DATE_ATOM) . "][$type] $message\n";
    }
}
