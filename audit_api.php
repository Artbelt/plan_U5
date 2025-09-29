<?php
// API для просмотра логов аудита
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_audit_logs':
        getAuditLogs();
        break;
    default:
        echo json_encode(['error' => 'Неизвестное действие']);
        break;
}

function getAuditLogs() {
    global $mysqli;
    
    $table_name = $_POST['table_name'] ?? '';
    $operation = $_POST['operation'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    $limit = intval($_POST['limit'] ?? 1000);
    
    
    try {
        // Строим запрос с фильтрами
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        if (!empty($table_name)) {
            $where_conditions[] = "table_name = ?";
            $params[] = $table_name;
            $param_types .= 's';
        }
        
        if (!empty($operation)) {
            $where_conditions[] = "operation = ?";
            $params[] = $operation;
            $param_types .= 's';
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = "DATE(created_at) >= ?";
            $params[] = $date_from;
            $param_types .= 's';
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "DATE(created_at) <= ?";
            $params[] = $date_to;
            $param_types .= 's';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Запрос для получения логов
        $query = "SELECT * FROM audit_log 
                 {$where_clause}
                 ORDER BY created_at DESC 
                 LIMIT ?";
        
        $params[] = $limit;
        $param_types .= 'i';
        
        $stmt = $mysqli->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            // Парсим JSON данные
            $row['old_values'] = $row['old_values'] ? json_decode($row['old_values'], true) : null;
            $row['new_values'] = $row['new_values'] ? json_decode($row['new_values'], true) : null;
            $row['changed_fields'] = $row['changed_fields'] ? explode(',', $row['changed_fields']) : null;
            
            $logs[] = $row;
        }
        
        $stmt->close();
        
        // Получаем статистику
        $stats_query = "SELECT 
                           COUNT(*) as total,
                           SUM(CASE WHEN operation = 'INSERT' THEN 1 ELSE 0 END) as `INSERT`,
                           SUM(CASE WHEN operation = 'UPDATE' THEN 1 ELSE 0 END) as `UPDATE`,
                           SUM(CASE WHEN operation = 'DELETE' THEN 1 ELSE 0 END) as `DELETE`
                        FROM audit_log 
                        {$where_clause}";
        
        $stats_stmt = $mysqli->prepare($stats_query);
        if (!empty($params)) {
            // Убираем последний параметр (limit) для статистики
            $stats_params = array_slice($params, 0, -1);
            $stats_param_types = substr($param_types, 0, -1);
            if (!empty($stats_params) && !empty($stats_param_types)) {
                $stats_stmt->bind_param($stats_param_types, ...$stats_params);
            }
        }
        
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats = $stats_result->fetch_assoc();
        $stats_stmt->close();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'stats' => $stats,
            'count' => count($logs)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

$mysqli->close();
?>
