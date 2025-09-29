<?php
// Тест загрузки данных для выпадающих списков
header('Content-Type: application/json; charset=utf-8');

try {
    // Подключение к базе данных
    $mysqli = new mysqli("localhost", "root", "", "plan_U5");

    if ($mysqli->connect_error) {
        throw new Exception('Ошибка подключения к базе данных: ' . $mysqli->connect_error);
    }

    // Тестируем загрузку фильтров
    echo "=== ТЕСТ ФИЛЬТРОВ ===\n";
    $query = "SELECT DISTINCT filter FROM salon_filter_structure ORDER BY filter LIMIT 5";
    $result = $mysqli->query($query);
    
    if (!$result) {
        echo "Ошибка запроса фильтров: " . $mysqli->error . "\n";
    } else {
        echo "Найдено фильтров: " . $result->num_rows . "\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['filter'] . "\n";
        }
    }

    echo "\n=== ТЕСТ ЗАЯВОК ===\n";
    $query = "SELECT DISTINCT order_number FROM orders ORDER BY order_number LIMIT 5";
    $result = $mysqli->query($query);
    
    if (!$result) {
        echo "Ошибка запроса заявок: " . $mysqli->error . "\n";
    } else {
        echo "Найдено заявок: " . $result->num_rows . "\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['order_number'] . "\n";
        }
    }

    echo "\n=== ТЕСТ API ФИЛЬТРОВ ===\n";
    // Тестируем API напрямую
    $query = "SELECT DISTINCT filter FROM salon_filter_structure ORDER BY filter";
    $result = $mysqli->query($query);
    
    if (!$result) {
        echo "Ошибка API фильтров: " . $mysqli->error . "\n";
    } else {
        $filters = [];
        while ($row = $result->fetch_assoc()) {
            $filters[] = $row['filter'];
        }
        echo json_encode([
            'success' => true,
            'filters' => $filters,
            'count' => count($filters)
        ]) . "\n";
    }

    echo "\n=== ТЕСТ API ЗАЯВОК ===\n";
    // Тестируем API напрямую
    $query = "SELECT DISTINCT order_number FROM orders ORDER BY order_number";
    $result = $mysqli->query($query);
    
    if (!$result) {
        echo "Ошибка API заявок: " . $mysqli->error . "\n";
    } else {
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row['order_number'];
        }
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ]) . "\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
?>
