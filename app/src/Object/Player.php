<?php

namespace VundorFuckedPirate\Object;

/**
 * Class Player
 * @package VundorFuckedPirate\Object
 */
class Player
{
    const WORLD_PARAM = 'w';
    const MESSAGE_PARAM = 'm';
    const LOGIN_PARAM = 'l';
    const POSITION_PARAM = 'p';

    /**
     * @var string
     */
    public $login;

    /**
     * @var int
     */
    public $x = 0;

    /**
     * @var int
     */
    public $y = 0;

    /**
     * @var string
     */
    public $currentWorld = '';

    /**
     * @var string
     */
    public $currentSessionToken = '';

    /**
     * Player constructor.
     * @param $id
     */
    public function __construct($login)
    {
        $this->login = $login;
        $this->regenerateSessionToken();
    }

    /**
     * Refresh session token and set it on player
     */
    public function regenerateSessionToken()
    {
        $sessionToken = substr(md5($this->login ?: uniqid()), 0, 6) . uniqid(null) . round(pow(microtime(true) * 10000, 1.01));
        usleep(rand(1, 1000));
        $sessionToken .= uniqid(null);

        $this->currentSessionToken = $sessionToken;
    }

    /**
     * @param $x
     * @param $y
     * @return bool
     */
    public function setPosition($x, $y): bool
    {
        $this->x = $x;
        $this->y = $y;

        return true;
    }

    /**
     * @return array
     */
    public function getPublicInfo()
    {
        return [
            Player::POSITION_PARAM => $this->getPosition(),
            Player::WORLD_PARAM    => $this->getCurrentWorld()
        ];
    }

    /**
     * @return string
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @return array
     */
    public function getPosition(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y
        ];
    }

    /**
     * @return string
     */
    public function getCurrentWorld(): string
    {
        return $this->currentWorld;
    }

    /**
     * @param $world
     * @return bool
     */
    public function setCurrentWorld($world): bool
    {
        $this->currentWorld = $world;

        return true;
    }

    /**
     * @return string
     */
    public function getCurrentSessionToken()
    {
        return $this->currentSessionToken;
    }

}
