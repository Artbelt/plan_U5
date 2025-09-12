<?php
require_once('tools/tools.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>Добавление нового панельного фильтра в БД</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        :root{
            --bg:#f9fafb;
            --card:#ffffff;
            --muted:#5f6368;
            --text:#1f2937;
            --accent:#2563eb;
            --accent-2:#059669;
            --border:#e5e7eb;
            --danger:#dc2626;
            --radius:12px;
            --shadow:0 4px 12px rgba(0,0,0,.08);
        }
        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0; background:var(--bg);
            color:var(--text); font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;
        }
        .container{max-width:1100px; margin:24px auto 64px; padding:0 16px;}
        header.top{
            display:flex; align-items:center; justify-content:space-between;
            padding:18px 20px; background:#fff; border:1px solid var(--border);
            border-radius:var(--radius); box-shadow:var(--shadow);
        }
        .title{font-size:18px; font-weight:700; letter-spacing:.2px}
        .badge{
            display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border:1px solid var(--border);
            border-radius:999px; color:var(--muted); background:#f3f4f6;
        }
        .grid{display:grid; gap:16px}
        .grid.cols-2{grid-template-columns:1fr 1fr}
        .card{
            background:var(--card); border:1px solid var(--border); border-radius:var(--radius);
            box-shadow:var(--shadow); padding:18px;
        }
        .card h3{margin:0 0 12px; font-size:16px; font-weight:700}
        label{display:block; color:var(--muted); margin-bottom:6px; font-size:13px}
        .row-2{display:grid; gap:12px; grid-template-columns:1fr 1fr}
        .row-4{display:grid; gap:12px; grid-template-columns:repeat(4,1fr)}
        input[type="text"], select{
            width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border);
            background:#fff; color:var(--text); outline:none;
            transition:border-color .15s, box-shadow .15s;
        }
        input[type="text"]:focus, select:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 2px rgba(37,99,235,.15);
        }
        .help{color:var(--muted); font-size:12px; margin-top:4px}
        .checks{display:flex; gap:14px; flex-wrap:wrap; margin-top:10px}
        .check{
            display:flex; align-items:center; gap:6px; padding:6px 8px; border:1px solid var(--border);
            border-radius:8px; background:#f9fafb;
        }
        .actions{
            position:sticky; bottom:0; margin-top:20px; padding:12px 16px; background:#fff;
            border:1px solid var(--border); border-radius:var(--radius);
            display:flex; justify-content:space-between; align-items:center; gap:12px;
            box-shadow:0 -2px 10px rgba(0,0,0,.05);
        }
        .btn{
            border:1px solid transparent; background:var(--accent);
            color:white; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer;
            transition:background .15s;
        }
        .btn:hover{background:#1e4ed8}
        .btn.secondary{background:#f3f4f6; color:var(--text); border-color:var(--border)}
        .btn.secondary:hover{background:#e5e7eb}
        .proto-form{display:flex; gap:12px; align-items:end; flex-wrap:wrap}
        .proto-form select{min-width:280px}
        .muted{color:var(--muted)}
        @media(max-width:900px){
            .row-2,.row-4{grid-template-columns:1fr}
            .grid.cols-2{grid-template-columns:1fr}
            .actions{flex-direction:column; align-items:stretch}
        }
    </style>
</head>
<body>

<div class="container">

    <header class="top">
        <div class="title">Добавление нового панельного фильтра</div>
        <div class="badge">
            <span class="muted">Цех:</span>
            <strong><?php echo isset($_POST['workshop']) ? htmlspecialchars($_POST['workshop']) : '—'; ?></strong>
        </div>
    </header>

    <?php

    // Текущее имя нового фильтра (чтобы не терялось при выборе прототипа)
    $filter_name = isset($_POST['filter_name']) ? $_POST['filter_name'] : '';

    // ===== Загрузка справочника фильтров для выпадающего списка прототипов =====
    try {
        // при необходимости поменяй таблицу
        $all_filters = mysql_execute("SELECT filter FROM salon_filter_structure ORDER BY filter");
    } catch (Throwable $e) { $all_filters = []; }

    // Текущий выбранный прототип
    $analog_filter = (isset($_POST['analog_filter']) && $_POST['analog_filter'] !== '') ? $_POST['analog_filter'] : '';

    // Получаем данные прототипа (если выбран)
    if ($analog_filter !== '') {
        echo "<p class='muted' style='margin:8px 2px 18px'>Загружен прототип: <b>".htmlspecialchars($analog_filter)."</b></p>";
        $analog_data = get_salon_filter_data($analog_filter);
    } else {
        echo "<p class='muted' style='margin:8px 2px 18px'>Прототип не выбран</p>";
        $analog_data = array();
        // дефолтные ключи, перекрываются значениями из get_salon_filter_data(...)
        $analog_data['paper_package_width'] ='';
        $analog_data['paper_package_height'] ='';
        $analog_data['paper_package_pleats_count'] ='';
        $analog_data['paper_package_remark'] ='';
        $analog_data['paper_package_supplier'] ='';
        $analog_data['insertion_count']='';
        $analog_data['paper_package_material']='';
        $analog_data['box'] ='';
        $analog_data['g_box'] ='';
        $analog_data['comment'] ='';
        $analog_data['foam_rubber']='';
        $analog_data['form_factor']='';
        $analog_data['tail']='';
        $analog_data['side_type']='';
    }
    // Чекбоксы
    if (!isset($analog_data['foam_rubber_checkbox_state'])) {
        $analog_data['foam_rubber_checkbox_state'] = (!empty($analog_data['foam_rubber'])) ? 'checked' : '';
    }
    if (!isset($analog_data['tail_checkbox_state'])) {
        $analog_data['tail_checkbox_state'] = (!empty($analog_data['tail'])) ? 'checked' : '';
    }
    if (!isset($analog_data['form_factor_checkbox_state'])) {
        $analog_data['form_factor_checkbox_state'] = (!empty($analog_data['form_factor'])) ? 'checked' : '';
    }
    ?>

    <!-- Прототип -->
    <section class="card">
        <h3>Прототип</h3>
        <form action="" method="post" class="proto-form">
            <div style="flex:1; min-width:280px">
                <label>Выберите существующий фильтр</label>
                <select name="analog_filter" onchange="this.form.submit()">
                    <option value="">— без прототипа —</option>
                    <?php foreach ($all_filters as $row):
                        $f = $row['filter'];
                        $sel = ($f === $analog_filter) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($f) ?>" <?= $sel ?>><?= htmlspecialchars($f) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="help">При выборе прототипа параметры ниже заполнятся автоматически — вы сможете их подправить.</div>
            </div>
            <!-- сохраняем уже введённое имя нового фильтра при смене прототипа -->
            <input type="hidden" name="filter_name" value="<?= htmlspecialchars($filter_name) ?>">
        </form>
    </section>

    <div class="grid cols-2" style="margin-top:16px">
        <!-- Общая информация -->
        <section class="card">
            <h3>Общая информация</h3>
            <div class="row-2">
                <div>
                    <label><b>Наименование фильтра</b></label>
                    <input type="text" name="filter_name" form="saveForm" value="<?= htmlspecialchars($filter_name) ?>" placeholder="Например, AF1234">
                </div>
                <div>
                    <label>Категория</label>
                    <select name="category" form="saveForm">
                        <option>Салонный</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Гофропакет -->
        <section class="card">
            <h3>Гофропакет</h3>
            <div class="row-4">
                <div>
                    <label>Ширина шторы</label>
                    <input type="text" id="width_input" name="p_p_width" form="saveForm" value="<?= htmlspecialchars($analog_data['paper_package_width']) ?>" placeholder="мм">
                </div>
                <div>
                    <label>Высота шторы</label>
                    <input type="text" id="height_input" name="p_p_height" form="saveForm" value="<?= htmlspecialchars($analog_data['paper_package_height']) ?>" placeholder="мм">
                </div>
                <div>
                    <label>Кол-во ребер</label>
                    <input type="text" name="p_p_pleats_count" form="saveForm" value="<?= htmlspecialchars($analog_data['paper_package_pleats_count']) ?>">
                </div>
                <div>
                    <label>Поставщик</label>
                    <select name="p_p_supplier" form="saveForm">
                        <option></option>
                        <option <?= ($analog_data['paper_package_supplier'] ?? '') === 'У5' ? 'selected' : '' ?>>У5</option>
                    </select>
                </div>
            </div>
            <div class="row-2" style="margin-top:12px">
                <div>
                    <label>Материал</label>
                    <select name="p_p_material" form="saveForm">
                        <option></option>
                        <option <?= ($analog_data['paper_package_material'] ?? '') === 'Carbon' ? 'selected' : '' ?>>Carbon</option>
                    </select>
                </div>
                <div>
                    <label>Комментарий</label>
                    <input type="text" name="p_p_remark" form="saveForm" value="<?= htmlspecialchars($analog_data['paper_package_remark'] ?? '') ?>" placeholder="Примечание по гофропакету">
                </div>
            </div>
        </section>

        <!-- Вставка -->
        <section class="card">
            <h3>Вставка</h3>
            <div class="row-2">
                <div>
                    <label>Количество в фильтре</label>
                    <input type="text" name="insertions_count" form="saveForm" value="<?= htmlspecialchars($analog_data['insertion_count']) ?>">
                </div>
                <div>
                    <label>Поставщик</label>
                    <select name="insertions_supplier" form="saveForm">
                        <option></option>
                        <option <?= !empty($analog_data['insertion_count']) ? 'selected' : '' ?>>УУ</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Лента / опции -->
        <section class="card">
            <h3>Лента и опции</h3>
            <div class="row-2">
                <div>
                    <label>Высота боковой ленты</label>
                    <input type="text" id="line_width_input" name="side_type" form="saveForm" value="<?= htmlspecialchars($analog_data['side_type']) ?>" placeholder="мм">
                </div>
            </div>
            <div class="checks">
                <label class="check"><input type="checkbox" name="foam_rubber" form="saveForm" <?= $analog_data['foam_rubber_checkbox_state'] ?>> Поролон</label>
                <label class="check"><input type="checkbox" name="tail" form="saveForm" <?= $analog_data['tail_checkbox_state'] ?>> Язычок</label>
                <label class="check"><input type="checkbox" name="form_factor" form="saveForm" <?= $analog_data['form_factor_checkbox_state'] ?>> Трапеция</label>
            </div>
        </section>

        <!-- Упаковка: индивидуальная -->
        <section class="card">
            <h3>Индивидуальная упаковка</h3>
            <div class="row-2">
                <div>
                    <label>Коробка №</label>
                    <select name="box" form="saveForm"><?php select_boxes($analog_data['box']); ?></select>
                </div>
            </div>
        </section>

        <!-- Упаковка: групповая -->
        <section class="card">
            <h3>Групповая упаковка</h3>
            <div class="row-2">
                <div>
                    <label>Ящик №</label>
                    <select name="g_box" form="saveForm"><?php select_g_boxes($analog_data['g_box']); ?></select>
                </div>
            </div>
        </section>

        <!-- Примечание -->
        <section class="card" style="grid-column:1/-1">
            <h3>Примечание</h3>
            <input type="text" name="remark" form="saveForm"
                   value="<?= htmlspecialchars(($analog_data['comment'] ?? '')) . ($analog_filter !== '' ? ' ANALOG_FILTER='.htmlspecialchars($analog_filter) : '') ?>"
                   placeholder="Произвольный комментарий" />
        </section>
    </div>

    <!-- Кнопки -->
    <form id="saveForm" action="processing_add_salon_filter_into_db.php" method="post"></form>
    <div class="actions">
        <div class="muted">Проверьте корректность параметров перед сохранением.</div>
        <div style="display:flex; gap:10px">
            <button type="submit" form="saveForm" class="btn">Сохранить фильтр</button>
            <button type="button" class="btn secondary" onclick="history.back()">Отмена</button>
        </div>
    </div>

</div>

<script>
    function replacement(field){
        var inputField = document.getElementById(field);
        if(!inputField) return;
        inputField.addEventListener('input', function() {
            inputField.value = inputField.value.replace(/,/g, '.');
        });
    }
    replacement("width_input");
    replacement("height_input");
    replacement("line_width_input");
</script>

</body>
</html>
