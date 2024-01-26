
<html>
<head>
    <title>
        Plan system
    </title>
    <link rel="stylesheet" href="sheets.css">
</head>
<body>
<div class="center">
<?php
echo '<form action="enter.php" method="get">';
echo '<input  type="text" name="user_name" width="140" placeholder="user_name"/>';
echo '<input  type="password" name="user_pass" width="140" placeholder="user_pass"/>';
echo '<select name="workshop">';
#echo '<option value="ZU">Заготовительный участок</option>';
#echo '<option value="U1">Сборочный участок №1</option>';
#echo '<option value="U2">Сборочный участок №2</option>';
#echo '<option value="U3">Сборочный участок №3</option>';
#echo '<option value="U4">Сборочный участок №4</option>';
echo '<option value="U5">Сборочный участок №5</option>';
#echo '<option value="U6">Сборочный участок №6</option>';
echo '</select>';
echo '<input type="submit" value="           Вход в систему        "/>';
echo '</form>';
?>
    <p>

    <form method="post"  action="share_orders.php" name="workshop_too" value="U2">
        <button type="submit">.... Просмотр заявок У5 .... </button>
    </form>

</div>



</body>
</html>


