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
            <th style=' border: 1px solid black'> Ширина шторы
            </th>              
            <th style=' border: 1px solid black'> Высота шторы
            </th>              
            <th style=' border: 1px solid black'> Высота ленты 
            </th>  
            <th style=' border: 1px solid black'> Поролон
            </th>                
            <th style=' border: 1px solid black'> Вставка
            </th>                                        
            <th style=' border: 1px solid black'> Язычек
            </th>                                       
            <th style=' border: 1px solid black'> Форм-фактор
            </th>               
            <th style=' border: 1px solid black'> Коробка
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
    $filter_data = get_salon_filter_data($row['filter']);
    echo "<tr style='hov'>"
        ."<td>".$row['filter']."</td>"
        ."<td>".$row['count']."</td>"
        ."<td>".$row['marking']."</td>"
        ."<td>".$row['personal_packaging']."</td>"
        ."<td>".$row['personal_label']."</td>"
        ."<td>".$row['group_packaging']."</td>"
        ."<td>".$row['packaging_rate']."</td>"
        ."<td>".$row['group_label']."</td>"
        ."<td>".$row['remark']."</td>"
        ."<td>".$filter_data['paper_package_width']."</td>"
        ."<td>".$filter_data['paper_package_height']."</td>"
        ."<td>".$filter_data['side_type']."</td>"
        ."<td>".$filter_data['foam_rubber']."</td>"
        ."<td>".$filter_data['insertion_count']."</td>"
        ."<td>".$filter_data['tail']."</td>"
        ."<td>".$filter_data['form_factor']."</td>"
        ."<td>".$filter_data['box']."</td>";



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
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
    ."<td></td>"
//    ."<td>".$filter_count_produced."</td>"
//    ."<td>".$summ_difference."</td>"
    ."</tr>";

echo "</table>";
echo '</form>';
