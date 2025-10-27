<?php
require_once('tools/tools.php');
require_once('settings.php');

// Получаем название фильтра
$filter_name = $_POST['filter_name'] ?? '';

if (empty($filter_name)) {
    echo '<p style="color: #dc2626;">Ошибка: не указано название фильтра</p>';
    exit;
}

// Отладочная информация
echo "<!-- Отладка: filter_name = '" . htmlspecialchars($filter_name) . "' -->";

/**
 * Получает конструктивные параметры фильтра из salon_filter_structure и paper_package_salon
 */
function get_filter_structure($filter_name) {
    global $mysql_host, $mysql_user, $mysql_user_pass, $mysql_database;
    
    try {
        $pdo = new PDO("mysql:host=$mysql_host;dbname=$mysql_database", $mysql_user, $mysql_user_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Сначала проверим, есть ли фильтр в основной таблице
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM salon_filter_structure WHERE filter = ?");
        $stmt->execute([$filter_name]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo "<!-- Отладка: найдено записей в salon_filter_structure = " . $count . " -->";
        
        if ($count == 0) {
            // Попробуем найти похожие фильтры
            $stmt = $pdo->prepare("SELECT filter FROM salon_filter_structure WHERE filter LIKE ? LIMIT 5");
            $stmt->execute(['%' . $filter_name . '%']);
            $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<!-- Отладка: похожие фильтры: " . implode(', ', $similar) . " -->";
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                sfs.*,
                pps.p_p_height as height,
                pps.p_p_width as width,
                pps.p_p_pleats_count as ribs_count,
                pps.p_p_material as material
            FROM salon_filter_structure sfs
            LEFT JOIN paper_package_salon pps ON CONCAT('гофропакет ', sfs.filter) = pps.p_p_name
            WHERE sfs.filter = ?
        ");
        $stmt->execute([$filter_name]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<!-- Отладка: результат запроса: " . ($result ? 'найден' : 'не найден') . " -->";
        
        return $result;
    } catch (PDOException $e) {
        echo "<!-- Отладка: ошибка БД: " . htmlspecialchars($e->getMessage()) . " -->";
        return false;
    }
}

// Получаем информацию о структуре фильтра
$filter_structure = get_filter_structure($filter_name);

if ($filter_structure === false) {
    echo '<div style="text-align: center; padding: 20px;">';
    echo '<p style="color: #f59e0b; font-size: 18px; font-weight: bold;">⚠️ Фильтр не найден в базе данных</p>';
    echo '<p style="color: #64748b;">Информация о конструктивных параметрах отсутствует.</p>';
    echo '</div>';
    exit;
}

// Функция для получения значения с проверкой
function getValue($value) {
    return !empty($value) ? htmlspecialchars($value) : '<span style="color: #9ca3af;">—</span>';
}

// Функция для отображения Да/Нет
function getYesNo($value) {
    if (empty($value)) {
        return '<span style="color: #9ca3af;">—</span>';
    }
    $truthy = ['on','1',1,true,'checked','yes','да','Да','true','True'];
    $isYes = in_array($value, $truthy, true);
    if ($isYes) {
        return '<span style="color: #059669; font-weight: 600;">Да</span>';
    } else {
        return '<span style="color: #dc2626; font-weight: 600;">Нет</span>';
    }
}

// Функция для поиска изображения фильтра
function findFilterImage($filter_name, $comment = '') {
    $photo_dir = 'photo/';
    $extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Список возможных названий файлов для поиска
    $search_names = [];
    
    // 1. Точное название
    $search_names[] = $filter_name;
    
    // 2. Если название содержит 'a', добавляем варианты без 'a'
    if (strpos($filter_name, 'a') !== false) {
        $search_names[] = str_replace('a', '', $filter_name);
    }
    
    // 3. Если название не содержит 'a', добавляем с 'a'
    if (strpos($filter_name, 'a') === false) {
        $search_names[] = $filter_name . 'a';
    }
    
    // 4. Варианты с суффиксами -2, -4
    $base_name = preg_replace('/[a-]+$/', '', $filter_name); // убираем 'a' и суффиксы
    $search_names[] = $base_name . '-2';
    $search_names[] = $base_name . 'a-2';
    $search_names[] = $base_name . '-4';
    $search_names[] = $base_name . 'a-4';
    
    // 5. Если есть комментарий, ищем аналог
    if (!empty($comment)) {
        // Ищем паттерны типа "аналог AF1234" или "analog AF1234"
        if (preg_match('/(?:аналог|analog)\s+([A-Z0-9]+(?:[a-]+)?)/i', $comment, $matches)) {
            $analog_name = $matches[1];
            $search_names[] = $analog_name;
            
            // Добавляем варианты аналога
            if (strpos($analog_name, 'a') !== false) {
                $search_names[] = str_replace('a', '', $analog_name);
            } else {
                $search_names[] = $analog_name . 'a';
            }
        }
    }
    
    // Убираем дубликаты
    $search_names = array_unique($search_names);
    
    // Ищем файлы
    foreach ($search_names as $name) {
        foreach ($extensions as $ext) {
            $file_path = $photo_dir . $name . '.' . $ext;
            if (file_exists($file_path)) {
                return $file_path;
            }
        }
    }
    
    return null;
}

// Функция для отображения картинки, если есть
function displayImage($filter_name, $comment = '') {
    $image_path = findFilterImage($filter_name, $comment);
    
    if ($image_path) {
        return '<div style="text-align: center; margin: 10px 0;">
                    <img src="' . htmlspecialchars($image_path) . '" alt="Схема фильтра" style="max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                </div>';
    }
    
    return '<div style="text-align: center; padding: 40px; background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 8px; color: #6b7280;">
                Схема фильтра отсутствует
            </div>';
}
?>

<div style="font-family: 'Segoe UI', Arial, sans-serif; background: white; padding: 0; margin: 0;">
    <!-- Заголовок -->
    <div style="margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 24px; font-weight: bold; color: #1f2937;"><?= htmlspecialchars($filter_name) ?></h2>
        <span style="display: inline-block; padding: 4px 12px; background: #f3f4f6; border-radius: 999px; font-size: 12px; color: #6b7280; margin-top: 8px;">Категория: Салонный</span>
        <div style="font-size: 12px; color: #6b7280; margin-top: 8px;">Ниже собраны основные параметры фильтра и упаковки.</div>
    </div>

    <!-- Основное содержимое в две колонки -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- Левая колонка -->
        <div>
            <!-- Блок "Гофропакет" -->
            <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Гофропакет</h3>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Ширина шторы:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['width'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Высота шторы:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['height'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Кол-во рёбер:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['ribs_count'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Поставщик:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;">У5</span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Материал:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['material'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Комментарий:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['comment'] ?? '') ?></span>
                </div>
            </div>

            <!-- Блок "Упаковка" -->
            <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Упаковка</h3>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Индивидуальная упак. (Коробка №):</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['box'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Групповая упак. (Ящик №):</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['g_box'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Примечание:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;">—</span>
                </div>
            </div>
        </div>

        <!-- Правая колонка -->
        <div>
            <!-- Блок "Вставка" -->
            <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Вставка</h3>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Кол-во в фильтре:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['insertion_count'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Поставщик:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;">У5</span>
                </div>
            </div>

            <!-- Блок "Боковая лента" -->
            <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Боковая лента</h3>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Высота ленты:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getValue($filter_structure['side_type'] ?? '') ?></span>
                </div>
            </div>

            <!-- Блок "Особенности" -->
            <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Особенности</h3>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Поролон:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getYesNo($filter_structure['foam_rubber'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #f3f4f6;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Язычок:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getYesNo($filter_structure['tail'] ?? '') ?></span>
                </div>
                <div class="param-row" style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
                    <span class="param-label" style="color: #6b7280; font-weight: 600; font-size: 12px;">Трапеция:</span>
                    <span class="param-value" style="color: #374151; font-size: 12px;"><?= getYesNo($filter_structure['form_factor'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Схема фильтра -->
    <div style="margin-top: 20px;">
        <h3 class="section-title" style="font-size: 14px; font-weight: 700; margin: 0 0 12px 0; color: #374151;">Схема фильтра</h3>
        <div class="card" style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <?= displayImage($filter_name, $filter_structure['comment'] ?? '') ?>
        </div>
    </div>

    <!-- Отладочная информация -->
    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
        <div class="small" style="font-size: 11px; color: #9ca3af;">Источник данных: get_salon_filter_data("<?= htmlspecialchars($filter_name) ?>")</div>
    </div>
</div>
