<?php

require_once('tools/tools.php');

// Загружаем все фильтры с текущим тарифом и материалом
$sql = "
    SELECT 
        sfs.filter,
        sfs.insertion_count,
        sfs.foam_rubber,
        sfs.tariff_id,
        st.tariff_name,
        pps.p_p_material
    FROM 
        salon_filter_structure sfs
    LEFT JOIN 
        salary_tariffs st ON sfs.tariff_id = st.id
    LEFT JOIN 
        paper_package_salon pps ON sfs.paper_package = pps.p_p_name
";
$filters = mysql_execute($sql);

// Загружаем все тарифы в массив вида [название => id]
$tariffs = mysql_execute("SELECT id, tariff_name FROM salary_tariffs");
$tariff_lookup = [];
foreach ($tariffs as $t) {
    $tariff_lookup[mb_strtolower($t['tariff_name'])] = $t['id'];
}

// Проверка и исправление
foreach ($filters as $f) {
    $filter = $f['filter'];
    $insertions = (int)$f['insertion_count'];
    $foam = trim(mb_strtolower($f['foam_rubber'] ?? ''));
    $material = trim(mb_strtolower($f['p_p_material'] ?? ''));
    $current_tariff = trim(mb_strtolower($f['tariff_name'] ?? ''));

    // Логика определения ожидаемого тарифа
    if ($insertions > 0 && $foam !== '') {
        $expected = 'с поролоном';
    } elseif ($foam !== '') {
        $expected = 'с поролоном';
    } elseif ($insertions > 0) {
        $expected = 'со вставками';
    } else {
        $expected = 'простой';
    }

    // Добавляем приставку CARBON если материал = CARBON
    if ($material === 'carbon') {
        $expected .= ' carbon';
    }

    if ($current_tariff !== $expected) {
        if (!isset($tariff_lookup[$expected])) {
            echo "❌ Не найден тариф '$expected' для фильтра $filter<br>";
            continue;
        }

        $new_id = $tariff_lookup[$expected];
        mysql_execute("UPDATE salon_filter_structure SET tariff_id = $new_id WHERE filter = '$filter'");
        echo "✅ Обновлён тариф для $filter: $f[tariff_name] → $expected<br>";
    }
}

echo "<br><b>Проверка и обновление завершены.</b>";

?>
