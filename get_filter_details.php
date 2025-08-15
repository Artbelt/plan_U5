<?php
// Подключение к БД
$pdo = new PDO('mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$filter_name = $_GET['id'] ?? '';
$filter_name = trim($filter_name);

if (!$filter_name) {
    echo "Фильтр не указан.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM salon_filter_structure WHERE filter = ?");
$stmt->execute([$filter_name]);
$filter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$filter) {
    echo "Фильтр не найден.";
    exit;
}

// Связанные данные
$paper = null;
if ($filter['paper_package']) {
    $stmt = $pdo->prepare("SELECT * FROM paper_package_salon WHERE p_p_name = ?");
    $stmt->execute([$filter['paper_package']]);
    $paper = $stmt->fetch(PDO::FETCH_ASSOC);
}

$box = null;
if ($filter['box']) {
    $stmt = $pdo->prepare("SELECT * FROM box WHERE b_name = ?");
    $stmt->execute([$filter['box']]);
    $box = $stmt->fetch(PDO::FETCH_ASSOC);
}

$gbox = null;
if ($filter['g_box']) {
    $stmt = $pdo->prepare("SELECT * FROM g_box WHERE gb_name = ?");
    $stmt->execute([$filter['g_box']]);
    $gbox = $stmt->fetch(PDO::FETCH_ASSOC);
}

$tariff = null;
if ($filter['tariff_id']) {
    $stmt = $pdo->prepare("SELECT * FROM salary_tariffs WHERE id = ?");
    $stmt->execute([$filter['tariff_id']]);
    $tariff = $stmt->fetch(PDO::FETCH_ASSOC);
}

function yesno($v) {
    //return ($v && $v !== 'нет' && $v !== '0') ? '✓' : '—';
    return ($v && $v !== 'нет' && $v !== '0') ? $v : '---';
}

// Фото
$photo_name = preg_replace('/[^a-zA-Z0-9а-яА-Я_-]+/u', '_', $filter['filter']) . '.jpg';
$photo_path = 'photo/' . $photo_name;
$photo_full_path = __DIR__ . '/' . $photo_path;
//$photo_html = file_exists($photo_full_path)
//    ? "<img src='{$photo_path}' alt='Фото {$filter['filter']}' style='max-width:95%; max-height:600px; display:block; margin:10px auto; border:1px solid #ccc; border-radius:5px;'>"
//    : "<div style='font-size:12px; color:#666; text-align:center; margin:20px;'>Фото не найдено</div>";
if (file_exists($photo_full_path)) {
    $photo_html = "<img src='{$photo_path}' alt='Фото {$filter['filter']}' style='max-width:95%; max-height:600px; display:block; margin:10px auto; border:1px solid #ccc; border-radius:5px;'>";
} else {
    // Попытка найти аналог из комментария
    $analog_photo = null;
    if (!empty($filter['comment'])) {
        // Ищем в комментарии шаблон AF + 4 цифры
        if (preg_match('/AF\d{4}/', $filter['comment'], $matches)) {
            $analog_name = $matches[0];
            $analog_photo_path = "photo/{$analog_name}.jpg"; // путь к фото аналога
            $analog_photo_full_path = $_SERVER['DOCUMENT_ROOT'] . '/plan_U5/' . $analog_photo_path;
            //echo $analog_photo_full_path;
            if (file_exists($analog_photo_full_path)) {
                $analog_photo = "<img src='{$analog_photo_path}' alt='Фото аналога {$analog_name}' style='max-width:95%; max-height:600px; display:block; margin:10px auto; border:1px solid #ccc; border-radius:5px;'>";
            }
        }
    }

    // Если нашли фото аналога
    $photo_html = $analog_photo ?: "<div style='font-size:12px; color:#666; text-align:center; margin:20px;'>Фото не найдено</div>";
}


?>

<!-- Контейнер с прокруткой -->
<div style="display:flex; flex-direction:column; gap:15px; font-size:14px; line-height:1.5; max-height:80vh; overflow-y:auto;">

    <!-- Заголовок -->
    <h3 style="margin:0; text-align:center;"><?php echo htmlspecialchars($filter['filter']); ?></h3>
    <!-- <div style="text-align:center; font-weight:bold;">Категория: <?php echo htmlspecialchars($filter['category']); ?></div> -->

    <!-- Блоки с данными -->
    <div class="filter-blocks" style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:15px;">
        <div style="flex:1; min-width:180px;">
            <strong>Гофропакет:</strong><br>
            <?php if ($paper): ?>
                Размеры: <?php echo $paper['p_p_width'] . " × " . $paper['p_p_height']; ?> мм<br>
                Кол-во ребер: <?php echo $paper['p_p_pleats_count']; ?><br>
                Материал: <?php echo $paper['p_p_material']; ?><br>
            <!--    - Поставщик: <?php echo $paper['p_p_supplier']; ?><br> -->
                Комментарий: <?php echo nl2br($paper['p_p_remark']); ?><br>
            <?php else: ?>
                Не указан<br>
            <?php endif; ?>
        </div>

        <div style="flex:1; min-width:180px;">
            <strong>
                <?php echo ($filter['insertion_count'] !== '' ? ('Количество вставок: '.$filter['insertion_count']) : ''); ?>
            </strong><br>
            <strong>Лента боковая: <?php echo $filter['side_type'] ?></strong><br>
            <strong>
                <?php echo ($filter['foam_rubber'] !== '' ? $filter['foam_rubber'] : ''); ?>
            </strong><br>
            <strong>
                <?php echo ($filter['tail'] !== '' ? $filter['tail'] : ''); ?>
            </strong><br>
            <strong>
                <?php echo ($filter['form_factor'] !== '' ? $filter['form_factor'] : ''); ?>
            </strong><br>
            <strong>
                <?php echo ($filter['has_edge_cuts'] !== 0 ? 'есть надрезы' : ''); ?>
            </strong><br>
        </div>

        <div style="flex:1; min-width:180px;">
            <strong>Упаковка:</strong><br>
            <?php if ($box): ?>
                Коробка: <?php echo $filter['box']; ?><br>
            <?php else: ?>
                Коробка: нет<br>
            <?php endif; ?>

            <?php if ($gbox): ?>
                Ящик: <?php echo $filter['g_box']; ?><br>
            <?php else: ?>
                Ящик: нет<br>
            <?php endif; ?><br>
            <!--
            <strong>▶ Тариф:</strong><br>
            <?php if ($tariff): ?>
                - <?php echo $tariff['tariff_name'] . " — " . $tariff['rate_per_unit'] . "₴ / " . $tariff['type']; ?><br>
            <?php else: ?>
                - Не указан<br>
            <?php endif; ?>
            -->
        </div>
    </div>

    <!-- Комментарий -->
    <div style="margin-top:0px;">
    <strong>
        <?php echo ($filter['comment'] !== '' ? ('Комментарий: '.$filter['comment']) : ''); ?>
    </strong><br>
    </div>
    <!-- Фото снизу -->
    <?php echo $photo_html; ?>
</div>

<style>
    @media (max-width: 600px) {
        .filter-blocks {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>
