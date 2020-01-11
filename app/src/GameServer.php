<?php

namespace VundorFuckedPirate;

use Swoole\Client\WebSocket;
use VundorFuckedPirate\Object\Player;

/**
 * Class GameServer
 * @package VundorFuckedPirate
 */
class GameServer
{
    /**
     * @var \swoole_http_client
     */
    public $connection;

    /**
     * @var Player[]
     */
    public $players = [];

    /**
     * @var array
     */
    public $sessions = [];

    /**
     * GameServer constructor.
     * @param $host
     * @param $port
     */
    public function __construct($host, $port)
    {
        $connection = new \swoole_http_client($host, $port);

        $connection->on('message', function ($connection, $frame) {

            $data = json_decode($frame->data, true);

            if (isset($data['login'])) {
                if (!isset($this->players[$data['login']])) {
                    $this->loginNewPlayer($data['login']);
                }

                if (isset($data['x']) && isset($data['y'])) {
                    $this->players[$data['login']]->setPosition($data['x'], $data['y']);
                    var_dump($this->players);
                }

                if (isset($data['world'])) {
                    $this->players[$data['login']]->setCurrentWorld($data['world']);
                }
            }
        });

        $connection->upgrade('/', function ($connection) {
            echo $connection->body;
            $connection->push(json_encode(['gameserver' => 1]));
        });

        $this->connection = $connection;
    }

    /**
     * @param $login
     */
    public function loginNewPlayer($login)
    {
        if ($login) {
            Server::log("Creating new player object: {$login}");
            $player = new Player($login);
            $this->players[$login] = $player;
            $this->sessions[$player->getCurrentSessionToken()] = $player;
        }
    }

    /**
     *
     */
    public function run()
    {
        swoole_timer_tick(100, function ($timerId) {
            $data = [
                'players' => [],
                'sessions' => []
            ];

            /**
             * @var int $playerId
             * @var Player $player
             */
            foreach ($this->players as $playerId => $player) {
                $data['players'][$playerId] = $player->getPublicInfo();
            }

            /**
             * @var Player $player
             */
            foreach ($this->sessions as $sessionId => $player) {
                $data['sessions'][$sessionId] = $player->getLogin();
            }

            Server::$gameworldTable->set('players', [
                'data' => json_encode($data['players'])
            ]);

            Server::$gameworldTable->set('sessions', [
                'data' => json_encode($data['sessions'])
            ]);
        });
    }

}
