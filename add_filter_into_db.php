<?php /** add_filter_into_db.php в файле реализован механизм внесения в БД нового фильтра */

require_once('tools/tools.php');
require_once ('settings.php');

/**  получаем имя участка на котором производится фильтр*/
$workshop = $_POST['workshop'];
?>

<!DOCTYPE html>
<html lang="ru">
    <head><link rel="stylesheet" type="text/css" href="sheets.css"/>
    <title>Добавление фильтра в БД</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
<script type="text/javascript">
    function add_filter_to_db() {
        let xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState === 4 && this.status === 200) {
                document.getElementById("result").innerHTML = this.responseText;
            }
        };
        let request = "add_filter_into_db_processing.php?filter="+document.getElementById("filter").value
                                                     +"&workshop="+document.getElementById('workshop').value;
        xhttp.open("GET", request, true);
        xhttp.send();
    }
</script>

<div class="demo">
    <label for="workshop">Участок на котором выпускается фильтр:</label>
    <input type="text" name="workshop" id="workshop" value="<?php echo $workshop;?>" size="5" style="text-align: center" readonly>
    <br>
    <label for="filter">Наименование фильтра:</label>
    <input type="text" name="filter" id="filter" value="" size="6" style="text-align: center" placeholder="AF****">
    <input type="button" name="add_button" id="add_button" value="Добавить в БД" onclick="add_filter_to_db()">
</div>
<div class="demo">
    <label>Результат:</label><p id="result"></p>
</div>
<div class="back">
    <a class="a" href='enter.php'>назад</a>
</div>

<?php
/** Создаем список фильтров уже внесенных в базу */
/** Подключаемся к БД */
$mysqli = new mysqli($mysql_host, $mysql_user, $mysql_user_pass, $mysql_database);

/** Если не получилось подключиться */
if ($mysqli->connect_errno) {  echo "Номер ошибки: " . $mysqli->connect_errno . "\n". "Ошибка: " . $mysqli->connect_error . "\n";
    exit;
}

/** Выполняем запрос SQL */

$sql = "SELECT * FROM filters WHERE workshop = '$workshop' ORDER BY filter ASC;";

/** Если запрос вернет ошибку */
if (!$result = $mysqli->query($sql)) {  echo "Номер ошибки: " . $mysqli->errno . "\n" . "Ошибка: " . $mysqli->error . "\n";
    exit;
}
echo "<div style=\"text-align: center;\"> Фильтры которые производятся на ".$workshop."<br>";
echo "<select size='30' style='width: 375px'>";
/** разбираем результат */
/** извлечение ассоциативного массива */
while ($row = mysqli_fetch_assoc($result)) {
   // printf ($row['filter']."<br>");
    $out_text = "<option>".$row['filter']."</option>";
     echo $out_text;
}
echo "</select></div>";
?>

<?php require_once('footer.php');?>