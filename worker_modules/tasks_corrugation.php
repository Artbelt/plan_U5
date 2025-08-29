<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4", "root", "");
$date = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT id, order_number, plan_date, filter_label, `count`, fact_count, status
    FROM corrugation_plan
    WHERE plan_date = ?
    ORDER BY order_number, id
");
$stmt->execute([$date]);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Задания гофромашины</title>
    <style>
        body{font-family:sans-serif;background:#f0f0f0;padding:10px}
        h2{text-align:center;margin:6px 0 12px}
        form{text-align:center;margin-bottom:10px}
        .section{max-width:800px;margin:0 auto;background:#fff;padding:10px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08)}

        table{border-collapse:collapse;width:100%;font-size:14px}
        thead th{background:#f5f5f5}
        th,td{border:1px solid #ddd;padding:6px 8px;text-align:center}
        tbody tr:nth-child(even){background:#fafafa}

        /* выполненная строка */
        .is-done td{
            text-decoration: line-through;
            color:#6b7280;            /* серый */
            background:#eaf7ea !important; /* лёгкий зелёный фон */
        }

        /* кнопки / инпуты */
        button{padding:6px 10px;font-size:14px;cursor:pointer}
        input[type="number"]{width:80px;padding:4px 6px;text-align:center}
        input[type="date"]{padding:4px 6px}

        /* мобильная версия: компактнее, но таблица остаётся таблицей */
        @media (max-width:600px){
            .section{padding:8px}
            table{font-size:13px}
            th,td{padding:4px}
            input[type="number"]{width:70px}
            button{width:100%;padding:10px 0;font-size:15px}
        }
    </style>
    <script>
        function saveFact(id){
            const inp = document.getElementById('fact-'+id);
            const val = (inp.value || '').trim();
            if(val === '' || isNaN(val) || Number(val) < 0){
                alert('Введите корректное число'); return;
            }
            fetch('save_corr_fact.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'id='+encodeURIComponent(id)+'&fact='+encodeURIComponent(val)
            })
                .then(r=>r.json())
                .then(d=>{
                    if(!d.success){ alert('Ошибка: '+(d.message||'не удалось сохранить')); return; }
                    // Ничего не меняем визуально — факт может быть частичным.
                })
                .catch(e=>alert('Ошибка запроса: '+e));
        }

        function saveStatus(id, checked){
            fetch('save_corr_fact.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'id='+encodeURIComponent(id)+'&status='+(checked?1:0)
            })
                .then(r=>r.json())
                .then(d=>{
                    if(!d.success){ alert('Ошибка: '+(d.message||'не удалось сохранить статус'));
                        // откат чекбокса при ошибке:
                        const cb = document.getElementById('status-'+id);
                        if(cb) cb.checked = !checked;
                        return;
                    }
                    // Переключаем оформление строки
                    const row = document.getElementById('row-'+id);
                    if(row){
                        if(checked) row.classList.add('is-done');
                        else row.classList.remove('is-done');
                    }
                })
                .catch(e=>{
                    alert('Ошибка запроса: '+e);
                    const cb = document.getElementById('status-'+id);
                    if(cb) cb.checked = !checked;
                });
        }
    </script>
</head>
<body>

<h2>Задания гофромашины на <?= htmlspecialchars($date) ?></h2>
<form method="get">
    Дата:
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
    <button type="submit">Показать</button>
</form>

<div class="section">
    <?php if ($plans): ?>
        <table>
            <thead>
            <tr>
                <th>Заявка</th>
                <th>Фильтр</th>
                <th>План, шт</th>
                <th>Факт, шт</th>
                <th>Готово</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($plans as $p): ?>
                <tr id="row-<?= (int)$p['id'] ?>" class="<?= $p['status'] ? 'is-done' : '' ?>">
                    <td><?= htmlspecialchars($p['order_number']) ?></td>
                    <td><?= htmlspecialchars($p['filter_label']) ?></td>
                    <td><?= (int)$p['count'] ?></td>
                    <td>
                        <input type="number" id="fact-<?= (int)$p['id'] ?>" value="<?= (int)$p['fact_count'] ?>" min="0">
                    </td>
                    <td>
                        <input type="checkbox" id="status-<?= (int)$p['id'] ?>" <?= $p['status'] ? 'checked' : '' ?>
                               onchange="saveStatus(<?= (int)$p['id'] ?>, this.checked)">
                    </td>
                    <td>
                        <button type="button" onclick="saveFact(<?= (int)$p['id'] ?>)">Сохранить</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align:center;margin:10px 0;">Заданий нет</p>
    <?php endif; ?>
</div>

</body>
</html>
