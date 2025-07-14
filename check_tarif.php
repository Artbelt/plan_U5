<?php

require_once('tools/tools.php');

$sql = "SELECT 
            sfs.filter,
            sfs.insertion_count,
            sfs.foam_rubber,
            st.tariff_name
        FROM 
            salon_filter_structure sfs
        LEFT JOIN 
            salary_tariffs st ON sfs.tariff_id = st.id";

$rows = mysql_execute($sql);

echo "<h2>Проверка корректности тарифов</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>
<tr>
    <th>Фильтр</th>
    <th>Вставки</th>
    <th>Поролон</th>
    <th>Назначенный тариф</th>
    <th>Ожидаемый тариф</th>
</tr>";

foreach ($rows as $row) {
    $filter = $row['filter'];
    $insertion = (int)$row['insertion_count'];
    $foam = mb_strtolower(trim($row['foam_rubber'] ?? ''));
    $actual = trim($row['tariff_name'] ?? '');

    if ($foam !== '') {
        $expected = 'С поролоном';
    } elseif ($insertion > 0) {
        $expected = 'Со вставками';
    } else {
        $expected = 'Простой';
    }

    if ($actual !== $expected) {
        echo "<tr>
                <td>{$filter}</td>
                <td>{$row['insertion_count']}</td>
                <td>{$row['foam_rubber']}</td>
                <td>{$actual}</td>
                <td><b style='color:red;'>$expected</b></td>
              </tr>";
    }
}

echo "</table>";
?>
