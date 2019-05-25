<?php

namespace VundorFuckedPirate\Object;

/**
 * Class Player
 * @package VundorFuckedPirate\Object
 */
class Player
{
    public $login;
    public $x = 0;
    public $y = 0;

    /**
     * Player constructor.
     * @param $id
     */
    public function __construct($login)
    {
        $this->login = $login;
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
    public function getPosition(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y
        ];
    }

    /**
     * @return array
     */
    public function getBasicInfo()
    {
        return [
            'position' => $this->getPosition()
        ];
    }

}