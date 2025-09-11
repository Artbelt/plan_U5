<?php

require_once('tools/tools.php');
require_once('style/table.txt');

$production_date = reverse_date($_POST['production_date']);
?>
<input type="text"
       id="calendar_input"
       name="selected_date"
       value="<?php echo isset($production_date) ? htmlspecialchars($production_date) : ''; ?>"
       readonly

       style="width: 120px;">
<?php
// === Загружаем доплаты из БД в массив ===
$addition_rows = mysql_execute("SELECT code, amount FROM salary_additions");
$additions = [];
foreach ($addition_rows as $a) {
    $additions[$a['code']] = (float)$a['amount'];
}

// === Загружаем ранее сохраненные часы ===
$hours_raw = mysql_execute("SELECT filter, order_number, hours FROM hourly_work_log");
$hours_map = [];
foreach ($hours_raw as $h) {
    $key = $h['filter'] . '_' . $h['order_number'];
    $hours_map[$key] = $h['hours'];
}

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
            st.tariff_name
        
        FROM manufactured_production mp
        
        /* --- salon_filter_structure: берем по 1 строке на каждый filter --- */
        LEFT JOIN (
            SELECT 
                filter,
                MAX(insertion_count) AS insertion_count,
                MAX(foam_rubber)     AS foam_rubber,
                MAX(form_factor)     AS form_factor,
                MAX(tail)            AS tail,
                MAX(has_edge_cuts)   AS has_edge_cuts,
                MAX(paper_package)   AS paper_package,
                MAX(tariff_id)       AS tariff_id
            FROM salon_filter_structure
            GROUP BY filter
        ) sfs ON sfs.filter = mp.name_of_filter
        
        /* --- paper_package_salon: по 1 строке на каждый p_p_name --- */
        LEFT JOIN (
            SELECT 
                p_p_name,
                MAX(p_p_material) AS p_p_material
            FROM paper_package_salon
            GROUP BY p_p_name
        ) pps ON pps.p_p_name = sfs.paper_package
        
        LEFT JOIN salary_tariffs st ON st.id = sfs.tariff_id
        WHERE mp.date_of_production = '$production_date';
        ";




$result = mysql_execute($sql);

// Группировка по бригадам
$teams = [];
$sums = [];
$wages = [];
$bonus_breakdown = [];

foreach ($result as $row) {
    $team = $row['team'];
    if (!isset($teams[$team])) {
        $teams[$team] = [];
        $sums[$team] = 0;
        $wages[$team] = 0;
        $bonus_breakdown[$team] = [];
    }

    $base_rate = (float)($row['rate_per_unit'] ?? 0);
    $rate = $base_rate;
    $description = [];

    $tail = mb_strtolower(trim($row['tail'] ?? ''));
    $form = mb_strtolower(trim($row['form_factor'] ?? ''));
    $has_edge_cuts = trim($row['has_edge_cuts'] ?? '');
    $count = (int)$row['count_of_filters'];
    $tariff_type = strtolower(trim($row['type'] ?? ''));
    $tariff_name = mb_strtolower(trim($row['tariff_name'] ?? ''));

    $is_hourly = $tariff_name === 'почасовый';
    $apply_additions = $tariff_type !== 'fixed' && !$is_hourly;

    if ($apply_additions && strpos($tail, 'языч') !== false && isset($additions['tongue_glue'])) {
        $rate += $additions['tongue_glue'];
        $description[] = '+язычок';
        if (!isset($bonus_breakdown[$team]['язычок'])) {
            $bonus_breakdown[$team]['язычок'] = ['count' => 0, 'rate' => $additions['tongue_glue']];
        }
        $bonus_breakdown[$team]['язычок']['count'] += $count;
    }

    if ($apply_additions && $form === 'трапеция' && isset($additions['edge_trim_glue'])) {
        $rate += $additions['edge_trim_glue'];
        $description[] = '+трапеция';
        if (!isset($bonus_breakdown[$team]['трапеция'])) {
            $bonus_breakdown[$team]['трапеция'] = ['count' => 0, 'rate' => $additions['edge_trim_glue']];
        }
        $bonus_breakdown[$team]['трапеция']['count'] += $count;
    }

    if ($apply_additions && !empty($has_edge_cuts) && isset($additions['edge_cuts'])) {
        $rate += $additions['edge_cuts'];
        $description[] = '+надрезы';
        if (!isset($bonus_breakdown[$team]['надрезы'])) {
            $bonus_breakdown[$team]['надрезы'] = ['count' => 0, 'rate' => $additions['edge_cuts']];
        }
        $bonus_breakdown[$team]['надрезы']['count'] += $count;
    }

    $key = $row['name_of_filter'] . '_' . $row['name_of_order'];
    $hours = $is_hourly ? ($hours_map[$key] ?? 0) : 0;
    $amount = $is_hourly ? $rate * $hours : $rate * $count;

    $wages[$team] += $amount;
    $sums[$team] += $count;

    $row['final_rate'] = $rate;
    $row['final_amount'] = $amount;
    $row['addition_description'] = implode(' ', $description);
    $row['is_hourly'] = $is_hourly;
    $row['hours'] = $hours;
    $teams[$team][] = $row;
}

ksort($teams);

foreach ($teams as $team => $rows) {
    echo "<h3>Бригада $team</h3>";
    echo "<table style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>
        <tr>
            <td>Фильтр</td>
            <td>Количество</td>
            <td>Заявка</td>
            <td>Вставка</td>
            <td>Поролон</td>
            <td>Форма</td>
            <td>Хвосты</td>
            <td>Надрезы</td>
            <td>Доплаты</td>
            <td>Материал</td>
            <td>Бригада</td>
            <td>Название тарифа</td>
            <td>Тариф (грн)</td>
            <td>Сумма (грн)</td>
            <td>Часы</td>
        </tr>";

    foreach ($rows as $variant) {
        $rate = number_format($variant['final_rate'], 2, '.', ' ');
        $amount = number_format($variant['final_amount'], 2, '.', ' ');
        $edge_cuts = !empty($variant['has_edge_cuts']) ? 'Да' : '';
        $adds = $variant['addition_description'] ?? '';
        $input_name = "hours[{$variant['name_of_filter']}_{$variant['name_of_order']}]";
        $input_hours = $variant['is_hourly'] ? "<input type='number' step='0.1' min='0' name='{$input_name}' value='{$variant['hours']}' style='width:60px'>" : '';

        echo "<tr>
            <td>{$variant['name_of_filter']}</td>
            <td>{$variant['count_of_filters']}</td>
            <td>{$variant['name_of_order']}</td>
            <td>{$variant['insertion_count']}</td>
            <td>{$variant['foam_rubber']}</td>
            <td>{$variant['form_factor']}</td>
            <td>{$variant['tail']}</td>
            <td>{$edge_cuts}</td>
            <td>{$adds}</td>
            <td>{$variant['p_p_material']}</td>
            <td>{$variant['team']}</td>
            <td>{$variant['tariff_name']}</td>
            <td>{$rate}</td>
            <td>{$amount}</td>
            <td>{$input_hours}</td>
        </tr>";
    }
    echo "</table>";
    echo "<p>Сумма выпущенной продукции бригады $team: {$sums[$team]} штук</p>";

    $bonus_total = 0;
    if (!empty($bonus_breakdown[$team])) {
        echo "<p><b>Применённые доплаты:</b></p><ul>";
        foreach ($bonus_breakdown[$team] as $type => $info) {
            $count = $info['count'];
            $rate = number_format($info['rate'], 2, '.', ' ');
            $sum = $info['count'] * $info['rate'];
            $sum_display = number_format($sum, 2, '.', ' ');
            $bonus_total += $sum;
            echo "<li>$type — $count шт. × $rate = $sum_display грн</li>";
        }
        echo "</ul>";
    }

    $total_salary = $wages[$team];
    echo "<p><b>Начисленная зарплата бригады $team: " . number_format($total_salary, 2, '.', ' ') . " грн</b></p><br>";
}

echo "<button onclick='saveHours()' style='margin-top: 10px;'>Сохранить часы</button>";

?>

<script>
    function saveHours() {
        const inputs = document.querySelectorAll("input[name^='hours']");
        const formData = new FormData();

        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                formData.append(input.name, input.value);
            }
        });

        fetch('save_hours.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.text())
            .then(result => {
                alert("✅ Часы успешно сохранены!");
            })
            .catch(error => {
                console.error("Ошибка:", error);
                alert("❌ Не удалось сохранить часы.");
            });
    }
</script>
