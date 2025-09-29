<?php
// NP_build_plan_week.php — недельный календарь (2 бригады) с фиксированной высотой 13ч
// Требуются эндпоинты в NP_build_plan.php: action=load, save, busy, meta

$dsn  = "mysql:host=127.0.0.1;dbname=plan_u5;charset=utf8mb4";
$user = "root";
$pass = "";
$SHIFT_HOURS = 11.5; // фактическая смена для расчётов



$order = $_GET['order_number'] ?? '';
if ($order==='') { http_response_code(400); exit('Укажите ?order=...'); }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
// Подсказки по заявкам из corrugation_plan (только тут, без API)
$orderSuggestions = [];
try{
    $pdoSug = new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
    $st = $pdoSug->query("
        SELECT cp.order_number, MAX(cp.plan_date) AS last_date
        FROM corrugation_plan cp
        GROUP BY cp.order_number
        ORDER BY last_date DESC
        LIMIT 100
    ");
    $orderSuggestions = $st->fetchAll();
}catch(Throwable $e){
    $orderSuggestions = [];
}

?>
<!doctype html>
<html lang="ru">
<meta charset="utf-8">
<title>План (неделя) — заявка <?=h($order)?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root{
        --line:#e5e7eb; --grid:#eef2f7; --muted:#667085; --accent:#2563eb;
        --brig1:#fff7db; --brig2:#eef5ff; --event:#fef3c7; --event-bd:#f59e0b;
        --bg:#f7f9fc;
        --pxh: 40px; /* pixels per 1 hour (will be overridden by JS) */
    }
    *{box-sizing:border-box}
    body{margin:0;font:13px system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;background:var(--bg);padding-top:60px}
    header{display:flex;gap:8px;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--line);background:#fff;position:fixed;top:0;left:0;right:0;z-index:100;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
    .controls{display:flex;gap:8px;align-items:center}
    .btn{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer;position:relative;z-index:1000}
    .btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}
    .muted{color:var(--muted)}
    .week-wrap{padding:10px;margin-top:0}
    .week{display:grid;grid-template-columns: 60px repeat(7, 1fr); gap:6px; height: calc(100vh - 120px);}
    .hours{background:#fff;border:1px solid var(--line);border-radius:10px;position:relative}
    .hours .h{position:absolute;left:6px;transform:translateY(-50%);font-size:11px;color:#94a3b8}
    .day{
        display:grid;
        grid-template-rows:auto 1fr;  /* шапка сама по высоте */
        gap:6px;
    }
    /* day-top — пусть переносится на 2 строку при нехватке места */
    .day-top{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        flex-wrap:wrap;               /* ← разрешили перенос */
    }/* дата не сжимается и не переносится */
    .day-date{
        font-weight:600;
        white-space:nowrap;
        flex:0 0 auto;                /* ← не shrink */
    }

    /* чипам можно сжиматься/расти и переноситься */
    .day-chips{
        display:flex;
        flex-wrap:wrap;
        gap:6px;
        justify-content:flex-end;
        flex:1 1 auto;                /* ← можно shrink/grow */
    }
    .day-head{background:#fff;border:1px solid var(--line);border-radius:10px;padding:6px 8px;display:flex;align-items:center;justify-content:space-between}
    .brig-wrap{background:#fff;border:1px solid var(--line);border-radius:10px;display:grid;grid-template-rows:1fr 1fr;gap:4px;overflow:hidden;position:relative}
    .lane{position:relative; overflow:hidden; border-top:1px dashed #f0f2f7}
    .lane:first-child{border-top:none}
    .lane.b1{background:#fff}
    .lane.b2{background:#fff}
    .event{position:absolute; left:6px; right:6px; border:1px solid var(--event-bd); background:var(--event);
        border-radius:10px; padding:6px 8px; cursor:grab; box-shadow:0 1px 0 rgba(0,0,0,.04)}
    .event:active{cursor:grabbing}
    /* режим разделения позиций */
    .split-mode .event{cursor:url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiIGhlaWdodD0iMTYiIHZpZXdCb3g9IjAgMCAxNiAxNiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTMwIj4KPC9wYXRoPgo8L3N2Zz4K") crosshair}
    .split-mode .lane{border:2px dashed #ef4444; background:rgba(239,68,68,0.1); border-radius:8px}
    .split-mode{position:relative}
    .split-mode::before{content:"Режим разделения: выберите позицию для разделения"; position:fixed; top:80px; left:50%; transform:translateX(-50%); background:#000; color:#fff; padding:8px 16px; border-radius:20px; font-size:12px; z-index:200; box-shadow:0 4px 12px rgba(0,0,0,0.3)}
    .split-mode.active .splitBtn{background:#ef4444; color:#fff; border-color:#ef4444}
    .event h4{margin:0 0 4px; font-size:12.5px; display:flex; align-items:center; gap:6px}
    .event h4 .ttl{min-width:0; flex:1 1 auto; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .event h4 .cx{flex:0 0 auto}
    .event .sub{font-size:11px;color:#475569;display:flex;gap:8px;flex-wrap:nowrap; white-space:nowrap; overflow:hidden; text-overflow:ellipsis}
    .badge{font-size:11px; padding:1px 6px; border:1px solid #d1d5db; border-radius:999px; background:#fff}
    .legend{display:flex; gap:8px; align-items:center}
    .legend .dot{width:10px;height:10px;border-radius:50%}
    .dot.b1{background:var(--brig1); border:1px solid #e6d8a3}
    .dot.b2{background:var(--brig2); border:1px solid #cfe0ff}
    .totals{font-size:12px}
    .over{outline:2px solid #ef4444; outline-offset:-2px}
    /* busy/shift маркеры внутри дорожек */
    .lane .busyBar{
        position:absolute; left:0; right:0; top:0;
        background:rgba(148,163,184,.25);   /* slate-400 ~ 25% */
        border-bottom:1px solid rgba(148,163,184,.5);
        z-index:0;
    }
    .lane .shiftLine{
        position:absolute; left:0; right:0; height:0;
        border-top:2px dashed #94a3b8;      /* slate-400 */
        z-index:1;
    }
    .event{ z-index:2; }                   /* события поверх маркеров */
    /* компактные карточки */
    .event.compact{ padding:4px 6px; }
    .event.compact h4{ margin:0 0 2px; font-size:12px; }
    .event.tiny{ padding:2px 6px; }
    .event.tiny h4{ margin:0; font-size:12px; }
    .event.tiny .sub{ display:none; }        /* у «крошек» прячем подзаголовок */
    /* принудительно скрыть вторую строку (подстроку) */
    .event.force-hide-sub .sub{ display:none; }
    /* новая компоновка шапки дня */
    .day-head{
        background:#fff;border:1px solid var(--line);border-radius:10px;
        padding:6px 8px; display:flex; flex-direction:column; gap:4px;
    }
    .day-top{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .day-date{ font-weight:600; white-space:nowrap; }

    /* компактные заголовки смен */
    .day-chips{ display:flex; flex-direction:column; gap:4px; }
    .shift-info{
        display:flex; align-items:center; gap:4px; padding:2px 0;
        font-size:11px; white-space:nowrap;
    }
    .shift-info .dot{ width:6px; height:6px; border-radius:50%; border:1px solid #94a3b8; flex-shrink:0; }
    .shift-info.b1 .dot{ background:var(--brig1); border-color:#e6d8a3; }
    .shift-info.b2 .dot{ background:var(--brig2); border-color:#cfe0ff; }
    .shift-label{ font-weight:600; color:var(--muted); min-width:0px; }
    .shift-data{ color:#0f172a; }
    .complexity-indicator{
        width:8px; height:8px; border-radius:50%; margin-left:6px; flex-shrink:0; 
        display:inline-block; border:1px solid rgba(0,0,0,0.1);
    }
    .complexity-text{
        margin-left:4px; 
        font-size:11px; 
        font-weight:500; 
        text-transform:none;   /* Убираем заглавные буквы для числовых значений */
        display:none;    /* По умолчанию скрыт */
    }

    /* сложность сборки: low/mid/high */
    .chip.cx{ background:#fff; border-color:#e5e7eb; padding:2px 6px; gap:6px }
    .chip.cx .dot{ border:none }
    .chip.cx.low  .dot{ background:#22c55e }   /* зеленый */
    .chip.cx.mid  .dot{ background:#f59e0b }   /* оранжевый */
    .chip.cx.high .dot{ background:#ef4444 }   /* красный */
    .chip.cx .lbl{ color:#475569; }
    /* компактные варианты чипа сложности */
    .cx-dot{ display:inline-flex; align-items:center; gap:4px }
    .cx-dot .dot{ width:8px; height:8px; border-radius:50% }
    
    /* Простые маркеры сложности без овалов */
    .cx .dot{ 
        width: 8px; 
        height: 8px; 
        border-radius: 50%; 
        display: inline-block; 
        margin-left: 4px;
    }

    /* Кружочек с высотой */
    .height-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 600;
        color: #374151;
        background: transparent;
        border: 1px solid #6b7280;
        margin-left: 6px;
        flex-shrink: 0;
    }

    .orderBox{display:flex;gap:6px;align-items:center}
    .orderInput{padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;width:170px}
    .badgeOrder{font-size:12px;color:var(--muted)}

    /* Стили для элементов управления тепловой картой */
    .heatmap-controls{display:flex;gap:8px;align-items:center}
    .heatmap-mode-btn{padding:6px 12px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;cursor:pointer;transition:all 0.2s;font-size:12px}
    .heatmap-mode-btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
    .heatmap-mode-btn:hover:not(.active){background:#f8fafc;border-color:#94a3b8}

    /* глобальная заливка прогресса (факт/план по позиции по всей заявке) */
    .event .fillGlobal{
        position:absolute; left:0; right:0; bottom:0;
        background:rgba(22,163,74,.28);            /* зелёная полупрозрачная */
        border-top:1px solid rgba(22,163,74,.55);
        pointer-events:none;
    }

    /* тонкая шапка при перепроизводстве (факт > план) */
    .event .overHat{
        position:absolute; left:0; right:0; top:0; height:4px;
        background:repeating-linear-gradient(45deg,
        rgba(220,38,38,.5) 0, rgba(220,38,38,.5) 6px, transparent 6px, transparent 12px);
        pointer-events:none;
    }

    /* Мобильная версия - увеличиваем размеры блоков на 10% */
    @media (max-width: 768px) {
        .panel, .day-head, .brig-wrap, .event {
            /* Увеличиваем размер блоков на 10% для лучшей читаемости на мобильных */
            transform: scale(1.1);
            margin: 5px; /* Небольшой отступ для лучшего восприятия */
        }
        
        /* Увеличиваем шрифты для лучшей читаемости на мобильных */
        .event h4 { font-size: 16px; /* 12.5px -> 16px (+28%) */ }
        .event .sub { font-size: 14px;  /* 11px -> 14px (+27%) */ }
        .shift-info { font-size: 14px;   /* 11px -> 14px (+27%) */ }
        .day-date { font-size: 16px; } /* Увеличиваем дату в заголовке дня */
        .section-title { font-size: 18px; } /* Увеличиваем заголовки секций */
        .btn { font-size: 16px; } /* Увеличиваем шрифт кнопок */
        .heatmap-mode-btn { font-size: 14px; } /* Увеличиваем шрифт кнопок тепловой карты */
        .legend { font-size: 14px; } /* Увеличиваем легенду */
        .totals { font-size: 14px; } /* Увеличиваем итоги */
        
        /* Увеличиваем кнопки и элементы интерфейса */
        .btn { padding: 10px 16px; } /* было 6px 10px, теперь больше для увеличенных шрифтов */
        
        /* Увеличиваем высоту дорожек для бригад */
        .lane { min-height: 60px; } /* была меньше, теперь конкретный минимум */
        
        /* Компенсация для общей сетки чтобы блоки лучше помещались */
        .week {
            grid-template-columns: 65px repeat(7, 1fr);  /* Увеличили колонку часов с 60px */
            gap: 7px; /* Увеличили промежутки с 6px */
        }
        
        /* Улучшаем заголовок дня */
        .day-head { padding: 8px 10px; } /* было 6px 8px */
        
        /* Больше места для текста на кнопках */
        .heatmap-mode-btn { padding: 10px 16px; } /* Увеличиваем padding для увеличенных шрифтов */
        
        /* Увеличиваем кружочек с высотой для мобильных */
        .height-dot {
            width: 20px;
            height: 20px;
            font-size: 11px;
            margin-left: 8px;
            color: #374151;
            background: transparent;
            border: 1px solid #6b7280;
        }
    }

</style>

<header>
    <div class="controls">
        <button class="btn" id="prevWeek">‹</button>
        <div id="weekTitle" style="font-weight:600"></div>
        <button class="btn" id="nextWeek">›</button>
        <button class="btn" id="todayBtn">Сегодня</button>
        <button class="btn" id="toggleSpan">2 недели</button>
        <button class="btn" id="allOrderBtn">Вся заявка</button>
    </div>
    <div class="legend">
        <span class="muted">Сложность:</span>
        <span class="cx-dot" title="низкая" style="margin-left:4px"><span class="dot" style="background:#22c55e"></span></span>
        <span class="cx-dot" title="средняя" style="margin-left:6px"><span class="dot" style="background:#f59e0b"></span></span>
        <span class="cx-dot" title="высокая" style="margin-left:6px"><span class="dot" style="background:#ef4444"></span></span>
    </div>
    <div class="controls">
        <button class="btn" id="loadBtn">Загрузить</button>
        <button class="btn primary" id="saveBtn" >Сохранить</button>
        <button class="btn" id="testBtn" onclick="alert('Test button works!')">Тест</button>
        <button class="btn" id="splitBtn" title="Разделить позицию на части (пробел)">✂</button>
        <button class="btn" id="undoSplitBtn" title="Откатить последнее разделение" disabled>⟲</button>
        <button class="btn" id="bufferBtn" title="Плавающий буфер">📋</button>
        <div class="heatmap-controls">
            <span class="muted">Режим:</span>
            <button class="btn heatmap-mode-btn active" data-mode="none">Без карты</button>
            <button class="btn heatmap-mode-btn" data-mode="heights">Высоты</button>
            <button class="btn heatmap-mode-btn" data-mode="complexity">Сложность</button>
        </div>
    </div>
</header>

<div class="week-wrap">
    <div class="week" id="weekGrid">
        <div class="hours" id="hourCol"></div>
    </div>
</div>

<!-- Плавающий буфер -->
<div id="bufferPanel" style="position:fixed; top:70px; right:20px; width:300px; max-height:calc(100vh - 80px); background:#fff; border:2px solid var(--accent); border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:999; display:none; flex-direction:column;">
    <div style="padding:12px 16px; border-bottom:1px solid #e5e7eb; background:var(--accent); color:#fff; border-radius:10px 10px 0 0; display:flex; justify-content:space-between; align-items:center;">
        <h3 style="margin:0; font-size:14px; font-weight:600;">📋 Буфер</h3>
        <button id="closeBuffer" style="background:none; border:none; color:#fff; font-size:18px; cursor:pointer; padding:0; width:24px; height:24px;">×</button>
    </div>
    <div id="bufferContent" style="flex:1; overflow-y:auto; padding:8px; min-height:60px; max-height:calc(100vh - 140px);">
        <div id="emptyBuffer" style="text-align:center; color:#94a3b8; font-size:13px; padding:20px;">
            Буфер пуст.<br>Перетащите сюда позиции<br>из плана
        </div>
    </div>
</div>

<!-- Модальное окно для разделения позиций -->
<div id="splitModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center">
    <div style="background:#fff; border-radius:10px; padding:20px; box-shadow:0 8px 24px rgba(0,0,0,0.2); max-width:350px; width:90%">
        <h3 style="margin:0 0 16px; font-size:16px; color:#111">Разделить позицию</h3>
        <div style="margin:12px 0">
            <label style="display:block; margin-bottom:6px; font-size:14px; color:#555">Размер первой части:</label>
            <select id="splitSize" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:6px; font-size:14px">
                <option value="10">10% от позиции</option>
                <option value="20">20% от позиции</option>
                <option value="30">30% от позиции</option>
                <option value="40">40% от позиции</option>
                <option value="50" selected>50% от позиции</option>
            </select>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px">
            <button id="splitCancel" style="padding:8px 16px; border:1px solid #ddd; background:#fff; border-radius:6px; cursor:pointer">Отмена</button>
            <button id="splitConfirm" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer">Разделить</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const API = 'NP_build_plan.php';

        if (!window.CSS || typeof CSS.escape !== 'function') {
            window.CSS = window.CSS || {}; CSS.escape = (s)=> String(s).replace(/[^a-zA-Z0-9_\-]/g, m => '\\' + m);
        }

        // === ключевые константы
        const ORDER = <?= json_encode($order) ?>;
        const SHIFT_H = <?= json_encode($SHIFT_HOURS) ?>; // расчёты (занятость/перегруз)
        const VIEW_H  = 13;                               // высота дорожки и шкалы (без скролла)
        // уменьшаем вертикальный масштаб ~на 20%: 40 -> 32 px
        const PX_PER_HOUR = 32;
        const GRID_STEP_H = 0.5;
        const FALLBACK_SLOT_H = 0.5;                      // если нет нормы (фиксируем!)
        const MIN_SLOT_H = 0.25;
        // количество отображаемых дней (режим: 7, 14 или все дни заявки)
        let spanDays = 7;
        let allOrderMode = false;
        let heatmapMode = 'none'; // 'none', 'complexity' или 'heights'
        let splitMode = false;   // режим разделения позиций
        const COMPACT_H = 1.0;  // ≤1.0 ч — компакт: дату скрыть
        const TINY_H    = 0.7;  // ≤0.7 ч — очень компактно: оставить только заголовок
        const TEAM_CAP = { '1': SHIFT_H, '2': 8 };  // вместимость дорожки: бр-1 = 11.5ч, бр-2 = 8ч
        const cap = (team) => +(TEAM_CAP[team] ?? SHIFT_H);




        // === состояние
        let weekStart = startOfWeek(new Date());
        // row: {source_date, filter, count, rate, height, baseH, _fallback}
        const plan = new Map();          // Map(day -> { '1':[], '2':[] })
        const busyHours = new Map();     // Map(day -> {'1':hrs,'2':hrs})
        let buffer = [];                 // массив для хранения позиций в буфере
        
        // === система отката разделений
        let splitHistory = [];          // массив: { day, team, originalRow, splitIndex, insertIndex }
        let maxHistorySize = 10;         // максимальное количество операций в истории

        // === DOM
        const weekGrid = document.getElementById('weekGrid');
        const hourCol  = document.getElementById('hourCol');
        const toggleSpanBtn = document.getElementById('toggleSpan');
        const allOrderBtn = document.getElementById('allOrderBtn');
        const heatmapBtn = document.getElementById('heatmapBtn');
        const saveBtn = document.getElementById('saveBtn');

        // === helpers
        function startOfWeek(d){ const nd=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate())); let day=nd.getUTCDay(); if(day===0) day=7; nd.setUTCDate(nd.getUTCDate()-(day-1)); return nd; }
        function fmtDate(d){ return new Date(d).toISOString().slice(0,10); }
        function addDays(d,n){ const x=new Date(d); x.setUTCDate(x.getUTCDate()+n); return x; }
        function fmt1(x){ return (Math.round((x||0)*10)/10).toFixed(1); }
        function escapeHtml(s){ return (s??'').replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
        function ensureDay(day){ if(!plan.has(day)) plan.set(day, {'1':[], '2':[]}); }

        // === шкала часов до VIEW_H
        buildHourColumn();
        function buildHourColumn(){
            const px = Math.ceil(VIEW_H/GRID_STEP_H)*GRID_STEP_H * PX_PER_HOUR;
            hourCol.style.height = (px+12)+'px';
            hourCol.innerHTML = '';
            for(let i=0;i<=VIEW_H;i++){
                const lab = document.createElement('div');
                lab.className='h'; lab.style.top = (i*PX_PER_HOUR)+'px'; lab.textContent = i+'ч';
                hourCol.appendChild(lab);
            }
            // Обновим CSS переменную для линий сетки
            document.documentElement.style.setProperty('--pxh', PX_PER_HOUR + 'px');
        }

        // === рендер
        renderWeek(false);
        document.getElementById('prevWeek').onclick = ()=>{ weekStart = addDays(weekStart,-spanDays); renderWeek(false); };
        document.getElementById('nextWeek').onclick = ()=>{ weekStart = addDays(weekStart,+spanDays); renderWeek(false); };
        document.getElementById('todayBtn').onclick = ()=>{ weekStart = startOfWeek(new Date()); renderWeek(false); };
        document.getElementById('loadBtn').onclick  = () => {
            console.log('Load button clicked');
            loadPlan();
        };
        
        // Проверяем, что кнопка загрузки существует
        const loadBtn = document.getElementById('loadBtn');
        console.log('Load button element:', loadBtn);
        if (!loadBtn) {
            console.error('Load button not found in DOM!');
        } else {
            // Проверяем стили кнопки
            const styles = window.getComputedStyle(loadBtn);
            console.log('Load button styles:', {
                display: styles.display,
                visibility: styles.visibility,
                pointerEvents: styles.pointerEvents,
                opacity: styles.opacity,
                zIndex: styles.zIndex
            });
            
            // Проверяем, что кнопка кликабельна
            loadBtn.addEventListener('click', (e) => {
                console.log('Direct click event fired on load button');
            });
            
            // Альтернативный способ обработки клика
            loadBtn.addEventListener('mousedown', (e) => {
                console.log('Mouse down on load button');
            });
            
            loadBtn.addEventListener('mouseup', (e) => {
                console.log('Mouse up on load button');
            });
        }
        document.getElementById('saveBtn').onclick  = savePlan;
        
        // Обработчики для кнопок режима тепловой карты
        document.querySelectorAll('.heatmap-mode-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                heatmapMode = btn.dataset.mode;
                document.querySelectorAll('.heatmap-mode-btn').forEach(b => 
                    b.classList.toggle('active', b === btn)
                );
                renderWeek(false); // Перерисовываем календарь с новым режимом
            });
        });

        if (toggleSpanBtn){
            toggleSpanBtn.onclick = ()=>{
                allOrderMode = false;
                spanDays = (spanDays===7 ? 14 : 7);
                updateSpanBtnLabel();
                updateAllOrderBtnLabel();
                renderWeek(false);
            };
            updateSpanBtnLabel();
        }

        // === Обработчики режима разделения позиций
        const splitBtn = document.getElementById('splitBtn');
        const splitModal = document.getElementById('splitModal');
        const splitCancel = document.getElementById('splitCancel');
        const splitConfirm = document.getElementById('splitConfirm');
        const splitSize = document.getElementById('splitSize');
        const undoSplitBtn = document.getElementById('undoSplitBtn');
        let splitTargetEvent = null;

        splitBtn.onclick = () => toggleSplitMode();
        splitCancel.onclick = () => cancelSplit();
        splitConfirm.onclick = () => confirmSplit();
        undoSplitBtn.onclick = () => undoLastSplit();

        function toggleSplitMode() {
            splitMode = !splitMode;
            if (splitMode) {
                splitBtn.classList.add('active');
                document.body.classList.add('split-mode');
                document.getElementById('weekTitle').textContent += ' (Режим разделения)';
            } else {
                splitBtn.classList.remove('active');
                document.body.classList.remove('split-mode');
                document.getElementById('weekTitle').textContent = document.getElementById('weekTitle').textContent.replace(' (Режим разделения)', '');
            }
        }

        function cancelSplit() {
            splitModal.style.display = 'none';
            splitTargetEvent = null;
        }

        // Вспомогательная функция для пересчета времени позиции
        function recalculatePositionTime(row) {
            const rate = row.rate || 0;
            const count = row.count;
            const rawH = rate>0 ? (count / rate) * SHIFT_H : 0;
            const baseH = rawH>0 ? rawH : FALLBACK_SLOT_H;
            row.baseH = baseH;
            row._originalBaseH = baseH; // ОБНОВЛЯЕМ исходное время
            row._fallback = (rawH<=0);
        }

        function confirmSplit() {
            if (!splitTargetEvent) return;
            
            const splitPercent = parseInt(splitSize.value);
            const restPercent = 100 - splitPercent;
            
            const row = splitTargetEvent._row;
            const originalCount = row.count;
            const firstPart = Math.round(originalCount * splitPercent / 100);
            const secondPart = originalCount - firstPart;
            
            if (splitTargetEvent._row && splitTargetEvent._day && splitTargetEvent._team) {
                // Сохраняем исходную позицию для отката
                const originalRowCopy = JSON.parse(JSON.stringify(row));
                
                // Обновляем текущую запись
                row.count = firstPart;
                recalculatePositionTime(row); // Пересчитываем время для первой части
                
                // Создаем новую запись для второй части  
                const newRow = JSON.parse(JSON.stringify(row));
                newRow.count = secondPart;
                recalculatePositionTime(newRow); // Пересчитываем время для второй части
                
                // Добавляем новую часть после текущей
                const arr = plan.get(splitTargetEvent._day)[splitTargetEvent._team];
                const currentIndex = arr.indexOf(row);
                const insertIndex = currentIndex + 1;
                arr.splice(insertIndex, 0, newRow);
                
                // Сохраняем в историю разделений для отката
                addToSplitHistory({
                    day: splitTargetEvent._day,
                    team: splitTargetEvent._team,
                    originalRow: originalRowCopy,
                    originalIndex: currentIndex,
                    insertIndex: insertIndex
                });
                
                // Перерисовываем календарь
                renderWeek(false);
            }
            
            splitModal.style.display = 'none';
            splitTargetEvent = null;
        }

        // === Функции для управления историей разделений
        function addToSplitHistory(splitData) {
            // Добавляем операцию в историю
            splitHistory.push(splitData);
            
            // Ограничиваем размер истории
            if (splitHistory.length > maxHistorySize) {
                splitHistory.shift(); // удаляем самую старую
            }
            
            // Активируем кнопку отката
            undoSplitBtn.disabled = false;
        }

        function undoLastSplit() {
            if (splitHistory.length === 0) return;
            
            // Берем последнюю операцию разделения
            const lastSplit = splitHistory.pop();
            const { day, team, originalRow, insertIndex } = lastSplit;
            
            const arr = plan.get(day)[team];
            if (!arr || insertIndex >= arr.length) return;
            
            // Удаляем разделенные части
            if (insertIndex > 0) {
                arr.splice(insertIndex - 1, 2); // удаляем обе части
            }
            
            // Восстанавливаем исходную позицию
            ensureDay(day);
            const teamArray = plan.get(day)[team];
            teamArray.push({
                source_date: originalRow.source_date,
                filter: originalRow.filter, 
                count: originalRow.count,
                rate: originalRow.rate,
                height: originalRow.height,
                complexity: originalRow.complexity,
                baseH: originalRow.baseH, 
                _fallback: originalRow._fallback
            });
            
            // Отключить кнопку отката если нет операций
            if (splitHistory.length === 0) {
                undoSplitBtn.disabled = true;
            }
            
            // Перерисовываем календарь
            renderWeek(false);
        }

        // Обработчик клавиши пробел для активирования режима разделения
        document.addEventListener('keydown', (e) => {
            // Предотвращаем срабатывание на пробел в input полях
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            if (e.code === 'Space') {
                e.preventDefault();
                toggleSplitMode();
            }
            if (e.code === 'Escape' && (splitMode || splitModal.style.display === 'flex')) {
                if (splitModal.style.display === 'flex') {
                    cancelSplit();
                } else {
                    toggleSplitMode();
                }
            }
        });

        // === Обработчики плавающего буфера
        const bufferPanel = document.getElementById('bufferPanel');
        const bufferBtn = document.getElementById('bufferBtn');
        const closeBuffer = document.getElementById('closeBuffer');
        const bufferContent = document.getElementById('bufferContent');
        const emptyBuffer = document.getElementById('emptyBuffer');

        bufferBtn.onclick = () => toggleBuffer();
        closeBuffer.onclick = () => closeBufferPanel();
        
        // === Функциональность перетаскивания буфера ===
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        
        // Делаем заголовок буфера перетаскиваемым
        const bufferHeader = bufferPanel.querySelector('div:first-child');
        if (bufferHeader) {
            bufferHeader.style.cursor = 'move';
            bufferHeader.style.userSelect = 'none';
            
            bufferHeader.addEventListener('mousedown', (e) => {
                if (e.target.id === 'closeBuffer') return; // Не перетаскиваем при клике на кнопку закрытия
                isDragging = true;
                dragOffset.x = e.clientX - bufferPanel.offsetLeft;
                dragOffset.y = e.clientY - bufferPanel.offsetTop;
                bufferPanel.style.transition = 'none';
                e.preventDefault();
            });
        }
        
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            
            const newX = e.clientX - dragOffset.x;
            const newY = e.clientY - dragOffset.y;
            
            // Ограничиваем перемещение в пределах экрана
            const maxX = window.innerWidth - bufferPanel.offsetWidth;
            const maxY = window.innerHeight - bufferPanel.offsetHeight;
            
            bufferPanel.style.left = Math.max(0, Math.min(newX, maxX)) + 'px';
            bufferPanel.style.top = Math.max(0, Math.min(newY, maxY)) + 'px';
            bufferPanel.style.right = 'auto';
        });
        
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
                bufferPanel.style.transition = '';
            }
        });

        function toggleBuffer() {
            if (bufferPanel.style.display === 'none' || bufferPanel.style.display === '') {
                bufferPanel.style.display = 'flex';
            } else {
                bufferPanel.style.display = 'none';
            }
        }

        function closeBufferPanel() {
            bufferPanel.style.display = 'none';
        }

        function addToBuffer(row) {
            // Добавляем позицию в буфер
            row._isInBuffer = true;
            const bufferItem = {...row, _bufferId: Date.now()};
            buffer.push(bufferItem);
            updateBufferDisplay();
            updateSaveButtonState();
        }

        // Добавить drop зону в буфер для обратного переноса
        bufferContent.addEventListener('dragover', e => e.preventDefault());
        bufferContent.addEventListener('drop', e => {
            e.preventDefault();
            const dragging = document.querySelector('.event.dragging');
            const draggingBuffer = document.querySelector('.buffer-item.dragging');
            
            if (dragging && dragging._row) {
                // Перетаскивание из плана в буфер
                const row = dragging._row;
                const srcDay = dragging._day || row.source_date || row.day;
                const srcTeam = dragging._team || row.team;
                
                // Улучшенный поиск позиции - в нескольких местах 
                let found = false;
                
                // Способ 1: через stored day/team
                if (srcDay && srcTeam) {
                    const arr = plan.get(srcDay)?.[srcTeam];
                    if (arr) {
                        const idx = arr.findIndex(r => r === row);
                        if (idx >= 0) {
                            arr.splice(idx, 1);
                            found = true;
                        }
                    }
                }
                
                // Способ 2: всесторонний поиск по всему плану
                if (!found) {
                    plan.forEach((teams, day) => {
                        if (found) return; // продолжать если уже найдено 
                        Object.keys(teams).forEach(team => {
                            if (found) return; // также здесь
                            const teamArr = teams[team];
                            const foundIdx = teamArr.findIndex(r => r === row);
                            if (foundIdx >= 0) {
                                teamArr.splice(foundIdx, 1);
                                found = true;
                            }
                        });
                    });
                }
                
                if (found) {
                    addToBuffer(row);
                    renderWeek(false);
                }
            }
            // Если перетаскивание из буфера обратно в план будет обрабатываться в lane drop handlers
        });

        function removeFromBuffer(bufferId) {
            // Удаляем позицию из буфера - точный поиск по ID
            const index = buffer.findIndex(item => 
                String(item._bufferId) === String(bufferId)
            );
            if (index > -1) {
                buffer.splice(index, 1);
                updateBufferDisplay();
                updateSaveButtonState();
                
                // Дополнительная проверка при удалении последней позиции
                if (buffer.length === 0) {
                    const emptyBufferMsg = document.getElementById('emptyBuffer');
                    if (emptyBufferMsg) {
                        emptyBufferMsg.style.display = 'block';
                    } else {
                        bufferContent.innerHTML = '<div id="emptyBuffer" style="text-align:center; color:#94a3b8; font-size:13px; padding:20px;">Буфер пуст.<br>Перетащите сюда позиции<br>из плана</div>';
                    }
                }
            }
        }

        function updateSaveButtonState() {
            // Проверяем что кнопка сохранения существует
            if (!saveBtn) {
                return; // Кнопка еще не создана
            }
            
            // Проверяем, есть ли позиции в плане
            let hasPositions = false;
            plan.forEach((byTeam) => {
                ['1', '2'].forEach(team => {
                    if ((byTeam[team] || []).length > 0) {
                        hasPositions = true;
                    }
                });
            });
            
            // Обновляем состояние кнопки сохранения
            if (buffer.length > 0) {
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.5';
                saveBtn.style.cursor = 'not-allowed';
                saveBtn.title = 'Нельзя сохранить пока в буфере есть позиции';
            } else if (!hasPositions) {
                saveBtn.disabled = true;
                saveBtn.style.opacity = '0.5';
                saveBtn.style.cursor = 'not-allowed';
                saveBtn.title = 'Нельзя сохранить пустой план';
            } else {
                saveBtn.disabled = false;
                saveBtn.style.opacity = '1';
                saveBtn.style.cursor = 'pointer';
                saveBtn.title = 'Сохранить план';
            }
        }

        function updateBufferDisplay() {
            // Проверяем что DOM элементы буфера существуют
            if (!bufferContent || !emptyBuffer) {
                return; // Элементы еще не созданы
            }
            
            if (buffer.length === 0) {
                emptyBuffer.style.display = 'block';
                // Очищаем контент если массив пустой - резервное решение
                bufferContent.innerHTML = '<div id="emptyBuffer" style="text-align:center; color:#94a3b8; font-size:13px; padding:20px;">Буфер пуст.<br>Перетащите сюда позиции<br>из плана</div>';
                updateSaveButtonState();
                return;
            }
            emptyBuffer.style.display = 'none';
            
            bufferContent.innerHTML = '';
            buffer.forEach(item => {
                const bufferItem = document.createElement('div');
                bufferItem.className = 'buffer-item';
                bufferItem.style.cssText = `
                    border: 1px solid #e5e7eb; border-radius: 4px; padding: 3px 4px; margin: 1px 0;
                    background: #f9fafb; cursor: grab; position: relative;
                `;
                bufferItem.draggable = true;
                bufferItem._bufferItem = item;
                bufferItem.dataset.bufferId = item._bufferId; // Сохраняем bufferId в data attribute
                // Формируем строку с высотой, сложностью и датой
                const heightText = (item.height != null && !isNaN(item.height)) ? ` • ${item.height} мм` : '';
                const dateText = item.source_date ? ` • ${item.source_date}` : '';
                
                // Маркер сложности для буфера
                const complexityDot = item.complexity && item.complexity > 0
                    ? `<span style="display:inline-block; width:8px; height:8px; border-radius:50%; margin-left:4px; background:${
                        item.complexity <= 600 ? '#ef4444' : 
                        (item.complexity <= 1000 ? '#f59e0b' : '#22c55e')
                    }" title="Сложность: ${item.complexity}"></span>`
                    : '';
                
                bufferItem.innerHTML = `
                    <div style="font-weight:500; font-size:12px; line-height:1.2; display:flex; align-items:center; gap:4px;">
                        ${item.filter}${complexityDot}
                    </div>
                    <div style="font-size:11px; color:#666; line-height:1.2;">${item.count} шт • ${(item.baseH || 0).toFixed(1)}ч${heightText}${dateText}</div>
                `;
                bufferContent.appendChild(bufferItem);
                
                // Event handlers for buffer items
                bufferItem.addEventListener('dragstart', e => {
                    bufferItem.classList.add('dragging');
                });
                
                bufferItem.addEventListener('dragend', e => {
                    bufferItem.classList.remove('dragging');
                });
            });
        }

        if (allOrderBtn){
            allOrderBtn.onclick = async ()=>{
                allOrderMode = !allOrderMode;
                if (allOrderMode) {
                    // Перезагружаем все данные из базы при включении режима "Вся заявка"
                    await loadPlan();
                    // После загрузки рассчитываем период отображения
                    calculateAllOrderDays();
                } else {
                    spanDays = 7;
                }
                updateSpanBtnLabel();
                updateAllOrderBtnLabel();
                renderWeek(false);
            };
            updateAllOrderBtnLabel();
        }

        function updateSpanBtnLabel(){
            if (!toggleSpanBtn) return;
            if (allOrderMode) {
                toggleSpanBtn.textContent = '2 недели';
                toggleSpanBtn.disabled = true;
            } else {
                toggleSpanBtn.textContent = (spanDays===7 ? '2 недели' : '1 неделя');
                toggleSpanBtn.disabled = false;
            }
        }

        function updateAllOrderBtnLabel(){
            if (!allOrderBtn) return;
            allOrderBtn.textContent = allOrderMode ? '1 неделя' : 'Вся заявка';
            allOrderBtn.style.background = allOrderMode ? '#2563eb' : '';
            allOrderBtn.style.color = allOrderMode ? '#fff' : '';
        }

        function calculateAllOrderDays(){
            // Собираем все даты из плана
            const allDates = new Set();
            plan.forEach((_, day) => allDates.add(day));
            
            console.log('calculateAllOrderDays: found dates:', Array.from(allDates).sort());
            
            if (allDates.size === 0) {
                // Если план пустой, показываем текущую неделю
                spanDays = 7;
                console.log('calculateAllOrderDays: plan empty, using 7 days');
                return;
            }
            
            const sortedDates = Array.from(allDates).sort();
            const firstDate = new Date(sortedDates[0]);
            const lastDate = new Date(sortedDates[sortedDates.length - 1]);
            
            // Устанавливаем начало недели на первую дату
            weekStart = startOfWeek(firstDate);
            
            // Вычисляем количество дней от первой до последней даты
            const timeDiff = lastDate.getTime() - firstDate.getTime();
            const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
            
            // Показываем все дни заявки + небольшой буфер для удобства просмотра
            spanDays = Math.max(7, daysDiff + 3); // минимум 7 дней, без ограничения максимума
            
            console.log('calculateAllOrderDays: firstDate:', firstDate, 'lastDate:', lastDate, 'daysDiff:', daysDiff, 'spanDays:', spanDays);
            
            // Предупреждаем пользователя, если заявка очень большая
            if (spanDays > 60) {
                console.warn(`Заявка содержит ${spanDays} дней. Отображение может быть медленным.`);
            }
        }

        // === Heatmap functions ===
        function cxLevel(val){
            const v = +val || 0; if (!v) return null;
            if (v <= 600) return 'high';
            if (v <= 1000) return 'mid';
            return 'low';
        }
        
        function getHeightColor(height){
            const h = +height || 0;
            if (h <= 22) return '#bbf7d0';      // 20-22мм - зелёный
            if (h <= 25) return '#bfdbfe';      // 23-25мм - голубой
            if (h <= 27) return '#c7d2fe';      // 26-27мм - индиго
            if (h <= 30) return '#ddd6fe';      // 28-30мм - фиолетовый
            if (h <= 32) return '#fde68a';      // 31-32мм - жёлтый
            if (h <= 35) return '#fda4af';      // 33-35мм - розовый
            return '#f87171';                   // 36+ мм - красный
        }
        
        function calculateDayAverageComplexity(day) {
            const byTeam = plan.get(day);
            if (!byTeam) return null;
            
            let totalComplexity = 0;
            let totalCount = 0;
            
            ['1', '2'].forEach(team => {
                (byTeam[team] || []).forEach(row => {
                    if (row.complexity) {
                        totalComplexity += row.complexity * row.count;
                        totalCount += row.count;
                    }
                });
            });
            
            if (totalCount === 0) return null;
            
            const avgComplexity = totalComplexity / totalCount;
            if (avgComplexity <= 600) return {class: 'high', label: 'высокая'};
            if (avgComplexity <= 1000) return {class: 'mid', label: 'средняя'};
            return {class: 'low', label: 'низкая'};
        }
        
        function calculateDayAverageHeight(day) {
            const byTeam = plan.get(day);
            if (!byTeam) return null;
            
            let totalHeight = 0;
            let totalCount = 0;
            
            ['1', '2'].forEach(team => {
                (byTeam[team] || []).forEach(row => {
                    if (row.height) {
                        totalHeight += row.height * row.count;
                        totalCount += row.count;
                    }
                });
            });
            
            return totalCount > 0 ? totalHeight / totalCount : null;
        }
        
        function calculateShiftComplexity(day, team) {
            const byTeam = plan.get(day);
            if (!byTeam || !byTeam[team]) return null;
            
            let totalComplexity = 0;
            let totalCount = 0;
            
            byTeam[team].forEach(row => {
                if (row.complexity) {
                    totalComplexity += row.complexity * row.count;
                    totalCount += row.count;
                }
            });
            
            if (totalCount === 0) return null;
            
            const avgComplexity = totalComplexity / totalCount;
            let complexityClass, label;
            
            if (avgComplexity <= 600) {
                complexityClass = 'high';
                label = Math.round(avgComplexity).toString();
            } else if (avgComplexity <= 1000) {
                complexityClass = 'mid';
                label = Math.round(avgComplexity).toString();
            } else {
                complexityClass = 'low';
                label = Math.round(avgComplexity).toString();
            }
            
            return {class: complexityClass, label: label};
        }
        
        function updateShiftComplexityIndicators(day, col) {
            const indicator1 = col.querySelector('[data-complexity-1]');
            const indicator2 = col.querySelector('[data-complexity-2]');
            const textIndicator1 = col.querySelector('[data-complexity-text-1]');
            const textIndicator2 = col.querySelector('[data-complexity-text-2]');
            
            // Показываем индикаторы сложности для каждой смены всегда
            const complexity1 = calculateShiftComplexity(day, '1');
            const complexity2 = calculateShiftComplexity(day, '2');
            
            if (indicator1 && complexity1) {
                const color = complexity1.class === 'high' ? '#ef4444' : 
                            (complexity1.class === 'mid' ? '#f59e0b' : '#22c55e');
                indicator1.style.backgroundColor = color;
                indicator1.style.borderRadius = '50%';
                indicator1.style.width = '8px';
                indicator1.style.height = '8px';
                indicator1.style.display = 'inline-block';
                indicator1.style.marginLeft = '6px';
                indicator1.title = `Сложность: ${complexity1.label}`;
                
                // Обновляем текстовый индикатор
                if (textIndicator1) {
                    textIndicator1.textContent = complexity1.label;
                    textIndicator1.style.color = color;
                    textIndicator1.style.display = 'inline';
                }
            } else if (indicator1) {
                indicator1.style.backgroundColor = '';
                indicator1.style.display = 'none';
                indicator1.title = '';
                
                if (textIndicator1) {
                    textIndicator1.textContent = '';
                    textIndicator1.style.display = 'none';
                }
            }
            
            if (indicator2 && complexity2) {
                const color = complexity2.class === 'high' ? '#ef4444' : 
                            (complexity2.class === 'mid' ? '#f59e0b' : '#22c55e');
                indicator2.style.backgroundColor = color;
                indicator2.style.borderRadius = '50%';
                indicator2.style.width = '8px';
                indicator2.style.height = '8px';
                indicator2.style.display = 'inline-block';
                indicator2.style.marginLeft = '6px';
                indicator2.title = `Сложность: ${complexity2.label}`;
                
                // Обновляем текстовый индикатор
                if (textIndicator2) {
                    textIndicator2.textContent = complexity2.label;
                    textIndicator2.style.color = color;
                    textIndicator2.style.display = 'inline';
                }
            } else if (indicator2) {
                indicator2.style.backgroundColor = '';
                indicator2.style.display = 'none';
                indicator2.title = '';
                
                if (textIndicator2) {
                    textIndicator2.textContent = '';
                    textIndicator2.style.display = 'none';
                }
            }
        }

        function renderWeek(skipBusy){
            // настраиваем количество колонок сетки
            weekGrid.style.gridTemplateColumns = '60px repeat(' + spanDays + ', 1fr)';

            const d0 = fmtDate(weekStart), dN = fmtDate(addDays(weekStart, spanDays-1));
            let titleText = d0+' — '+dN;
            if (allOrderMode) {
                titleText += ' (Вся заявка)';
            }
            document.getElementById('weekTitle').textContent = titleText;

            // очистка
            [...weekGrid.querySelectorAll('.day')].forEach(n=>n.remove());

            // колонки на spanDays
            for(let i=0;i<spanDays;i++){
                const day = fmtDate(addDays(weekStart,i));
                const col = document.createElement('div'); col.className='day'; col.dataset.day = day;

                const head = document.createElement('div'); head.className='day-head';
                head.innerHTML = `
                          <div class="day-top">
                            <div class="day-date">${day}</div>
                            <div class="day-chips">
                              <div class="shift-info b1">
                                <span class="dot"></span>
                                <span class="shift-data">
                                  <span class="t1" data-t1="${day}">0</span> шт · <span class="h1" data-h1="${day}">0.0</span> ч
                              </span>
                                <span class="complexity-indicator" data-complexity-1="${day}"></span>
                                <span class="complexity-text" data-complexity-text-1="${day}" style="margin-left:4px; font-size:11px; color:#6b7280;"></span>
                              </div>
                              <div class="shift-info b2">
                                <span class="dot"></span>
                                <span class="shift-data">
                                  <span class="t2" data-t2="${day}">0</span> шт · <span class="h2" data-h2="${day}">0.0</span> ч
                              </span>
                                <span class="complexity-indicator" data-complexity-2="${day}"></span>
                                <span class="complexity-text" data-complexity-text-2="${day}" style="margin-left:4px; font-size:11px; color:#6b7280;"></span>
                              </div>
                            </div>
                          </div>
                        `;
                col.appendChild(head);

                const wrap = document.createElement('div'); wrap.className='brig-wrap'; wrap.dataset.day = day;
                const lane1 = document.createElement('div'); lane1.className='lane b1'; lane1.dataset.day=day; lane1.dataset.team='1';
                const lane2 = document.createElement('div'); lane2.className='lane b2'; lane2.dataset.day=day; lane2.dataset.team='2';

                const heightPx = VIEW_H * PX_PER_HOUR; // фиксированная высота 13ч
                lane1.style.height = heightPx+'px'; lane2.style.height = heightPx+'px';
                // маркеры: busy-заливка и пунктир окончания реальной смены
                addMarkers(lane1);
                addMarkers(lane2);


                // DnD
                [lane1,lane2].forEach(l=>{
                    l.addEventListener('dragover', e=>e.preventDefault());
                    l.addEventListener('drop', e=>{
                        e.preventDefault();
                        const draggingEvent = document.querySelector('.event.dragging');
                        const draggingBuffer = document.querySelector('.buffer-item.dragging');
                        
                        if (draggingEvent && draggingEvent._row) {
                            // Перетаскивание из плана в другую позицию
                            const row = draggingEvent._row; 
                            const srcDay = draggingEvent._day; 
                            const srcTeam = draggingEvent._team;
                            const dstDay = l.dataset.day; 
                            const dstTeam = l.dataset.team;
                            if (srcDay === dstDay && srcTeam === dstTeam) return;
                            
                            // Сохраняем исходные данные позиции для восстановления
                            const originalBaseH = row._originalBaseH || row.baseH;
                            
                            const arr = plan.get(srcDay)?.[srcTeam] || []; 
                            const idx = arr.indexOf(row);
                            if (idx >= 0) arr.splice(idx, 1);
                            ensureDay(dstDay); 
                            plan.get(dstDay)[dstTeam].push(row);
                            
                            // Восстанавливаем исходное время после переноса
                            row.baseH = originalBaseH;
                            row._isTransferred = true;
                            if (row._originalBaseH) delete row._originalBaseH;
                            
                        renderWeek(false);
                        } else if (draggingBuffer && draggingBuffer._bufferItem) {
                            // Перетаскивание из буфера в план
                            const bufferItem = draggingBuffer._bufferItem;
                            const dstDay = l.dataset.day;
                            const dstTeam = l.dataset.team;
                            
                            // Создаем новую позицию в плане
                            const newRow = {
                                ...bufferItem,
                                source_date: dstDay, // обновляем дату на целевую
                                _isTransferred: true, // может быть перенесена
                                _isInBuffer: false,
                            };
                            delete newRow._bufferId;
                            
                            ensureDay(dstDay);
                            plan.get(dstDay)[dstTeam].push(newRow);
                            
                            // Удаляем из буфера - используем прямое удаление через функцию
                            const draggedBufferId = draggingBuffer.dataset.bufferId;
                            if (draggedBufferId) {
                                removeFromBuffer(draggedBufferId);
                            }
                            
                            renderWeek(false);
                        }
                    });
                });

                wrap.appendChild(lane1); wrap.appendChild(lane2);
                col.appendChild(wrap);
                weekGrid.appendChild(col);
            }

            // расчёт и отрисовка по дорожкам
            for(let i=0;i<spanDays;i++){
                const day = fmtDate(addDays(weekStart,i));
                ['1','2'].forEach(team=>{
                    const layout = computeLaneLayout(day, team);
                    let topH = 0;
                    layout.forEach(r=>{
                        paintEvent(day, team, r, r._effH, topH);
                        topH += r._effH;
                    });
                });
            }

            if (!skipBusy){
                const days = [...Array(spanDays)].map((_,i)=>fmtDate(addDays(weekStart,i)));
                fetchBusy(days).then(()=> renderWeek(true)); // второй рендер с актуальной занятостью
            }
            applyBusyMarkersForWeek();
            refreshTotals();
            updateSaveButtonState();
        }
        function addMarkers(lane){
            const busy = document.createElement('div');
            busy.className = 'busyBar';
            busy.style.height = '0px';
            lane.appendChild(busy);

            const sline = document.createElement('div');
            sline.className = 'shiftLine';
            sline.style.top = (Math.min(VIEW_H, SHIFT_H) * PX_PER_HOUR) + 'px';
            lane.appendChild(sline);
        }

        function applyBusyMarkersForWeek(){
            for(let i=0;i<spanDays;i++){
                const day = fmtDate(addDays(weekStart,i));
                ['1','2'].forEach(team=>{
                    const lane = document.querySelector(`.lane[data-day="${CSS.escape(day)}"][data-team="${team}"]`);
                    if (!lane) return;
                    const busy = (busyHours.get(day) || {})[team] || 0;
                    const busyPx = Math.min(VIEW_H, busy) * PX_PER_HOUR;
                    const bar = lane.querySelector('.busyBar');
                    if (bar) bar.style.height = busyPx + 'px';
                    const sline = lane.querySelector('.shiftLine');
                    if (sline) sline.style.top = (Math.min(VIEW_H, cap(team)) * PX_PER_HOUR) + 'px';
                });
            }
        }


        // === укладка: используем фиксированные baseH карточек; НЕ масштабируем время НИКОГДА
        function computeLaneLayout(day, team){
            const rows = (plan.get(day)?.[team]||[]).slice();
            const busy = ((busyHours.get(day)||{})[team]||0);
            const avail = Math.max(0, cap(team) - busy);     // в смене доступно

            // КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: НЕ масштабируем время позиций
            // Все позиции сохраняют свое исходное время независимо от загрузки смены
            rows.forEach(r=>{
                // ВСЕГДА используем исходный baseH без масштабирования
                const baseTime = r._originalBaseH || r.baseH || FALLBACK_SLOT_H;
                r._effH = Math.max(MIN_SLOT_H, baseTime);
            });

            // большие сверху
            rows.sort((a,b)=> (b._effH - a._effH) || String(a.filter).localeCompare(String(b.filter)));
            return rows;
        }

        function paintEvent(day, team, row, effH, topH){
            const lane = weekGrid.querySelector(`.lane[data-day="${CSS.escape(day)}"][data-team="${team}"]`);
            if(!lane) return;

            // сложность: нормализация 1350 (легко) -> 450 (сложно)
            function mapComplexity(c){
                const val = +c || 0;
                if (!val) return {lvl:null, label:'', class:''};
                // инвертируем: high при <= 600, mid при 600-1000, low при >1000
                if (val <= 600) return {lvl:'high', label:'высокая', class:'high'};
                if (val <= 1000) return {lvl:'mid', label:'средняя', class:'mid'};
                return {lvl:'low', label:'низкая', class:'low'};
            }
            const cx = mapComplexity(row.complexity);

            const topPx    = Math.round(topH * PX_PER_HOUR);
            let heightPx   = Math.max(18, Math.round(effH * PX_PER_HOUR));

            // определяем режимы компактности по фактическим часам
            const isTiny    = effH <= TINY_H;
            const isCompact = !isTiny && effH <= COMPACT_H;

            // гарантируем минимальную высоту карточки по режиму, чтобы текст не обрезался
            // tiny: только заголовок; compact: заголовок + короткая строка; normal: заголовок + субстрока
            const minTinyPx    = 22;  // шапка
            const minCompactPx = 28;  // шапка + компактная подстрока
            const minNormalPx  = 34;  // шапка + подстрока
            const needMinPx    = isTiny ? minTinyPx : (isCompact ? minCompactPx : minNormalPx);
            if (heightPx < needMinPx) heightPx = needMinPx;

            const ev = document.createElement('div');
            ev.className='event';
            if (isCompact) ev.classList.add('compact');
            if (isTiny)    ev.classList.add('tiny');

            ev.style.top = topPx+'px';
            ev.style.height = heightPx+'px';
            ev.draggable = true;

            // Применяем цветовую индикацию в зависимости от режима тепловой карты
            if (heatmapMode === 'complexity' && cx.lvl) {
                const color = cx.class==='high' ? '#ef4444' : (cx.class==='mid' ? '#f59e0b' : '#22c55e');
                ev.style.backgroundColor = color + '30'; // увеличиваем прозрачность для лучшей видимости
                ev.style.borderColor = color;
                ev.style.borderWidth = '2px';
            } else if (heatmapMode === 'heights' && row.height) {
                const heightColor = getHeightColor(row.height);
                ev.style.backgroundColor = heightColor + '60'; // еще меньше прозрачности для высот
                ev.style.borderColor = heightColor;
                ev.style.borderWidth = '2px';
            } else if (heatmapMode === 'none') {
                // Сбрасываем стили для режима "без карты"
                ev.style.backgroundColor = '';
                ev.style.borderColor = '';
                ev.style.borderWidth = '';
            }

            // показываем дату только если карточка не компактная
            const showDate = !(isTiny || isCompact);
            const dateHtml = showDate ? `<span class="muted">${escapeHtml(row.source_date)}</span>` : '';

            // подсказка с информацией о тепловой карте
            const hasValidHeight = (row.height != null && !isNaN(row.height));
            let tooltipText = `${row.filter}${hasValidHeight ? ` [${row.height} мм]` : ''}\n${row.count} шт • ~ ${fmt1(effH)} ч${row._fallback?'*':''}\n${row.source_date}`;
            
            if (heatmapMode === 'complexity' && cx.lvl) {
                tooltipText += `\nСложность: ${cx.label} (${row.complexity})`;
                // Добавляем высоту если она есть
                if (hasValidHeight) {
                    tooltipText += `\nВысота: ${row.height} мм`;
                }
            } else if (heatmapMode === 'heights' && hasValidHeight) {
                tooltipText += `\nВысота: ${row.height} мм`;
            } else if (heatmapMode === 'none') {
                // В режиме "без карты" показываем базовую информацию
                if (hasValidHeight) tooltipText += `\nВысота: ${row.height} мм`;
                if (cx.lvl) tooltipText += `\nСложность: ${cx.label} (${row.complexity})`;
            }
            
            ev.title = tooltipText;

            const complexityChip = cx.lvl
                ? `<span class="cx"><span class="dot" style="background:${cx.class==='high'?'#ef4444':(cx.class==='mid'?'#f59e0b':'#22c55e')}" title="Сложность: ${cx.label}"></span></span>`
                : '';
            const complexityDot = cx.lvl
                ? `<span class="cx"><span class="dot" style="background:${cx.class==='high'?'#ef4444':(cx.class==='mid'?'#f59e0b':'#22c55e')}" title="Сложность: ${cx.label}"></span></span>`
                : '';

            // Показываем высоту всегда, если она есть
            const showHeight = row.height != null;
            
            // Кружочек с высотой для заголовка
            const heightDot = (row.height != null && !isNaN(row.height)) 
                ? `<span class="height-dot" title="Высота: ${row.height} мм">${row.height}</span>` 
                : '';
            

            ev.innerHTML = `
                            <h4><span class="ttl">${escapeHtml(row.filter)}</span>${heightDot}${isTiny||isCompact?complexityDot:complexityChip}</h4>
                            <div class="sub">
                              <span>${row.count} шт</span>
                              <span>~ ${fmt1(effH)} ч${row._fallback?'*':''}</span>
                              ${dateHtml}
                            </div>
                          `;

            ev._row=row; ev._day=day; ev._team=team;
            ev.addEventListener('dragstart', ()=>ev.classList.add('dragging'));
            ev.addEventListener('dragend',   ()=>ev.classList.remove('dragging'));
            
            // Обработчик клика для режима разделения
            ev.addEventListener('click', (e) => {
                if (splitMode) {
                    e.preventDefault();
                    e.stopPropagation();
                    splitTargetEvent = ev;
                    splitModal.style.display = 'flex';
                }
            });
            // если фактическая высота < 1.5 часа — скрываем подстроку и переносим данные в title
            if (effH < 1.5){
                ev.classList.add('force-hide-sub');
                // расширенный title с переносами
                let cxText = '';
                if (heatmapMode === 'complexity' && cx.lvl) {
                    cxText = `\nСложность: ${cx.label} (${row.complexity})`;
                    // Добавляем высоту если она есть
                    if (hasValidHeight) {
                        cxText += `\nВысота: ${row.height} мм`;
                    }
                } else if (heatmapMode === 'heights' && hasValidHeight) {
                    cxText = `\nВысота: ${row.height} мм`;
                } else if (heatmapMode === 'none') {
                    if (hasValidHeight) cxText += `\nВысота: ${row.height} мм`;
                    if (cx.lvl) cxText += `\nСложность: ${cx.label} (${row.complexity})`;
                }
                ev.title = `${row.filter}${hasValidHeight ? ` [${row.height} мм]` : ''}${cxText}\n${row.count} шт • ~ ${fmt1(effH)} ч${row._fallback?'*':''}\n${row.source_date}`;
            }

            lane.appendChild(ev);
        }


        function refreshTotals(){
            for (let i = 0; i < spanDays; i++){
                const day = fmtDate(addDays(weekStart, i));
                const by  = plan.get(day) || {'1':[], '2':[]};

                const effSum = (team) => computeLaneLayout(day, team)
                    .reduce((s, r) => s + (r._effH || 0), 0);

                const t1 = by['1'].reduce((s,r)=> s + (r.count||0), 0);
                const t2 = by['2'].reduce((s,r)=> s + (r.count||0), 0);

                const myH1 = effSum('1');
                const myH2 = effSum('2');
                const busy1 = (busyHours.get(day)||{})['1'] || 0;
                const busy2 = (busyHours.get(day)||{})['2'] || 0;

                const h1 = myH1 + busy1;
                const h2 = myH2 + busy2;

                const col = weekGrid.querySelector(`.day[data-day="${CSS.escape(day)}"]`);
                if (!col) continue;

                col.querySelector(`.t1[data-t1="${CSS.escape(day)}"]`).textContent = String(t1);
                col.querySelector(`.t2[data-t2="${CSS.escape(day)}"]`).textContent = String(t2);
                col.querySelector(`.h1[data-h1="${CSS.escape(day)}"]`).textContent = fmt1(h1);
                col.querySelector(`.h2[data-h2="${CSS.escape(day)}"]`).textContent = fmt1(h2);

                const lane1 = col.querySelector(`.lane[data-team="1"]`);
                const lane2 = col.querySelector(`.lane[data-team="2"]`);

                const cap1 = cap('1');   // 11.5ч
                const cap2 = cap('2');   // 8ч

                lane1.classList.toggle('over', h1 > cap1 + 0.01);
                lane2.classList.toggle('over', h2 > cap2 + 0.01);
                
                // Применяем цветовую индикацию к чипам в заголовке дня
                const chip1 = col.querySelector('.chip.b1');
                const chip2 = col.querySelector('.chip.b2');
                
                if (heatmapMode === 'complexity') {
                    // Для режима сложности применяем цвет на основе средней сложности дня
                    const avgComplexity = calculateDayAverageComplexity(day);
                    if (avgComplexity) {
                        const color = avgComplexity.class === 'high' ? '#ef4444' : 
                                    (avgComplexity.class === 'mid' ? '#f59e0b' : '#22c55e');
                        if (chip1) {
                            chip1.style.backgroundColor = color + '30';
                            chip1.style.borderColor = color;
                            chip1.style.background = 'none'; // убираем базовый фон
                        }
                        if (chip2) {
                            chip2.style.backgroundColor = color + '30';
                            chip2.style.borderColor = color;
                            chip2.style.background = 'none'; // убираем базовый фон
                        }
                    }
                } else if (heatmapMode === 'heights') {
                    // Для режима высот применяем цвет на основе средней высоты дня
                    const avgHeight = calculateDayAverageHeight(day);
                    if (avgHeight) {
                        const heightColor = getHeightColor(avgHeight);
                        if (chip1) {
                            chip1.style.backgroundColor = heightColor + '60';
                            chip1.style.borderColor = heightColor;
                            chip1.style.background = 'none'; // убираем базовый фон
                        }
                        if (chip2) {
                            chip2.style.backgroundColor = heightColor + '60';
                            chip2.style.borderColor = heightColor;
                            chip2.style.background = 'none'; // убираем базовый фон
                        }
                    }
                } else if (heatmapMode === 'none') {
                    // Сбрасываем стили для режима "без карты"
                    if (chip1) {
                        chip1.style.backgroundColor = '';
                        chip1.style.borderColor = '';
                        chip1.style.background = ''; // восстанавливаем базовый фон
                    }
                    if (chip2) {
                        chip2.style.backgroundColor = '';
                        chip2.style.borderColor = '';
                        chip2.style.background = ''; // восстанавливаем базовый фон
                    }
                }
                
                // Обновляем индикаторы сложности для каждой смены
                updateShiftComplexityIndicators(day, col);
            }
        }


        // === I/O
        async function loadPlan(){
            console.log('loadPlan called, ORDER:', ORDER);
            try{
                const url = `${API}?action=load&order=`+encodeURIComponent(ORDER);
                console.log('Fetching URL:', url);
                const res = await fetch(url, {headers:{'Accept':'application/json'}});
                console.log('Response status:', res.status);
                const data = await res.json();
                console.log('Response data:', data);
                if (!data.ok) throw new Error(data.error||'load failed');

                plan.clear();
                // Очищаем историю разделений при загрузке плана
                splitHistory = [];
                undoSplitBtn.disabled = true;
                
                // Очищаем буфер при загрузке плана
                buffer = [];
                updateBufferDisplay();

                const need = [];
                Object.keys(data.plan||{}).forEach(day=>{
                    ['1','2'].forEach(team=>{
                        (data.plan[day][team]||[]).forEach(it=>{
                            need.push({day,team, source_date: it.source_date, filter: (it.filter || '').trim(), count: +it.count||0});
                        });
                    });
                });
                

                let metaMap = new Map();
                try{
                    const uniq = Array.from(new Set(need.map(x=>x.filter)));
                    const resMeta = await fetch(`${API}?action=meta`, {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({filters: uniq})
                    });
                    const dataMeta = await resMeta.json();
                    (dataMeta.items||[]).forEach(it=>{
                        const rawCx = (
                            it.build_complexity ?? it.complexity ?? it.buildComplexity ?? it.cx ?? it.rate ?? null
                        );
                        
                        const processedHeight = (it.height == null || it.height === '' || it.height === 0) ? null : +it.height;
                        
                        
                        metaMap.set(it.filter, {
                            rate: +it.rate||0,
                            height: processedHeight,
                            complexity: rawCx==null?null:+rawCx
                        });
                    });
                }catch(_){ metaMap = new Map(); }

                need.forEach(x=>{
                    const m = metaMap.get(x.filter) || {rate:0, height:null, complexity:null};
                    
                    
                    const rate = +m.rate || 0;
                    const rawH = rate>0 ? (x.count / rate) * SHIFT_H : 0;
                    const baseH = rawH>0 ? rawH : FALLBACK_SLOT_H; // зафиксировали!
                    const finalHeight = (m.height==null?null:+m.height);
                    
                    
                    ensureDay(x.day);
                    plan.get(x.day)[x.team].push({
                        source_date: x.source_date, filter: x.filter, count: x.count,
                        rate: rate, height: finalHeight,
                        complexity: (m.complexity==null?null:+m.complexity),
                        baseH: baseH, _fallback: (rawH<=0),
                        _originalBaseH: baseH  // сохраняем исходное время для защиты при переносах
                    });
                });

                renderWeek(false);
                
                // После загрузки плана обновляем состояние кнопки сохранения
                updateSaveButtonState();
                
                alert('Загружено.');
            }catch(e){
                alert('Ошибка загрузки: '+e.message);
            }
        }

        async function savePlan(){
            // Проверяем, что буфер пустой
            if (buffer.length > 0) {
                alert('Нельзя сохранить план пока в буфере есть позиции. Сначала разместите все позиции из буфера в план.');
                return;
            }

            const payload = {};
            plan.forEach((byTeam,day)=>{
                const t1 = (byTeam['1']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
                const t2 = (byTeam['2']||[]).map(x=>({source_date:x.source_date, filter:x.filter, count:x.count}));
                if (t1.length || t2.length) payload[day] = {'1':t1, '2':t2};
            });
            try{
                const res = await fetch(`${API}?action=save`, {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({order: ORDER, plan: payload})
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error||'save failed');
                alert('Сохранено.');
            }catch(e){
                alert('Ошибка сохранения: '+e.message);
            }
        }

        async function fetchBusy(days){
            try{
                const res = await fetch(`${API}?action=busy&order=`+encodeURIComponent(ORDER), {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({days})
                });
                const data = await res.json();
                if (!data.ok) return;
                busyHours.clear();
                (days||[]).forEach(d=>{
                    const v = (data.data||{})[d] || {1:0,2:0};
                    busyHours.set(d, {'1': +v[1]||0, '2': +v[2]||0});
                });
            }catch(e){ /* ignore */ }
        }
        
        // Инициализация состояния кнопки сохранения
        updateSaveButtonState();
        
    });
</script>
</html>
