<?php /** show_order.php  файл отображает выбранную заявку в режиме просмотра*/

require_once('tools/tools.php');
require_once('settings.php');

require_once ('style/table.txt');


/** Номер заявки которую надо нарисовать */
$order_number = $_POST['order_number'];

/** Показываем номер заявки */
echo '<h3>Заявка:'.$order_number.'</h3><p>';

/** Формируем шапку таблицы для вывода заявки */
echo "<table style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>
        <tr>
            <th style=' border: 1px solid black'> №п/п
            </th>            
            <th style=' border: 1px solid black'> Фильтр
            </th>
            <th style=' border: 1px solid black'> Количество, шт           
            </th>
            <th style=' border: 1px solid black'> Маркировка           
            </th>
            <th style=' border: 1px solid black'> Упаковка инд.           
            </th>  
            <th style=' border: 1px solid black'> Этикетка инд.           
            </th>
            <th style=' border: 1px solid black'> Упаковка групп.           
            </th>
            <th style=' border: 1px solid black'> Норма упаковки           
            </th>
            <th style=' border: 1px solid black'> Этикетка групп.           
            </th>    
            <th style=' border: 1px solid black'> Примечание           
            </th>     
            <th style=' border: 1px solid black'> Изготовлено, шт
            </th>  
            <th style=' border: 1px solid black'> Остаток, шт
            </th>                                                       
        </tr>";

/** Загружаем из БД заявку */
$result = show_order($order_number);

/** Переменная для подсчета суммы фильтров в заявке */
$filter_count_in_order = 0;



/** Переменная для подсчета количества сделанных фильтров */
$filter_count_produced = 0;

/** strings counter */
$count =0;

echo '<form action="filter_parameters.php" method="post">';

/** Разбор массива значений по подключению */
while ($row = $result->fetch_assoc()){
    $difference = (int)$row['count']-(int)select_produced_filters_by_order($row['filter'],$order_number)[1];
    $difference_in_prcnt = round($difference / (int)$row['count'] * 100,0);
    $filter_count_in_order = $filter_count_in_order + (int)$row['count'] ;
    $filter_count_produced = $filter_count_produced + (int)select_produced_filters_by_order($row['filter'],$order_number)[1];
    $count += 1;
    echo "<tr style='hov'>"
        ."<td>".$count."</td>"
        //."<td><input type='submit' name='filter_name' value=".$row['filter']." style=\"height: 20px; width: 200px\">".$row['filter']."</td>"
        ."<td>".$row['filter']."</td>"
        ."<td>".$row['count']."</td>"
        ."<td>".$row['marking']."</td>"
        ."<td>".$row['personal_packaging']."</td>"
        ."<td>".$row['personal_label']."</td>"
        ."<td>".$row['group_packaging']."</td>"
        ."<td>".$row['packaging_rate']."</td>"
        ."<td>".$row['group_label']."</td>"
        ."<td>".$row['remark']."</td>"
        ."<td>".(int)select_produced_filters_by_order($row['filter'],$order_number)[1]."</td>";
    if (($difference < 75)AND($difference > 0)){
       // echo "<td bgcolor='yellow'>".$difference."</td></tr>";
        echo "<td>".$difference."</td></tr>";
    } elseif ($difference < 0){
       // echo "<td bgcolor='green'>".$difference."</td></tr>";
        echo "<td>".$difference."</td></tr>";
    }else{
        echo "<td>".$difference."</td></tr>";
    }

}

/** @var расчет оставшегося количества продукции для производства $summ_difference */
$summ_difference = $filter_count_in_order - $filter_count_produced;
echo "<tr style='hov'>"
    ."<td>Итого:</td>"
    ."<td></td>"
    ."<td>".$filter_count_in_order."</td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td>".$filter_count_produced."</td>"
    ."<td>".$summ_difference."</td>"
    ."</tr>";

echo "</table>";
echo '</form>';
//echo "Количество фильтров в заявке: ".$filter_count_in_order;
//echo "Количество фильтров изготовлено: ".$filter_count_produced;

/** Кнопка перехода в режим планирования для У2*/
echo "<br><form action='order_planning_U2.php' method='post'>"
    ."<input type='hidden' name='order_number' value='$order_number'>"
    ."<input type='submit' value='Режим простого планирования'>"
    ."</form>";


/** Кнопка сокрытия заявки*/
echo "<br><form action='hiding_order.php' method='post'>"
    ."<input type='hidden' name='order_number' value='$order_number'>"
    ."<input type='submit' value='Отправить заявку в архив'>"
    ."</form>";
