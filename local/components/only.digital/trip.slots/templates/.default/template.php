<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
$currentMember = $arResult['MEMBER_ID'] ?? 0;
?>

<section>
    <h2>Доступные автомобили для поездки</h2>

    <form action="/">
        <label for="fieldMemberId">
            Сотрудник
        </label>
        <select name="member_id" id="fieldMemberId">
            <?foreach ($arResult['MEMBERS'] as $member):?>
                <option
                    <?if($member['ID'] == $currentMember):?> selected="selected" <?endif;?>
                    value="<?= $member['ID'] ?>"
                ><?= "$member[UF_NAME] ($member[MEMBER_POSITION_UF_NAME])" ?></option>
            <?endforeach?>
        </select>
        <hr>
        <label for="fieldStartTripDate">
            Время начала поездки
        </label>
        <input type="datetime-local" name="start_trip_date" id="fieldStartTripDate" value="<?= $arResult['START_AT'] ?? '' ?>">
        <hr>
        <label for="fieldEndTripDate">
            Время конца поездки
        </label>
        <input type="datetime-local" name="end_trip_date" id="fieldEndTripDate" value="<?= $arResult['END_AT'] ?? '' ?>">
        <hr>
        <button class="form__button" type="submit">Найти</button>
    </form>

    <?if(!empty($arResult['SLOTS'])):?>
        <ul id="slotsList">
            <?foreach ($arResult['SLOTS'] as $slot):
                $tel = preg_replace("/[^0-9]/", '', $slot['DRIVER_PHONE']);
                ?>
                <li>
                    <p>
                        Водитель: <?= $slot['DRIVER_NAME'] ?> <a href="tel:+<?= $tel ?>"><?= $slot['DRIVER_PHONE'] ?></a> <br>
                        Автомобиль: <br>
                        &nbsp;&nbsp;&nbsp;&nbsp; Номер: <?= $slot['CAR_REG_NUMBER'] ?> <br>
                        &nbsp;&nbsp;&nbsp;&nbsp; Модель: <?= $slot['CAR_MODEL_NAME'] ?> <br>
                        &nbsp;&nbsp;&nbsp;&nbsp; Уровень комфорта: <?= $slot['CAR_LEVEL_NAME'] ?> <br>
                    </p>
                </li>
            <?endforeach?>
        </ul>
    <?else:?>
        <p>Нет доступных автомобилей для поездки</p>
    <?endif;?>
</section>
