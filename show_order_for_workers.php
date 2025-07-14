<!-- Спиннер (индикатор загрузки) -->
<div id="loader" style="
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 18px;
    color: #333;
    text-align: center;
">
    <div style="
        border: 6px solid #f3f3f3;
        border-top: 6px solid #c73434;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    "></div>
    Загрузка таблицы...
</div>

<!-- Таблица (по умолчанию скрыта) -->
<div id="tableContainer" style="display: none;">
    <?php
    /** Код генерации таблицы, который у тебя уже есть */
    ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Ждём полной загрузки страницы
        setTimeout(function() {
            document.getElementById("loader").style.display = "none"; // Скрываем спиннер
            document.getElementById("tableContainer").style.display = "block"; // Показываем таблицу
        }, 100); // Можно увеличить задержку, если нужно
    });
</script>

<style>
    /* Анимация вращения спиннера */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>



<button onclick="calculateBoxes()" style="
    background-color: #4CAF50;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
">
    Расчет коробок
</button>

<script>
    function calculateBoxes() {
        let table = document.querySelector("table"); // Получаем таблицу
        let rows = table.querySelectorAll("tr"); // Получаем все строки
        let boxCounts = {}; // Объект для хранения количества каждой коробки

        // Проходим по строкам, начиная со 2-й (0 - заголовок, 1 - "Итого")
        for (let i = 1; i < rows.length - 1; i++) {
            let cells = rows[i].querySelectorAll("td"); // Получаем все ячейки строки

            let count = parseInt(cells[1].innerText.trim()) || 0; // Количество фильтров
            let boxType = cells[cells.length - 1].innerText.trim(); // Название коробки

            if (boxType && count > 0) {
                boxCounts[boxType] = (boxCounts[boxType] || 0) + count;
            }
        }

        // Формируем стильную таблицу
        let result = `
        <html>
        <head>
            <title>Расчет коробок</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h3 { text-align: center; color: #333; }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
                    border-radius: 8px;
                    overflow: hidden;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 12px;
                    text-align: center;
                }
                th {
                    background-color: #4CAF50;
                    color: white;
                    font-size: 16px;
                }
                tr:nth-child(even) { background-color: #f2f2f2; }
                tr:hover { background-color: #ddd; }
            </style>
        </head>
        <body>
            <h3>Расчет коробок</h3>
            <table>
                <tr>
                    <th>Коробка</th>
                    <th>Количество</th>
                </tr>`;

        for (let box in boxCounts) {
            result += `<tr><td>${box}</td><td>${boxCounts[box]}</td></tr>`;
        }

        result += `
            </table>
        </body>
        </html>`;

        // Открываем новое окно и выводим данные
        let newWindow = window.open("", "_blank");
        newWindow.document.write(result);
    }
</script>




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
