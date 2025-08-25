<?php
// NP/confirm_cut.php — утверждение раскроя (Salon)
session_start();

/* === DB === */
$dsn  = "mysql:host=127.0.0.1;dbname=plan_U5;charset=utf8mb4";
$user = "root";
$pass = "";
$CUT_PAGE = "../NP_cut_plan.php"; // куда вести «Открыть раскрой»

$pdo = new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* helpers */
function colExists(PDO $pdo, $table, $col){
    $st=$pdo->prepare("SELECT 1 FROM information_schema.columns
                       WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $st->execute([$table,$col]);
    return (bool)$st->fetchColumn();
}
function csrfToken(){
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function needParam($name){
    $v = $_GET[$name] ?? $_POST[$name] ?? '';
    if ($v==='') { http_response_code(400); exit("Укажите ?$name=..."); }
    return $v;
}

/* авто-миграция флагов утверждения */
if (!colExists($pdo,'orders','cut_confirmed')) {
    $pdo->exec("ALTER TABLE orders
        ADD cut_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        ADD cut_confirmed_at DATETIME NULL,
        ADD cut_confirmed_by VARCHAR(100) NULL,
        ADD cut_comment VARCHAR(255) NULL");
}

/* входные данные */
$order = needParam('order');

/* есть ли раскрой (без него утверждать нельзя) */
$planRows = (int)$pdo->prepare("SELECT COUNT(*) FROM cut_plans WHERE order_number=?")
    ->execute([$order]) ? (int)$pdo->query("SELECT COUNT(*) FROM cut_plans WHERE order_number=".$pdo->quote($order))->fetchColumn() : 0;

/* сводка по раскрою (для информации) */
$summary = [
    'bales'   => 0,
    'strips'  => 0,
    'meters'  => 0.0,
];
if ($planRows>0){
    $row = $pdo->query("
        SELECT COUNT(DISTINCT bale_id) AS bales,
               COUNT(*)                AS strips,
               ROUND(SUM(length),3)  AS meters
        FROM cut_plans
        WHERE order_number=".$pdo->quote($order)
    )->fetch();
    if ($row){ $summary = $row; }
}

/* текущий статус */
$st = $pdo->prepare("SELECT cut_confirmed
                     FROM orders WHERE order_number=?");
$st->execute([$order]);
$status = $st->fetch() ?: ['cut_confirmed'=>0,'cut_confirmed_at'=>null,'cut_confirmed_by'=>null,'cut_comment'=>null];

/* обработка POST */
$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (($_POST['csrf'] ?? '') !== csrfToken()) { http_response_code(419); exit('CSRF token'); }

    if (isset($_POST['action']) && $_POST['action']==='confirm') {
        if ($planRows<=0) {
            $msg = 'Нельзя утвердить: нет данных раскроя.';
        } else {
            $by   = trim($_POST['by'] ?? '');
            $comm = trim($_POST['comment'] ?? '');
            if ($by==='') $by = ($_SESSION['approver_name'] ?? 'оператор');
            $_SESSION['approver_name'] = $by;

            $u = $pdo->prepare("UPDATE orders
                                SET cut_confirmed=1
                                WHERE order_number=?");
            $u->execute([$order]);
            $msg = 'Утверждено.';
            // перечитать статус
            $st->execute([$order]);
            $status = $st->fetch();
            //переход на следующую страницу
            header("Location: ../NP_cut_index.php" );
            exit;
        }
    }
    if (isset($_POST['action']) && $_POST['action']==='unconfirm') {
        $u = $pdo->prepare("UPDATE orders
                            SET cut_confirmed=0
                            WHERE order_number=?");
        $u->execute([$order]);
        $msg = 'Утверждение снято.';
        $st->execute([$order]);
        $status = $st->fetch();
    }
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Утверждение раскроя — <?=htmlspecialchars($order)?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    :root{--bg:#f6f7fb;--card:#fff;--line:#e5e7eb;--text:#111827;--muted:#6b7280;--ok:#16a34a;--danger:#dc2626;--accent:#2563eb}
    *{box-sizing:border-box} body{margin:20px;background:var(--bg);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    .wrap{max-width:820px;margin:0 auto}
    .card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
    h2{margin:0 0 10px} .muted{color:var(--muted)}
    .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
    input[type=text]{padding:8px;border:1px solid var(--line);border-radius:8px;min-width:220px}
    textarea{padding:8px;border:1px solid var(--line);border-radius:8px;width:100%;height:70px}
    .btn{padding:8px 12px;border-radius:8px;border:1px solid transparent;color:#fff;background:var(--accent);cursor:pointer}
    .btn:hover{filter:brightness(.96)} .btn-outline{background:#eef2ff;color:var(--accent);border-color:#c7d2fe}
    .btn-danger{background:var(--danger)} .ok{color:var(--ok);font-weight:600}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{border:1px solid var(--line);padding:8px;text-align:left}
    .note{margin:10px 0;color:var(--muted)}
    .alert{margin:12px 0;padding:10px 12px;border:1px solid var(--line);background:#f8fafc;border-radius:8px}
</style>

<div class="wrap">
    <div class="card">
        <h2>Утверждение раскроя · заявка <?=htmlspecialchars($order)?></h2>
        <div class="note">
            <?php if ($status['cut_confirmed']): ?>
                <span class="ok">Статус: утверждено</span>

            <?php else: ?>
                Статус: <b>не утверждено</b>
            <?php endif; ?>
        </div>

        <table>
            <tr><th>Строк в раскрое</th><td><?=$planRows?></td></tr>
            <tr><th>Бухт</th><td><?= (int)$summary['bales'] ?></td></tr>
            <tr><th>Полос</th><td><?= (int)$summary['strips'] ?></td></tr>
            <tr><th>Суммарная длина, м</th><td><?= htmlspecialchars(number_format((float)$summary['meters'],3,'.',' ')) ?></td></tr>
        </table>

        <?php if ($msg): ?>
            <div class="alert"><?=htmlspecialchars($msg)?></div>
        <?php endif; ?>

        <form method="post" class="row" style="margin-top:12px">
            <input type="hidden" name="csrf" value="<?=csrfToken()?>">
            <input type="hidden" name="order" value="<?=htmlspecialchars($order, ENT_QUOTES)?>">
            <?php if (!$status['cut_confirmed']): ?>
            <button class="btn" name="action" value="confirm" <?= $planRows<=0 ? 'disabled' : '' ?>>Утвердить</button>
            <?php endif; ?>
            <?php if ($status['cut_confirmed']): ?>
                <button class="btn btn-danger" name="action" value="unconfirm" type="submit">Снять утверждение</button>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?=$CUT_PAGE?>?order_number=<?=urlencode($order)?>" target="_blank">Открыть раскрой</a>
        </form>


        <p class="note">Подтверждать можно только если в <code>cut_plan_salon</code> есть строки по этой заявке.</p>
    </div>
</div>
