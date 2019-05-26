<?php

namespace VundorFuckedPirate;

use swoole_http_request;
use swoole_http_response;
use swoole_websocket_frame;
use swoole_websocket_server;
use swoole_http_client;
use swoole_table;

/**
 * Class Server
 * @package VundorFuckedPirate
 */
class Server
{
    /**
     * @var swoole_websocket_server
     */
    static $server;

    /**
     * @var swoole_table
     */
    static $playersTable;

    /**
     * @var swoole_table
     */
    static $handlersTable;

    /**
     * @var swoole_table
     */
    static $gameworldTable;

    /**
     * Initialize websocket server
     */
    public function run()
    {
        $bindAddress = '0.0.0.0';
        $bindPort = 80;

        $this->initializeTables();

        $server = new swoole_websocket_server($bindAddress, $bindPort);

        $server->on('handshake', function (swoole_http_request $request, swoole_http_response $response) {
            $this->handshake($request, $response);
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

    public function initializeTables()
    {
        self::$playersTable = new swoole_table(1024);
        self::$playersTable->column('login', swoole_table::TYPE_STRING, 50);
        self::$playersTable->create();
        self::$handlersTable = new swoole_table(64);
        self::$handlersTable->column('value', swoole_table::TYPE_STRING, 50);
        self::$handlersTable->create();
        self::$gameworldTable = new swoole_table(8);
        self::$gameworldTable->column('data', swoole_table::TYPE_STRING, 1000000);
        self::$gameworldTable->create();
    }

    /**
     * @param swoole_http_request $request
     * @param swoole_http_response $response
     * @return bool
     */
    public function handshake(swoole_http_request $request, swoole_http_response $response)
    {
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
    }

    /**
     * @param swoole_websocket_server $server
     */
    public function start($server)
    {
        echo "Server started\n";

        $gameServer = new GameServer($server->host, $server->port);
        $gameServer->run();

    }

    /**
     * @param swoole_websocket_server $server
     * @param swoole_websocket_frame $frame
     */
    public function message($server, $frame)
    {
        self::log("Received message: {$frame->data} from {$frame->fd}");
        if (!self::$server) {
            self::$server = $server;
        }

        $data = json_decode($frame->data, true);

        if ((int)$frame->fd === (int)self::getGameserverId()) {
            if (isset($data['players'])) {
                $this->broadcast($server, $frame->data);
            }

        }

        if (isset($data['gameserver'])) {
            self::setGameserver($frame);
            $server->push($frame->fd, json_encode(['s' => 100]));
            swoole_timer_tick(10, function ($timerId) use ($server) {
                $this->broadcast($server, self::$gameworldTable->get('players')['data'] ?? '[]');
            });
        } elseif (isset($data['login'])) {
            self::$playersTable->set($frame->fd, [
                'login' => $data['login']
            ]);
            self::log("Player logged as: {$data['login']} from {$frame->fd}");
            $server->push(self::getGameserverId(), json_encode(
                ['login' => $data['login']]
            ));
        } elseif (isset($data['x']) && (isset($data['y']))) {
            if ($playerData = self::$playersTable->get($frame->fd)) {
                $server->push(self::getGameserverId(), json_encode([
                    'login' => $playerData['login'],
                    'x'     => $data['x'],
                    'y'     => $data['y']
                ]));
            } else {
                self::log("Player from connection {$frame->fd} not found");
            }
        }
    }

    /**
     * @param swoole_websocket_server $server
     * @param $message
     */
    public function broadcast($server, $message)
    {
        foreach ($server->connections as $connection) {
            if (self::$playersTable->get($connection)) {
                $server->push($connection, $message);
            }
        }
    }

    /**
     * @param swoole_websocket_server $server
     * @param int $fd
     */
    public function close($server, $fd)
    {
        echo "connection close: {$fd}\n";
    }

    /**
     * @param swoole_websocket_frame $frame
     */
    static function setGameserver($frame)
    {
        self::log("Setting up gameserver: {$frame->fd}");
        self::$handlersTable->set('gameserver', [
            'value' => $frame->fd
        ]);
    }

    /**
     * @return int
     */
    static function getGameserverId()
    {
        return self::$handlersTable->get('gameserver')['value'] ?? 0;
    }

    /**
     * @param string $message
     * @param int $type
     */
    static function log(string $message, int $type = 0)
    {
        echo "[" . date(DATE_ATOM) . "][$type] $message\n";
    }
}
