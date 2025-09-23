<?php
// Подключение к базе данных
$dsn = 'mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

/**
 * Берём список заявок из build_plan и подтягиваем их статус из orders.hide
 * Предполагаем:
 *   hide = 1  -> заявка выполнена/закрыта (делаем кнопку неактивной/«серой»)
 *   hide = 0  -> активная (зелёная и кликабельная)
 */
$sql = "
    SELECT
        bp.order_number,
        MAX(bp.plan_date)               AS last_plan_date,
        COALESCE(MAX(CASE WHEN o.hide=1 THEN 1 ELSE 0 END), 0) AS is_done
    FROM build_plan bp
    LEFT JOIN orders o
        ON o.order_number = bp.order_number
    GROUP BY bp.order_number
    ORDER BY last_plan_date DESC
";
$plans = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Планы производства</title>
    <style>
        :root{
            --bg:#f6f7f9;
            --panel:#ffffff;
            --ink:#1f2937;
            --muted:#6b7280;
            --accent:#22c55e;           /* зелёная */
            --accent-hover:#16a34a;
            --done:#9ca3af;             /* серая для «выполнено» */
            --radius:12px;
            --shadow:0 2px 12px rgba(2,8,20,.06);
        }
        *{box-sizing:border-box}
        html,body{
            height:100%;
            margin:0;
            font-family:Arial, sans-serif;
            background:var(--bg);
            color:var(--ink);
        }
        .wrap{
            min-height:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .button-container{
            display:flex;
            flex-direction:column;
            gap:14px;
            background:var(--panel);
            padding:24px;
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            max-width:520px;
            width:100%;
        }
        .btn{
            appearance:none;
            border:0;
            border-radius:10px;
            padding:12px 20px;
            font-size:16px;
            line-height:1.2;
            cursor:pointer;
            background:var(--accent);
            color:#fff;
            transition:transform .08s ease, box-shadow .2s ease, background-color .2s ease;
            box-shadow:0 4px 10px rgba(34,197,94,.25);
            text-align:left;
        }
        .btn:hover{ background:var(--accent-hover) }
        .btn:active{ transform:translateY(1px) }

        /* «Выполнено» — неактивная/серая кнопка */
        .btn.done{
            background:var(--done);
            color:#fff;
            box-shadow:none;
            cursor:not-allowed;
        }
        .btn.done:hover{ background:var(--done) }
        .btn.done:active{ transform:none }

        /* маленькая подпись под номером заявки */
        .sub{
            display:block;
            font-size:12px;
            color:var(--muted);
            margin-top:4px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="button-container">
        <?php if (!empty($plans)): ?>
            <?php foreach ($plans as $row): ?>
                <?php
                $order = $row['order_number'];
                $isDone = (int)$row['is_done'] === 1;
                $title  = $isDone ? 'Заявка выполнена' : 'Открыть план по заявке';
                ?>
                <?php if ($isDone): ?>
                    <!-- Выполненная: серый стиль и заблокирована -->
                    <button class="btn done" title="<?= htmlspecialchars($title) ?>" disabled>
                        <?= htmlspecialchars($order) ?>
                        <span class="sub">статус: выполнено</span>
                    </button>
                <?php else: ?>
                    <!-- Активная: кликабельная -->
                    <button class="btn" title="<?= htmlspecialchars($title) ?>"
                            onclick="window.open('view_production_plan.php?order=<?= urlencode($order) ?>','_blank')">
                        <?= htmlspecialchars($order) ?>
                        <span class="sub">статус: активная</span>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Нет доступных планов.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
