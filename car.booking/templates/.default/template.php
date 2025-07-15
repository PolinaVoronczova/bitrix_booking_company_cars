<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="available-cars-list">
    <?php if (!empty($arResult['USERS_AVAILABLE_CARS'])): ?>
        <h2 class="cars-title">Доступные автомобили</h2>
        <div class="cars-container">
            <?php foreach ($arResult['USERS_AVAILABLE_CARS'] as $key => $car): ?>
                <div class="car-item" id="car-<?= $car['ID'] ?>">
                    <div class="car-header">
                        <h4 class="car-name"><?= htmlspecialcharsbx($car['NAME']) ?></h4>
                        <div class="car-model">Модель: <?= htmlspecialcharsbx($car['MODEL']) ?></div>
                    </div>
                    
                    <div class="car-info">
                        <div class="car-number">
                            <span class="detail-label">Номер авто:</span>
                            <span class="detail-value"><?= htmlspecialcharsbx($car['NUMBER']) ?></span>
                        </div>
                        
                        <div class="car-comfort">
                            <span class="detail-label">Категория комфорта:</span>
                            <span class="detail-value">
                                <?= htmlspecialcharsbx($car['COMFORT_CATEGORY']['NAME']) ?>
                            </span>
                        </div>
                        
                        <div class="car-driver">
                            <span class="detail-label">Водитель:</span>
                            <span class="detail-value">
                                <?= htmlspecialcharsbx($car['DRIVER']['NAME']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert">
            На выбранный период нет доступных автомобилей.
        </div>
    <?php endif; ?>
</div>