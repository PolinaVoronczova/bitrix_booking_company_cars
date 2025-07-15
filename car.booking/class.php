<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;

class IblockListComponent extends CBitrixComponent
{
    /**
     * Подготавливаем входные параметры
     *
     * @param array $arParams
     *
     * @return array
     */

    const IBLOCK_CODE_POSITIONS = 'users_positions';
    const IBLOCK_CODE_CARS = 'cars';

    const HLBLOCK_CODE_BOOKINGS = 'CarBooking';

    public function onPrepareComponentParams($arParams)
    {
        $arParams['USER_ID'] ??= 0;

        return $arParams;
    }
    /**
     * Основной метод выполнения компонента
     *
     * @return void
     */
    public function executeComponent()
    {
        try {
            if ($this->startResultCache()) {
                $this->initResult();
                
                if (empty($this->arResult['USERS_AVAILABLE_CARS'])) {
                    $this->abortResultCache();
                    showError('Доступные машины не найдены.');
                    return;
                }
                
                $this->includeComponentTemplate();
            }
        } catch (Exception $e) {
            $this->abortResultCache();
            showError($e->getMessage());
        }
    }
    /**
     * Инициализируем результат
     *
     * @return void
     */
    private function initResult(): void
    {
        // ?start_booking=13-07-2025%2020:20:00&end_booking=14-07-2025%2023:20:00
        $startBooking = $_REQUEST['start_booking'];
        $endBooking = $_REQUEST['end_booking'];

        if (empty($startBooking) || empty($endBooking)) {
            throw new Exception('Не указаны даты бронирования');
        }

        $availableCars = $this->getAvailableCars($startBooking,$endBooking);

        $this->arResult['USERS_AVAILABLE_CARS'] = $availableCars;
    }

// Получаем категорию комфорта для текущего пользователя
    private function getComfortCategoryForCurrentUser()
    {
        global $USER;
        
        if (!$USER->IsAuthorized()) {
            throw new Exception('Пользователь не авторизирован.');
        }
        
        $userId = $USER->GetID();
        $userData = CUser::GetByID($userId)->Fetch();
        $positionId = $userData['UF_USER_POSITION'];
        
        if (empty($positionId)) {
            throw new Exception('Должность пользователя не указана.');
        }

        $iblockIdPositions = $this->getIDIblock(self::IBLOCK_CODE_POSITIONS);

        $res = CIBlockElement::GetProperty(
            $iblockIdPositions,
            $positionId,
            [],
            ['CODE' => 'COMFORT_CAT']
        );
        
        $comfortCatIds = [];
        while ($property = $res->Fetch()) {
            $comfortCatIds[] = $property['VALUE'];
        }

        if (empty($comfortCatIds)) {
            throw new Exception('Категория комфорта не указана.');
        }
        
        return $comfortCatIds;
    }

// Получаем доступные для бронирования машины

    function getAvailableCars(string $startBooking, string $endBooking): array
    {

        $allCars = [];
        $comfortCatIds = $this->getComfortCategoryForCurrentUser();
        var_dump($comfortCatIds);
        $allCars = [];
        foreach ($comfortCatIds as $comfortCatId) {
            $cars = $this->getCarsByComfortCategory($comfortCatId);
            $allCars = array_merge($allCars, $cars);
        }

        if (empty($allCars)) {
            return [];
        }

        $bookedCars = $this->getBookedCars($startBooking, $endBooking);

        $availableCars = [];

        foreach ($allCars as $key => $car) {
            if (!in_array($key, $bookedCars)) {
                $availableCars[] = $car;
            }
        }

        return $this->prepareCarsResult($availableCars);
    }

// Получаем машины по категории комфорта

    private function getCarsByComfortCategory(int $comfortCatId): array
    {
        $cars = [];

        $iblockIdCars = $this->getIDIblock(self::IBLOCK_CODE_CARS);

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockIdCars,
                'ACTIVE' => 'Y',
                'PROPERTY_CAR_COMFORT_CATEGORY' => $comfortCatId
            ],
            false,
            false,
            [
                'ID',
                'NAME',
                'PROPERTY_CAR_NUMBER',
                'PROPERTY_CAR_MODEL',
                'PROPERTY_CAR_COMFORT_CATEGORY',
                'PROPERTY_CAR_DRIVER'
            ]
        );

        while ($car = $res->Fetch()) {
            $cars[(int)$car['ID']] = $car;
        }

        return $cars;
    }

// Получаем машины которые пересекаются с выбраным временем/датой бронирования

private function getBookedCars(string $startBooking, string $endBooking): array
    {
        $hlblock = HL\HighloadBlockTable::getList(['filter' => ['=NAME' => self::HLBLOCK_CODE_BOOKINGS], 'limit' => 1])->fetch();
        $entity = HL\HighloadBlockTable::compileEntity($hlblock);
        $entityClass = $entity->getDataClass();

        $bookedCars = [];
        $startDate = new DateTime($startBooking);
        $endDate = new DateTime($endBooking);

        $res = $entityClass::getList([],
            ['ACTIVE' => 'Y'],
            false,
            [],
            ['UF_CAR_BOOKING', 'UF_CAR_BOOKING_START','UF_CAR_BOOKING_END']
        );

        while ($booking = $res->fetch()) {
            $startBookedDate = new DateTime($booking['UF_CAR_BOOKING_START']);
            $endBookedDate = new DateTime($booking['UF_CAR_BOOKING_END']);

            if ($endBookedDate->format("d-m-Y H:i:s")
                >= $startDate->format("d-m-Y H:i:s") 
               && $startBookedDate->format("d-m-Y H:i:s")
               <= $endDate->format("d-m-Y H:i:s")) {
                $bookedCars[] = (int)$booking['UF_CAR_BOOKING'];
            }
        }

        return array_unique($bookedCars);
    }

// Переводим массив полученных машин в удобный формат

    private function prepareCarsResult(array $cars): array
    {
        $result = [];
        
        foreach ($cars as $id => $car) {
            $result[$id] = [
                'NAME' => $car['NAME'],
                'MODEL' => $car['PROPERTY_CAR_MODEL_VALUE'],
                'NUMBER' => $car['PROPERTY_CAR_NUMBER_VALUE'],
                'COMFORT_CATEGORY' => [
                    'ID' => $car['PROPERTY_CAR_COMFORT_CATEGORY_VALUE'],
                    'NAME' => $this->getLinkedElementName($car['PROPERTY_CAR_COMFORT_CATEGORY_VALUE'])
                ],
                'DRIVER' => [
                    'ID' => $car['PROPERTY_CAR_DRIVER_VALUE'],
                    'NAME' => $this->getLinkedElementName($car['PROPERTY_CAR_DRIVER_VALUE'])
                ]
            ];
        }

        return $result;
    }

// Получаем имена связанных элементов

    private function getLinkedElementName($elementId)
    {
        if (!$elementId) return null;
        
        $res = \CIBlockElement::GetByID($elementId);
        if ($element = $res->GetNext()) {
            return $element['NAME'];
        }
        
        return null;
    }

// Получаем id инфоблоков
    private function getIDIblock($iblockCode)
    {
        $res = CIBlock::GetList([], ["CODE"=>$iblockCode]);

        while ($element = $res->Fetch()) {
            return $element['ID'];
        }
    }

}
?>