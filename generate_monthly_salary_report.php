<?php
require_once('tools/tools.php');

// Получаем выбранный месяц (формат: YYYY-MM)
$month = $_POST['month'] ?? date('Y-m');
$period = $_POST['period'] ?? 'full';
list($year, $month_num) = explode('-', $month);

// Определяем начало и конец периода
$first_day = "$year-$month_num-01";
$last_day_of_month = date('Y-m-t', strtotime($first_day));

switch ($period) {
    case 'first':
        $last_day = "$year-$month_num-15";
        break;
    case 'second':
        $first_day = "$year-$month_num-16";
        $last_day = $last_day_of_month;
        break;
    default: // 'full'
        $last_day = $last_day_of_month;
        break;
}

// Загружаем доплаты из БД
$addition_rows = mysql_execute("SELECT code, amount FROM salary_additions");
$additions = [];
foreach ($addition_rows as $a) {
    $additions[$a['code']] = (float)$a['amount'];
}

// Загружаем ранее сохраненные часы
$hours_raw = mysql_execute("SELECT filter, order_number, hours FROM hourly_work_log");
$hours_map = [];
foreach ($hours_raw as $h) {
    $key = $h['filter'] . '_' . $h['order_number'];
    $hours_map[$key] = $h['hours'];
}

// Загружаем данные производства за месяц с тарифами
$sql = "
    SELECT 
        mp.date_of_production,
        mp.name_of_filter,
        mp.count_of_filters,
        mp.name_of_order,
        mp.team,
        sfs.insertion_count,
        sfs.foam_rubber,
        sfs.form_factor,
        sfs.tail,
        sfs.has_edge_cuts,
        pps.p_p_material,
        st.rate_per_unit,
        st.type,
        st.tariff_name,
        st.id as tariff_id
    FROM manufactured_production mp
    LEFT JOIN (
        SELECT 
            filter,
            MAX(insertion_count) AS insertion_count,
            MAX(foam_rubber) AS foam_rubber,
            MAX(form_factor) AS form_factor,
            MAX(tail) AS tail,
            MAX(has_edge_cuts) AS has_edge_cuts,
            MAX(paper_package) AS paper_package,
            MAX(tariff_id) AS tariff_id
        FROM salon_filter_structure
        GROUP BY filter
    ) sfs ON sfs.filter = mp.name_of_filter
    LEFT JOIN (
        SELECT 
            p_p_name,
            MAX(p_p_material) AS p_p_material
        FROM paper_package_salon
        GROUP BY p_p_name
    ) pps ON pps.p_p_name = sfs.paper_package
    LEFT JOIN salary_tariffs st ON st.id = sfs.tariff_id
    WHERE mp.date_of_production BETWEEN '$first_day' AND '$last_day'
    ORDER BY mp.date_of_production, mp.team
";

$result = mysql_execute($sql);

// Структура данных: [бригада][тариф][день] = ['count' => количество, 'rate' => тариф]
$brigade_data = [
    '1-2' => [], // Бригады 1 и 2
    '3-4' => []  // Бригады 3 и 4
];

// Множество всех тарифов с их ставками
$all_tariffs = [];

// Обрабатываем данные
foreach ($result as $row) {
    $team = (int)$row['team'];
    $brigade = ($team == 1 || $team == 2) ? '1-2' : '3-4';
    
    $tariff_name = $row['tariff_name'] ?? 'Без тарифа';
    $tariff_id = $row['tariff_id'] ?? 0;
    
    // Применяем доплаты
    $base_rate = (float)($row['rate_per_unit'] ?? 0);
    $tail = mb_strtolower(trim($row['tail'] ?? ''));
    $form = mb_strtolower(trim($row['form_factor'] ?? ''));
    $has_edge_cuts = trim($row['has_edge_cuts'] ?? '');
    $tariff_type = strtolower(trim($row['type'] ?? ''));
    $tariff_name_lower = mb_strtolower(trim($tariff_name));
    
    $is_hourly = $tariff_name_lower === 'почасовый';
    $apply_additions = $tariff_type !== 'fixed' && !$is_hourly;
    
    $description_parts = [];
    $final_rate = $base_rate;
    
    if ($apply_additions && strpos($tail, 'языч') !== false && isset($additions['tongue_glue'])) {
        $final_rate += $additions['tongue_glue'];
        $description_parts[] = '+язычок';
    }
    if ($apply_additions && $form === 'трапеция' && isset($additions['edge_trim_glue'])) {
        $final_rate += $additions['edge_trim_glue'];
        $description_parts[] = '+трапеция';
    }
    if ($apply_additions && !empty($has_edge_cuts) && isset($additions['edge_cuts'])) {
        $final_rate += $additions['edge_cuts'];
        $description_parts[] = '+надрезы';
    }
    
    $full_tariff_name = $tariff_name;
    if (!empty($description_parts)) {
        $full_tariff_name .= ' (' . implode(', ', $description_parts) . ')';
    }
    
    $tariff_key = $tariff_id . '|' . $full_tariff_name;
    
    $date = $row['date_of_production'];
    $count = (int)$row['count_of_filters'];
    
    // Для почасовых работ используем часы, для остальных - количество фильтров
    $key = $row['name_of_filter'] . '_' . $row['name_of_order'];
    $hours = $is_hourly ? ($hours_map[$key] ?? 0) : 0;
    $display_count = $is_hourly ? $hours : $count;
    
    if (!isset($brigade_data[$brigade][$tariff_key])) {
        $brigade_data[$brigade][$tariff_key] = [];
    }
    
    if (!isset($brigade_data[$brigade][$tariff_key][$date])) {
        $brigade_data[$brigade][$tariff_key][$date] = [
            'count' => 0, 
            'rate' => $final_rate, 
            'hours' => 0, 
            'filters' => 0,
            'is_hourly' => $is_hourly
        ];
    }
    
    $brigade_data[$brigade][$tariff_key][$date]['count'] += $display_count;
    if ($is_hourly) {
        $brigade_data[$brigade][$tariff_key][$date]['hours'] += $hours;
        $brigade_data[$brigade][$tariff_key][$date]['filters'] += $count;
    }
    $all_tariffs[$tariff_key] = ['name' => $full_tariff_name, 'rate' => $final_rate, 'is_hourly' => $is_hourly];
}

// Получаем дни для отображения в зависимости от периода
$start_day = (int)date('d', strtotime($first_day));
$end_day = (int)date('d', strtotime($last_day));
$all_days = [];

for ($d = $start_day; $d <= $end_day; $d++) {
    $all_days[] = sprintf('%s-%02d', $month, $d);
}

// Функция для отрисовки таблицы бригады
function renderBrigadeTable($brigade_name, $brigade_data, $all_days, $all_tariffs, $month) {
    $month_name_ru = [
        '01' => 'Январь', '02' => 'Февраль', '03' => 'Март', '04' => 'Апрель',
        '05' => 'Май', '06' => 'Июнь', '07' => 'Июль', '08' => 'Август',
        '09' => 'Сентябрь', '10' => 'Октябрь', '11' => 'Ноябрь', '12' => 'Декабрь'
    ];
    
    list($year, $month_num) = explode('-', $month);
    $month_display = $month_name_ru[$month_num] . ' ' . $year;
    
    echo "<div class='panel' style='padding: 10px; display: inline-block; width: auto; max-width: fit-content; margin: 0 auto;'>";
    echo "<h3 class='section-title' style='font-size: 14px; margin: 0 0 8px 0;'>Бригады $brigade_name — $month_display</h3>";
    
    // Начинаем таблицу
    echo "<table class='report-table'>";
    
    // Заголовок с днями
    echo "<thead><tr>";
    echo "<th class='tariff-col'>Тариф (грн/шт)</th>";
    
    $column_index = 0; // Начинаем с 0 для индексации td (первый td это первый день, индекс 0)
    foreach ($all_days as $date) {
        $d = (int)date('d', strtotime($date));
        $timestamp = strtotime($date);
        $day_of_week = date('N', $timestamp);
        $is_weekend = ($day_of_week == 6 || $day_of_week == 7);
        $is_today = ($date == date('Y-m-d'));
        
        $class = 'clickable';
        if ($is_weekend) $class .= ' weekend';
        if ($is_today) $class .= ' today';
        
        echo "<th class='$class' onclick='toggleColumn(event, $column_index)' title='Кликните чтобы скрыть/показать данные столбца'>$d</th>";
        $column_index++;
    }
    
    echo "<th class='total-cell'>Итого</th>";
    echo "</tr></thead>";
    
    // Тело таблицы
    echo "<tbody>";
    
    if (empty($brigade_data)) {
        $colspan = count($all_days) + 2;
        echo "<tr><td colspan='$colspan' style='text-align:center; color: var(--muted);'>Нет данных за этот период</td></tr>";
    } else {
        foreach ($all_tariffs as $tariff_key => $tariff_info) {
            if (!isset($brigade_data[$tariff_key])) {
                continue; // Пропускаем тарифы, которых нет в этой бригаде
            }
            
            $tariff_display = $tariff_info['name'];
            $tariff_rate = $tariff_info['rate'];
            $is_hourly = $tariff_info['is_hourly'];
            
            echo "<tr>";
            echo "<td class='tariff-cell'>$tariff_display<span class='tariff-rate'>" . number_format($tariff_rate, 2, '.', ' ') . " грн</span></td>";
            
            $row_total = 0;
            
            foreach ($all_days as $date) {
                $timestamp = strtotime($date);
                $day_of_week = date('N', $timestamp);
                $is_weekend = ($day_of_week == 6 || $day_of_week == 7);
                $is_today = ($date == date('Y-m-d'));
                
                $count = $brigade_data[$tariff_key][$date]['count'] ?? 0;
                $row_total += $count;
                
                $class = 'count-cell';
                if ($is_weekend) $class .= ' weekend';
                if ($is_today) $class .= ' today';
                
                $display = '';
                if ($count > 0) {
                    if ($is_hourly) {
                        $hours = $brigade_data[$tariff_key][$date]['hours'] ?? 0;
                        $filters = $brigade_data[$tariff_key][$date]['filters'] ?? 0;
                        $display = $hours . ($filters > 0 ? "($filters)" : '');
                    } else {
                        $display = $count;
                    }
                }
                echo "<td class='$class'>$display</td>";
            }
            
            echo "<td class='total-cell'>$row_total</td>";
            echo "</tr>";
        }
        
        // Итоговая строка по дням (в штуках)
        echo "<tr style='background: #e0e7ff; font-weight: normal; font-size: 7px;'>";
        echo "<td style='text-align: left; padding: 3px 4px;'>ИТОГО (шт)</td>";
        
        $grand_total_count = 0;
        foreach ($all_days as $date) {
            $day_total = 0;
            
            foreach ($brigade_data as $tariff_key => $days) {
                $day_total += $days[$date]['count'] ?? 0;
            }
            
            $grand_total_count += $day_total;
            $display = $day_total > 0 ? $day_total : '';
            echo "<td>$display</td>";
        }
        
        echo "<td style='background: #3b82f6; color: white;'>$grand_total_count</td>";
        echo "</tr>";
        
        // Итоговая строка по дням (в гривнах)
        echo "<tr style='background: #dcfce7; font-weight: normal; font-size: 7px;'>";
        echo "<td style='text-align: left; padding: 3px 4px;'>ИТОГО (грн)</td>";
        
        $grand_total_salary = 0;
        foreach ($all_days as $date) {
            $day_salary = 0;
            
            foreach ($brigade_data as $tariff_key => $days) {
                $count = $days[$date]['count'] ?? 0;
                $rate = $days[$date]['rate'] ?? 0;
                $day_salary += $count * $rate;
            }
            
            $grand_total_salary += $day_salary;
            $display = $day_salary > 0 ? number_format($day_salary, 2, '.', ' ') : '';
            echo "<td>$display</td>";
        }
        
        echo "<td style='background: #059669; color: white;'>" . number_format($grand_total_salary, 2, '.', ' ') . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
}

// Отрисовываем таблицы для обеих групп бригад
renderBrigadeTable('1-2', $brigade_data['1-2'], $all_days, $all_tariffs, $month);
renderBrigadeTable('3-4', $brigade_data['3-4'], $all_days, $all_tariffs, $month);

?>

