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
    public $connection;
    public $players = [];

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
        Server::log("Creating new player object: {$login}");
        $this->players[$login] = new Player($login);
    }

    public function run()
    {
        swoole_timer_tick(100, function ($timerId) {
            $data = [
                'players' => [],
            ];

            /**
             * @var int $playerId
             * @var Player $player
             */
            foreach ($this->players as $playerId => $player) {
                $data['players'][$playerId] = $player->getBasicInfo();
            }

            Server::$gameworldTable->set('players', [
                'data' => json_encode($data)
            ]);
        });
    }

}