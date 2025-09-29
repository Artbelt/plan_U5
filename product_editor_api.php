<?php
// Включаем отображение ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Устанавливаем заголовок для JSON
header('Content-Type: application/json; charset=utf-8');

session_start();

try {
    // Подключение к базе данных
    $mysqli = new mysqli("localhost", "root", "", "plan_U5");

    if ($mysqli->connect_error) {
        throw new Exception('Ошибка подключения к базе данных: ' . $mysqli->connect_error);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Подключаем аудит логгер
require_once 'audit_logger.php';
$auditLogger = new AuditLogger($mysqli);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'load_data':
        loadProductData();
        break;
    case 'load_data_by_date':
        loadDataByDate();
        break;
    case 'load_filters':
        loadFilters();
        break;
    case 'load_orders':
        loadOrders();
        break;
    case 'load_orders_for_dropdown':
        loadOrdersForDropdown();
        break;
    case 'update_quantity':
        updateQuantity();
        break;
    case 'move_to_order':
        moveToOrder();
        break;
    case 'add_position':
        addPosition();
        break;
    case 'remove_position':
        removePosition();
        break;
    default:
        echo json_encode(['error' => 'Неизвестное действие']);
        break;
}

function loadProductData() {
    global $mysqli;
    
    try {
        // Простой запрос для проверки таблицы manufactured_production
        $query = "SELECT * FROM manufactured_production LIMIT 10";
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception('Ошибка запроса к таблице manufactured_production: ' . $mysqli->error);
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Создаем виртуальный ID
            $virtual_id = $row['name_of_order'] . '_' . $row['name_of_filter'] . '_' . $row['date_of_production'];
            

            

            $data[] = [
                'virtual_id' => $virtual_id,
                'filter_name' => $row['name_of_filter'],
                'quantity' => $row['count_of_filters'],
                'production_date' => $row['date_of_production'],
                'order_number' => $row['name_of_order'],
                'brigade' => $row['team'],

            ];
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $data,
            'message' => 'Данные успешно загружены'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function loadDataByDate() {
    global $mysqli;
    
    try {
        $selectedDate = $_POST['date'] ?? '';
        
        if (empty($selectedDate)) {
            throw new Exception('Дата не указана');
        }
        
        // Загружаем данные за выбранную дату
        $query = "SELECT * FROM manufactured_production WHERE date_of_production = ? ORDER BY team, name_of_filter";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("s", $selectedDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception('Ошибка запроса к таблице manufactured_production: ' . $mysqli->error);
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Создаем виртуальный ID
            $virtual_id = $row['name_of_order'] . '_' . $row['name_of_filter'] . '_' . $row['date_of_production'];
            
            $data[] = [
                'virtual_id' => $virtual_id,
                'filter_name' => $row['name_of_filter'],
                'quantity' => $row['count_of_filters'],
                'production_date' => $row['date_of_production'],
                'order_number' => $row['name_of_order'],
                'brigade' => $row['team']
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $data,
            'date' => $selectedDate,
            'message' => 'Данные за дату успешно загружены'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function loadFilters() {
    global $mysqli;
    
    try {
        $query = "SELECT DISTINCT filter FROM salon_filter_structure ORDER BY filter";
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception('Ошибка загрузки фильтров: ' . $mysqli->error);
        }
        
        $filters = [];
        while ($row = $result->fetch_assoc()) {
            $filters[] = $row['filter'];
        }
        
        echo json_encode([
            'success' => true,
            'filters' => $filters,
            'count' => count($filters)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function loadOrders() {
    global $mysqli;
    
    try {
        $query = "SELECT DISTINCT order_number FROM orders ORDER BY order_number DESC";
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception('Ошибка загрузки заявок: ' . $mysqli->error);
        }
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row['order_number'];
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function loadOrdersForDropdown() {
    global $mysqli;
    
    try {
        // Загружаем заявки, отсортированные по номеру заявки (новые сверху)
        $query = "SELECT DISTINCT order_number FROM orders ORDER BY order_number DESC";
        $result = $mysqli->query($query);
        
        if (!$result) {
            throw new Exception('Ошибка загрузки заявок: ' . $mysqli->error);
        }
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row['order_number'];
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateQuantity() {
    global $mysqli, $auditLogger;
    
    $virtual_id = $_POST['id'];
    $quantity = intval($_POST['quantity']);
    
    if (empty($virtual_id) || $quantity < 0) {
        echo json_encode(['error' => 'Некорректные данные']);
        return;
    }
    
    // Парсим virtual_id для получения компонентов
    $parts = explode('_', $virtual_id);
    if (count($parts) < 3) {
        echo json_encode(['error' => 'Некорректный ID записи']);
        return;
    }
    
    $order_name = $parts[0];
    $filter_name = $parts[1];
    $production_date = $parts[2];
    
    try {
        // Получаем старые значения для аудита
        $old_query = "SELECT * FROM manufactured_production 
                     WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $old_stmt = $mysqli->prepare($old_query);
        $old_stmt->bind_param('sss', $order_name, $filter_name, $production_date);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        $old_values = $old_result->fetch_assoc();
        $old_stmt->close();
        
        // Обновляем количество в таблице manufactured_production
        $query = "UPDATE manufactured_production SET count_of_filters = ? WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("isss", $quantity, $order_name, $filter_name, $production_date);
        
        if ($stmt->execute()) {
            // Записываем в аудит
            $new_values = $old_values;
            $new_values['count_of_filters'] = $quantity;
            
            $auditLogger->logUpdate(
                'manufactured_production',
                $virtual_id,
                $old_values,
                $new_values,
                ['count_of_filters'],
                "Обновление количества фильтров: {$old_values['count_of_filters']} → {$quantity}"
            );
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Ошибка обновления: ' . $stmt->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function moveToOrder() {
    global $mysqli, $auditLogger;
    
    $virtual_id = $_POST['id'];
    $new_order_name = $_POST['new_order_id'];
    
    if (empty($virtual_id) || empty($new_order_name)) {
        echo json_encode(['error' => 'Некорректные данные']);
        return;
    }
    
    // Парсим virtual_id для получения компонентов
    $parts = explode('_', $virtual_id);
    if (count($parts) < 3) {
        echo json_encode(['error' => 'Некорректный ID записи']);
        return;
    }
    
    $old_order_name = $parts[0];
    $filter_name = $parts[1];
    $production_date = $parts[2];
    
    try {
        // Получаем старые значения для аудита
        $old_query = "SELECT * FROM manufactured_production 
                     WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $old_stmt = $mysqli->prepare($old_query);
        $old_stmt->bind_param('sss', $old_order_name, $filter_name, $production_date);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        $old_values = $old_result->fetch_assoc();
        $old_stmt->close();
        
        // Обновляем название заявки в таблице manufactured_production
        $query = "UPDATE manufactured_production SET name_of_order = ? WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ssss", $new_order_name, $old_order_name, $filter_name, $production_date);
        
        if ($stmt->execute()) {
            // Записываем в аудит
            $new_values = $old_values;
            $new_values['name_of_order'] = $new_order_name;
            
            $auditLogger->logUpdate(
                'manufactured_production',
                $virtual_id,
                $old_values,
                $new_values,
                ['name_of_order'],
                "Перенос в заявку: {$old_order_name} → {$new_order_name}"
            );
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Ошибка обновления: ' . $stmt->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function addPosition() {
    global $mysqli, $auditLogger;
    
    $filter_name = $_POST['filter_name'] ?? '';
    $quantity = intval($_POST['quantity']);
    $order_name = $_POST['order_name'] ?? '';
    $team = intval($_POST['team']);
    $production_date = $_POST['production_date'] ?? date('Y-m-d');
    
    if (empty($filter_name) || $quantity <= 0 || empty($order_name) || $team <= 0) {
        echo json_encode(['error' => 'Некорректные данные']);
        return;
    }
    
    // Проверяем, не существует ли уже такая запись
    $check_query = "SELECT COUNT(*) as count FROM manufactured_production WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
    $check_stmt = $mysqli->prepare($check_query);
    $check_stmt->bind_param("sss", $order_name, $filter_name, $production_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $check_row = $check_result->fetch_assoc();
    
    if ($check_row['count'] > 0) {
        echo json_encode(['error' => 'Запись с такими параметрами уже существует']);
        return;
    }
    
    // Добавляем новую позицию в таблицу manufactured_production
    $query = "INSERT INTO manufactured_production (name_of_filter, count_of_filters, name_of_order, team, date_of_production) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sisis", $filter_name, $quantity, $order_name, $team, $production_date);
    
    if ($stmt->execute()) {
        // Записываем в аудит
        $virtual_id = $order_name . '_' . $filter_name . '_' . $production_date;
        $new_values = [
            'name_of_filter' => $filter_name,
            'count_of_filters' => $quantity,
            'name_of_order' => $order_name,
            'team' => $team,
            'date_of_production' => $production_date
        ];
        
        $auditLogger->logInsert(
            'manufactured_production',
            $virtual_id,
            $new_values,
            "Добавлена новая позиция: фильтр {$filter_name}, количество {$quantity}, заявка {$order_name}, бригада {$team}"
        );
        
        echo json_encode(['success' => true, 'new_id' => $mysqli->insert_id]);
    } else {
        echo json_encode(['error' => 'Ошибка добавления: ' . $stmt->error]);
    }
}

function removePosition() {
    global $mysqli, $auditLogger;
    
    $virtual_id = $_POST['id'];
    
    if (empty($virtual_id)) {
        echo json_encode(['error' => 'Некорректные данные']);
        return;
    }
    
    // Парсим virtual_id для получения компонентов
    $parts = explode('_', $virtual_id);
    if (count($parts) < 3) {
        echo json_encode(['error' => 'Некорректный ID записи']);
        return;
    }
    
    $order_name = $parts[0];
    $filter_name = $parts[1];
    $production_date = $parts[2];
    
    try {
        // Получаем старые значения для аудита
        $old_query = "SELECT * FROM manufactured_production 
                     WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $old_stmt = $mysqli->prepare($old_query);
        $old_stmt->bind_param('sss', $order_name, $filter_name, $production_date);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        $old_values = $old_result->fetch_assoc();
        $old_stmt->close();
        
        // Удаляем запись из таблицы manufactured_production
        $query = "DELETE FROM manufactured_production WHERE name_of_order = ? AND name_of_filter = ? AND date_of_production = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sss", $order_name, $filter_name, $production_date);
        
        if ($stmt->execute()) {
            // Записываем в аудит
            $auditLogger->logDelete(
                'manufactured_production',
                $virtual_id,
                $old_values,
                "Удалена позиция: фильтр {$filter_name}, количество {$old_values['count_of_filters']}, заявка {$order_name}, бригада {$old_values['team']}"
            );
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Ошибка удаления: ' . $stmt->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

$mysqli->close();
?>
