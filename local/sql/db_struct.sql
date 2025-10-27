-- Создаём индексы для FK, так как по ним часто выборки и это best-practice
CREATE INDEX `IDX_CAR_LEVEL` ON `car_models` (`UF_CAR_LEVEL_ID`);
CREATE INDEX `IDX_CAR_MODEL` ON `cars` (`UF_MODEL_ID`);
CREATE INDEX `IDX_CAR_DRIVER` ON `cars` (`UF_DRIVER_ID`);
CREATE INDEX `IDX_MEMBER_POSITION` ON `members` (`UF_POSITION_ID`);

CREATE INDEX `IDX_POSITION_CAR_LEVEL_POSITION` ON `position_car_levels` (`UF_POSITION_ID`);
CREATE INDEX `IDX_POSITION_CAR_LEVEL_CAR_LEVEL` ON `position_car_levels` (`UF_CAR_LEVEL_ID`);

CREATE INDEX `IDX_TRIP_CAR` ON `trip_slots` (`UF_CAR_ID`);
CREATE INDEX `IDX_TRIP_MEMBER` ON `trip_slots` (`UF_MEMBER_ID`);

-- Индексы для оптимизации выборки по датам
CREATE INDEX `IDX_TRIP_START_AT` ON `trip_slots` (`UF_START_AT`);
CREATE INDEX `IDX_TRIP_END_AT` ON `trip_slots` (`UF_END_AT`);

-- Только уникальные телефоны, чтобы избегать дублирования людей.
-- Best-practice здесь будет хранить в строке только числа, так как плюс легко добавить как префикс, а
-- форматирование иногда легко добавляется (как в случае с номерами России), либо чуть сложнее через регулярки/шаблоны
-- по странам или готовые библиотеки.
-- Для поля с типом данных TEXT придётся указать длину индекса, но в этом случае правильно было в целом сменить его
-- на VARCHAR(20) или что-то вроде этого. По стандарту длина номера 15 символов, можно ориентироваться на это.
-- Тип поля TEXT кажется удобным, так как хранит столько, сколько нам нужно, но движок по-разному работает
-- с TEXT и VARCHAR и при выборках будут просадки в производительности, так как БД не знает какая возможная длина
-- колонки и может, например, вытеснять из кэша или создавать временные таблицы на диске.
ALTER TABLE `drivers`
    ADD UNIQUE INDEX `UQ_DRIVERS_PHONE` (`UF_PHONE`(20));
ALTER TABLE `members`
    ADD UNIQUE INDEX `UQ_MEMBERS_PHONE` (`UF_PHONE`(20));

-- Уникальный индекс, чтобы не дублировались должности и уровня комфорта
ALTER TABLE `position_car_levels`
    ADD CONSTRAINT `UQ_POSITION_CAR_LEVEL`
        UNIQUE (`UF_POSITION_ID`, `UF_CAR_LEVEL_ID`);

-- Сделаем поля обязательными и UNSIGNED, так как Битрикс сам этого не делает
ALTER TABLE `car_models`
    MODIFY COLUMN `UF_CAR_LEVEL_ID` INT UNSIGNED NOT NULL;

-- Водителя делаем не обязательным
ALTER TABLE `cars`
    MODIFY COLUMN `UF_MODEL_ID` INT UNSIGNED NOT NULL,
    MODIFY COLUMN `UF_DRIVER_ID` INT UNSIGNED DEFAULT NULL;

ALTER TABLE `members`
    MODIFY COLUMN `UF_POSITION_ID` INT UNSIGNED NOT NULL;

ALTER TABLE `position_car_levels`
    MODIFY COLUMN `UF_POSITION_ID` INT UNSIGNED NOT NULL,
    MODIFY COLUMN `UF_CAR_LEVEL_ID` INT UNSIGNED NOT NULL;

-- Время начала и конца смены делаем обязательными, но не делаем обязательным MEMBER_ID, так как по IS NULL можно
-- понять, что место ещё не занято
ALTER TABLE `trip_slots`
    MODIFY COLUMN `UF_CAR_ID` INT UNSIGNED NOT NULL,
    MODIFY COLUMN `UF_START_AT` DATETIME NOT NULL,
    MODIFY COLUMN `UF_END_AT` DATETIME NOT NULL,
    MODIFY COLUMN `UF_MEMBER_ID` INT UNSIGNED DEFAULT NULL;

-- Добавляем внешний ключ, при удалении и обновлении выполняем каскадное действие
ALTER TABLE `car_models`
    ADD CONSTRAINT `FK_CAR_LEVEL`
        FOREIGN KEY (`UF_CAR_LEVEL_ID`)
            REFERENCES `car_levels` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE;

-- При удалении водителя убираем из авто связь с ним, но не удаляем авто,
-- нужно учесть в запросах, что не нужно выбирать авто без водителей
ALTER TABLE `cars`
    ADD CONSTRAINT `FK_CAR_MODEL`
        FOREIGN KEY (`UF_MODEL_ID`)
            REFERENCES `car_models` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    ADD CONSTRAINT `FK_CAR_DRIVER`
        FOREIGN KEY (`UF_DRIVER_ID`)
            REFERENCES `drivers` (`ID`)
            ON DELETE SET NULL
            ON UPDATE CASCADE;

-- При удалении должности удаляем сотрудников, так как подразумевается, что при реальном сокращении должностей
-- необходимым сотрудникам сменят должность. Либо может быть сценарий, что под мероприятие создаётся должность
-- с временным персоналом и удаляется после него.
ALTER TABLE `members`
    ADD CONSTRAINT `FK_MEMBER_POSITION`
        FOREIGN KEY (`UF_POSITION_ID`)
            REFERENCES `positions` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE;

-- Удаляем каскадно при удалении должности или уровня комфорта
ALTER TABLE `position_car_levels`
    ADD CONSTRAINT `FK_PCL_POSITION`
        FOREIGN KEY (`UF_POSITION_ID`)
            REFERENCES `positions` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    ADD CONSTRAINT `FK_PCL_LEVEL`
        FOREIGN KEY (`UF_CAR_LEVEL_ID`)
            REFERENCES `car_levels` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE;

-- При удалении авто или сотрудника удаляем слоты, но не удаляем авто или сотрудника
ALTER TABLE `trip_slots`
    ADD CONSTRAINT `FK_TRIP_SLOT_CAR`
        FOREIGN KEY (`UF_CAR_ID`)
            REFERENCES `cars` (`ID`)
            ON DELETE CASCADE
            ON UPDATE CASCADE,
    ADD CONSTRAINT `FK_TRIP_SLOT_MEMBER`
        FOREIGN KEY (`UF_MEMBER_ID`)
            REFERENCES `members` (`ID`)
            ON DELETE SET NULL
            ON UPDATE CASCADE;
