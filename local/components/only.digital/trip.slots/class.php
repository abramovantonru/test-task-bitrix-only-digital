<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;

Loader::includeModule("highloadblock");

class TripSlots extends CBitrixComponent
{
    public function executeComponent(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();

        // Данные для <select/> текущего сотрудника в UI
        $arResult['MEMBERS'] = $this->getMembers();
        $firstMemberId = $arResult['MEMBERS'][0]['ID'] ?? 0;

        // Получаем текущего сотрудника с фронтенда, но в реальном случае брали бы из сессии
        $memberId = $this->getMemberId($request, $firstMemberId);
        $arResult['MEMBER_ID'] = $memberId;
        $params = ['MEMBER_ID' => $memberId];

        if ($startAt = $this->parseInputDate($request, 'start_trip_date')) {
            $params['START_AT'] = $startAt;
            $arResult['START_AT'] = $startAt->format('Y-m-d\TH:i');
        }

        if ($endAt = $this->parseInputDate($request, 'end_trip_date')) {
            $params['END_AT'] = $endAt;
            $arResult['END_AT'] = $endAt->format('Y-m-d\TH:i');
        }

        // Первый вариант выборки из БД через D7
        $arResult['SLOTS'] = $this->searchTripSlots($params);
        // Второй вариант выборки из БД через SQL
        // $arResult['SLOTS'] = $this->searchTripSlotsRaw($params);

        $this->arResult = $arResult;
        $this->includeComponentTemplate();
    }

    // Делаем пользовательский ввод чистым
    // Исключения не обрабатываем и не выводим пользователю нормальную ошибку, так как не было в ТЗ
    private function getMemberId(Request $request, int $firstMemberId): int
    {
        $memberId = (int)$request->getQuery('member_id');
        if ($memberId > 0) {
            return $memberId;
        }

        $memberId = $firstMemberId;
        if ($memberId > 0) {
            return $memberId;
        }

        throw new \Exception('Member ID is not set');
    }

    private function parseInputDate(Request $request, string $field): ?DateTime
    {
        $dateStr = $request->getQuery($field);
        if (!$dateStr) {
            return null;
        }

        try {
            $dt = new \DateTime($dateStr);
            return DateTime::createFromPhp($dt);
        } catch (\Throwable) {
            throw new \Exception("Invalid date format for $field");
        }
    }

    private function searchTripSlots(array $params): array
    {
        $filter = [
            'UF_MEMBER_ID' => null, // Выбираем только свободные слоты, поле будет заполнено, если кем-то будет занято
        ];
        $dateFilter = [];

        if (!empty($params['START_AT'])) {
            // Время начала поездки должно быть больше времени выхода на смену водителя.
            // А так же оно должно быть меньше времени завершения смены.
            $dateFilter[] = [
                '<=UF_START_AT' => $params['START_AT'],
                '>=UF_END_AT' => $params['START_AT'],
            ];
        }
        if (!empty($params['END_AT'])) {
            // Аналогичная логика для времени конца поездки
            $dateFilter[] = [
                '>=UF_END_AT' => $params['END_AT'],
                '<=UF_START_AT' => $params['END_AT'],
            ];
        }

        if (!empty($dateFilter)) {
            $filter[] = array_merge([
                'LOGIC' => 'AND',
            ], $dateFilter);
        }

        // Нельзя в JOIN (runtime fields) использовать значение, только указать связь по полям.
        // Закомментированный код связан с этим, иначе он бы работал и всё упростил.
        // Из-за этого придётся сделать лишний запрос и узнать должность текущего пользователя.
        // В методе searchTripSlotsRaw реализован правильный JOIN.
        $memberPositionId = $this->getMemberPosition($params['MEMBER_ID']);
        $filter['POSITION_CAR_LEVEL.UF_POSITION_ID'] = $memberPositionId;

        $table = makeHBlockEntity(HBLOCK_ID_TRIP_SLOTS);
        $query = $table::getList([
            'select' => [
                'ID',
                'CAR_REG_NUMBER' => 'CAR.UF_REG_NUMBER',
                'DRIVER_NAME' => 'DRIVER.UF_NAME',
                'DRIVER_PHONE' => 'DRIVER.UF_PHONE',
                'CAR_MODEL_NAME' => 'CAR_MODEL.UF_NAME',
                'CAR_LEVEL_NAME' => 'CAR_LEVEL.UF_NAME',
            ],
            'order' => ['ID' => 'ASC'],
            'filter' => $filter,
            'runtime' => [
                'CAR' => [
                    'data_type' => makeHBlockEntity(HBLOCK_ID_CARS),
                    'reference' => [
                        "this.UF_CAR_ID" => "ref.ID",
                    ],
                    'join_type' => 'inner',
                ],
                'DRIVER' => [
                    'data_type' => makeHBlockEntity(HBLOCK_ID_DRIVERS),
                    'reference' => [
                        "this.CAR.UF_DRIVER_ID" => "ref.ID",
                    ],
                    'join_type' => 'inner',
                ],
                'CAR_MODEL' => [
                    'data_type' => makeHBlockEntity(HBLOCK_ID_CAR_MODELS),
                    'reference' => [
                        "this.CAR.UF_MODEL_ID" => "ref.ID",
                    ],
                    'join_type' => 'inner',
                ],
                'CAR_LEVEL' => [
                    'data_type' => makeHBlockEntity(HBLOCK_ID_CAR_LEVELS),
                    'reference' => [
                        "this.CAR_MODEL.UF_CAR_LEVEL_ID" => "ref.ID",
                    ],
                    'join_type' => 'inner',
                ],
//                'MEMBER' => [
//                    'data_type' => makeHBlockEntity(HBLOCK_ID_MEMBERS),
//                    'reference' => [
//                        "this.UF_MEMBER_ID" => $memberId,
//                    ],
//                    'join_type' => 'inner',
//                ],
                'POSITION_CAR_LEVEL' => [
                    'data_type' => makeHBlockEntity(HBLOCK_ID_POSITION_CAR_LEVELS),
                    'reference' => [
//                        "ref.UF_POSITION_ID" => "this.MEMBER.UF_POSITION_ID",
                        "ref.UF_CAR_LEVEL_ID" => "this.CAR_LEVEL.ID",
                    ],
                    'join_type' => 'inner',
                ],
            ],
        ]);

        return $query->fetchAll();
    }

    // Я бы использовал в этом случае такой запрос, но чтобы показать навыки работы с D7 оставил вариант на HL
    private function searchTripSlotsRaw(array $params): array
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $where = [];
        $memberId = $sqlHelper->convertToDbInteger($params['MEMBER_ID']);

        if (!empty($params['START_AT'])) {
            $filterStartAt = $sqlHelper->convertToDbDateTime($params['START_AT']);
            $where[] = "ts.UF_START_AT <= $filterStartAt";
            $where[] = "ts.UF_END_AT >= $filterStartAt";
        }

        if (!empty($params['END_AT'])) {
            $filterEndAt = $sqlHelper->convertToDbDateTime($params['END_AT']);
            $where[] = "ts.UF_END_AT >= $filterEndAt";
            $where[] = "ts.UF_START_AT <= $filterEndAt";
        }

        $whereStr = '';
        if (!empty($where)) {
            $whereStr = ' AND ' . implode(' AND ', $where);
        }

        $sql = <<<SQL
        SELECT 
            ts.ID AS ID,
            c.UF_REG_NUMBER AS CAR_REG_NUMBER,
            d.UF_NAME AS DRIVER_NAME,
            d.UF_PHONE AS DRIVER_PHONE,
            cm.UF_NAME AS CAR_MODEL_NAME,
            cl.UF_NAME AS CAR_LEVEL_NAME
        FROM trip_slots ts
             INNER JOIN cars c ON ts.UF_CAR_ID = c.ID
             INNER JOIN car_models cm ON c.UF_MODEL_ID = cm.ID
             INNER JOIN car_levels cl ON cm.UF_CAR_LEVEL_ID = cl.ID
             INNER JOIN drivers d ON c.UF_DRIVER_ID = d.ID
             INNER JOIN members m ON m.ID = $memberId
             INNER JOIN position_car_levels pcl
                  ON pcl.UF_POSITION_ID = m.UF_POSITION_ID
                      AND pcl.UF_CAR_LEVEL_ID = cl.ID
        WHERE
            ts.UF_MEMBER_ID IS NULL
            $whereStr
        ORDER BY ts.ID ASC
        SQL;

        $query = $connection->query($sql);
        $rows = $query->fetchAll();

        $connection->stopTracker();

        return $rows;
    }

    private function getMemberPosition(int $memberId): ?int
    {
        $table = makeHBlockEntity(HBLOCK_ID_MEMBERS);
        $row = $table::getById($memberId)->fetch();
        return $row['UF_POSITION_ID'] ?? null;
    }

    private function getMembers(): array
    {
        $taskTable = makeHBlockEntity(HBLOCK_ID_MEMBERS);
        return $taskTable::getList([
            'select' => ['ID', 'UF_NAME', 'POSITION.UF_NAME'],
            'order' => ['ID' => 'ASC'],
            'filter' => [],
            'runtime' => [
                'POSITION' => [
                    'select' => 'POSITION.UF_NAME',
                    'data_type' => makeHBlockEntity(HBLOCK_ID_POSITIONS),
                    'reference' => [
                        "=this.UF_POSITION_ID" => "ref.ID",
                    ],
                    'join_type' => 'inner',
                ],
            ],
        ])->fetchAll();
    }
}
