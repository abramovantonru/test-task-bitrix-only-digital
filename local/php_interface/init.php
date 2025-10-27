<?php

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Entity\DataManager;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}


const HBLOCK_ID_CAR_LEVELS = 1;
const HBLOCK_ID_CAR_MODELS = 2;
const HBLOCK_ID_CARS = 3;

const HBLOCK_ID_DRIVERS = 4;
const HBLOCK_ID_POSITIONS = 5;

const HBLOCK_ID_POSITION_CAR_LEVELS = 6;
const HBLOCK_ID_MEMBERS = 7;
const HBLOCK_ID_TRIP_SLOTS = 8;


function makeHBlockEntity(int $hblockId): DataManager|string
{
    return HighloadBlockTable::compileEntity($hblockId)->getDataClass();
}
