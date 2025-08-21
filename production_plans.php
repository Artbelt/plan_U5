<?php
// Подключение к базе данных
$dsn = 'mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4';
$user = 'root'; // укажите своего пользователя
$pass = '';     // укажите пароль

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Получаем список планов (группируем по номеру заявки, если нужно)
$stmt = $pdo->query("
    SELECT DISTINCT order_number
    FROM build_plan
    ORDER BY plan_date DESC
");
$plans = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Планы производства</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f4f4f4;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<div class="button-container">
    <?php if ($plans): ?>
        <?php foreach ($plans as $plan): ?>
            <button class="btn" onclick="window.open('view_production_plan.php?order=<?= urlencode($plan) ?>', '_blank')">
                <?= htmlspecialchars($plan) ?>
            </button>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Нет доступных планов.</p>
    <?php endif; ?>
</div>
</body>
</html>
