<?php
require_once ('tools/tools.php');
require_once ('settings.php');

set_time_limit(600);

class Planned_order
/** Класс реализует планирование заявки и хранение всех данных при поанировании и сохранение вего в БД
 * - хранение изначальной заявки
 * - создание раскроев бухт
 * - хранение раскроев бухт-------------?
 * - хранение плана гофрирования--------?
 * -хранение плана сборки---------------?
 * -Расчет комплектующих:
 *      каркасов;
 *      предфильтров;
 * ...
*/
{
    /** СВОЙСТВА КЛАССА */

    /** переменная хранит заявку
     *  $initial_order = {filter, count, ppHeight, ppWidth, ppPleats_count, count_of_pp_per_roll} */
   private $initial_order = array();
    /** переменная хранит номер заявки */
   public $order_name;
   /** @var int  $collection_marker - признак собираемости рулона */
   public $collection_marker = 0;
   /** обработанный массив для раскроя */
    private $cut_array_simple = array();
    public $cut_array_carbon = array();
    private $test_cut_array = array();
    /** массив собраных бухт */
    private $completed_rolls = array();
    /** маркер выполнения раскроя */
    private $cut_marker = false;
    /** массив не вошедших в первичный раскрой рулонов */
    private $not_cut_rolls = array();
    /** диапазон выбранных по длине рулонов  */
    private $diapazon = array();

    /**  $max_width_of_roll -  */



   /** МЕТОДЫ КЛАССА*/

    /** сохраняем в объект заявку
     * @param $order_array
     */
   public function set_order($order_array){
        $this->initial_order = $order_array;
   }

    /** задаем имя заявки
     * @param $name
     */
    public function set_name($name){
        $this->order_name = $name;
    }

    /** проверяем все ли фильтры из заявки ест у нас в БД и возвращает названия фильтров, которых нет  в БД
     * и рисует список отстутствующих фильтров
     */
    public function check_for_new_filters():array {
        $order = $this->initial_order;
        /** @var  $not_exist_filters  - массив фильтров, отсутствующих в БД*/
        $not_exist_filters = array();
        /** проходим по каждой позиции в заявке */
        for($x = 0; $x < sizeof($order); $x++ ){
            /** проверяем есть ли фильтр в БД и если нет, то добавляем его в массив с отсутствующими фильтрами */
            if (check_filter($order[$x][0]) != true){
                array_push($not_exist_filters, $order[$x][0]);
            }
        }
        /** блок отрисовки списка  */

        for ($x = 0; $x < sizeof($not_exist_filters); $x++){
            echo "<p> В БД ОТСУТСТВУЕТ ФИЛЬТР:<br>";
            echo '<form action="add_salon_filter_into_db.php" method="post" target="_blank">';
            echo '<input type="hidden" name="workshop" value="U5">';
            echo '<input type="hidden" name="filter_name" value="'.$not_exist_filters[$x].'">';
            echo $not_exist_filters[$x]."==>";
            load_filters_into_select();
            echo '<input type="submit" value="Добавить в БД"><br>';
            echo '</form>';
        }

        return $not_exist_filters;
    }

    /** получаем параметры гофропакетов
     * @param $roll_length
     */
    public function get_data_for_cutting($roll_length){
        /** в цикле перебираем массив заявки, извлекаем номер фильтра, по номеру делаем выборку и получаем параметры г/пакета */
        for ($x = 0; $x < sizeof($this->initial_order); $x++){
            /** определяем имя фильтра в данной записи */
            $pp_name = 'гофропакет '.$this->initial_order[$x][0];
            /** делаем запрос к БД на выборку параметров г/пакетов */
            $result = mysql_execute("SELECT * FROM paper_package_salon WHERE p_p_name = '$pp_name'");
            if ($result->num_rows === 0) {
                echo "Данные для расчета заявки не полные. Расчет остановлен.
                       Не полные данные на фильтр $pp_name";
                exit();
            }
            $row = $result->fetch_assoc();
            /** получаем высоту */
            $pp_height = $row['p_p_height'];
            /** получаем ширину */
            $pp_width = $row['p_p_width'];
            /** получаем количество ребер */
            $pp_pleats_count = $row['p_p_pleats_count'];
            /** получаем материал */
            $p_p_material = $row['p_p_material'];

            /** вычисляем необходимую длину полос*/
            $length_of_roll = $pp_height * $pp_pleats_count*2;
            $required_length_of_roll = round($this->initial_order[$x][1] * $pp_height* 2 * $pp_pleats_count);

            /** добавлем в массив заявки высоту г/пакета */
            array_push($this->initial_order[$x], $pp_height);
            /** добавлем в массив заявки ширину г/пакета */
            array_push($this->initial_order[$x], $pp_width);
            /** добавлем в массив заявки количество ребер г/пакета */
            array_push($this->initial_order[$x], $pp_pleats_count);
            /** Добавляем в массив заявки длину шторы одного фильтра */
            array_push($this->initial_order[$x], $length_of_roll);
            /** ДОбавляяем в массив длину рулона необходимую */
            array_push($this->initial_order[$x], $required_length_of_roll);
            /** ДОбавляяем в массив длину рулона необходимую */
            array_push($this->initial_order[$x], $p_p_material);
        }
    }

    /** Инициируем массив для формирования раскроев
     * конструкция массива $cut_array{filter, pp_height, pp_width} */



    public function cut_arrays_init(){

        for ($x = 0; $x < sizeof($this->initial_order); $x++){
            $temp = array();
            array_push($temp, $this->initial_order[$x][0]);
            array_push($temp, $this->initial_order[$x][2]);
            array_push($temp, $this->initial_order[$x][3]);
            array_push($temp, $this->initial_order[$x][6]/1000);
            array_push($temp, $this->initial_order[$x][7]);
            if ($this->initial_order[$x][7] != 'Carbon'){
                array_push($this->cut_array_simple,  $temp);
            } else {
                array_push($this->cut_array_carbon,  $temp);
            }
        }
    }

    /** отрисовка заявки */
    public function show_order(){

        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='9' style='background-color: #ff6200; text-align: center; color: white'>СЕРВИСНАЯ ИНФОРМАЦИЯ: РАСЧЕТ ДЛИН ШТОРЫ</td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Кол-во в заказе</td><td style=' border: 1px solid black'>Высота г/п, мм</td><td style=' border: 1px solid black'>Ширина г/п, мм</td><td style=' border: 1px solid black'>Число ребер, шт</td><td style=' border: 1px solid black'>Длина одного гофропакета, м</td><td style=' border: 1px solid black'>Необходимая длина рулона, м</td><td style=' border: 1px solid black'>Материал</td></tr>";
        for ($x = 0; $x < (sizeof($this->initial_order)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->initial_order[$x][0]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->initial_order[$x][1]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->initial_order[$x][2]."</td>";
            echo "<td style=' border: 1px solid black'>".str_replace(".",",",$this->initial_order[$x][3])."</td>";
            echo "<td style=' border: 1px solid black'>".$this->initial_order[$x][4]."</td>";
            echo "<td style=' border: 1px solid black'>".str_replace(".",",",$this->initial_order[$x][5]/1000)."</td>";
            echo "<td style=' border: 1px solid black'>".str_replace(".",",",$this->initial_order[$x][6]/1000)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->initial_order[$x][7]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";

    }



    /** отрисовка cut-массива для простого материала*/
    public function show_cut_array_simple(){

        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='5' style='background-color: #ff6200; text-align: center; color: white'>СЕРВИСНАЯ ИНФОРМАЦИЯ: МАССИВ ДЛЯ РАСЧЕТА ПРОСТОГО МАТЕРИАЛА</td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Высота г/п</td><td style=' border: 1px solid black'>Ширина г/п</td><td style=' border: 1px solid black'>Длина полосы, м</td>";
        for ($x = 0; $x < (sizeof($this->cut_array_simple)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_simple[$x][0]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_simple[$x][1]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_simple[$x][2]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_simple[$x][3]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";
    }


    /** отрисовка cut-массива для угольного материала*/
    public function show_cut_array_carbon(){

        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='5' style='background-color: #ff6200; text-align: center; color: white'>СЕРВИСНАЯ ИНФОРМАЦИЯ: МАССИВ ДЛЯ РАСЧЕТА УГОЛЬНОГО МАТЕРИАЛА</td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Высота г/п</td><td style=' border: 1px solid black'>Ширина г/п</td><td style=' border: 1px solid black'>Длина полосы, м</td>";
        for ($x = 0; $x < (sizeof($this->cut_array_carbon)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_carbon[$x][0]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_carbon[$x][1]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_carbon[$x][2]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->cut_array_carbon[$x][3]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";
    }
    /** отрисовка диапазона длин */
    public function show_diapazon_for_carbon($remark){
        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='5' style='background-color: #ff6200; text-align: center; color: white'>ДИАПАЗОН ДЛИН ".$remark."</td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Высота г/п</td><td style=' border: 1px solid black'>Ширина г/п</td><td style=' border: 1px solid black'>Длина полосы, м</td>";
        for ($x = 0; $x < (sizeof($this->diapazon)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][0]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][1]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][2]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][3]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";
    }

    /** отрисовка диапазона длин */
    public function show_diapazon_for_simple($remark){
        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='5' style='background-color: #ff6200; text-align: center; color: white'>ДИАПАЗОН ДЛИН ".$remark."</td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Высота г/п</td><td style=' border: 1px solid black'>Ширина г/п</td><td style=' border: 1px solid black'>Длина полосы, м</td>";
        for ($x = 0; $x < (sizeof($this->diapazon)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][0]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][1]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][2]."</td>";
            echo "<td style=' border: 1px solid black'>".$this->diapazon[$x][3]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";
    }

    /** отрисовка собранных бухт */
    public function show_completed_rolls_for_carbon(){        //собираем статистику по сформированным рулонам
        $statistic_completed_rolls_count= 0;
        for ($x=0; $x < sizeof($this->completed_rolls); $x++){
            $statistic_completed_rolls_count = $statistic_completed_rolls_count + sizeof($this->completed_rolls[$x]);
        }
        echo "<hr>";
        echo "В раскрой было добавлено ".$statistic_completed_rolls_count." рулон(ов)<p>";


        //Ограничиваем блок для печати
        echo "<div id='print-content'><p>";

        //Выводим таблицы с раскроями
        $roll_initial_width = 1000;                                                //поменять на $width_of_main_roll
        for($x = 0; $x < sizeof($this->completed_rolls); $x++){
            $test = sizeof($this->completed_rolls);

            /** Для каждой бухты: */
            $test_array = $this->completed_rolls[$x];

            /** Определяем длину каждой бухты */
            /** ------------------------------- Блок отрисовки одной бухты ----------------------------------*/
            echo "№".$x."<br>";
            echo "<table style='border-collapse: collapse' '>";
            /** Считаем остаток */
            $ostatok = 0;
            for($y = 0; $y < sizeof($test_array); $y++){
                $ostatok += $test_array[$y][2];
            }
            $ostatok = $roll_initial_width - $ostatok;
            $length_of_roll = $test_array[0][3];

            /** Заносим в талицу валки */
            echo "<tr>";
            echo "<td style='font-size:13pt; border: 1px solid black' colspan='".(sizeof($test_array)+1)."'>[Бухта ".$roll_initial_width." мм] [н/т полотно Carbon] [остаток = ".$ostatok." мм]"." [длина = ".$length_of_roll." м] </td>";
            echo "</tr>";
            echo "<tr>";
            for($y = 0; $y < sizeof($test_array);$y++){
                /** Высчитываем ширину рулона в масштабе 1/2 */
                $roll_size = $test_array[$y][2]/1.5;
                echo "<td width=".$roll_size." style='font-size:12pt; border: 1px solid black' >";
                echo $test_array[$y][0]."<br>";
                echo "h=".$test_array[$y][1]."<br>";
                echo "<b>".$test_array[$y][2]."</b> мм<br>";
                //echo "(".$test_array[$y][3]." м)<br>";
                echo "</td>";
            }
            echo "<td width='.$ostatok.' style='font-size:9pt; border: 1px solid black; background-color: #ababab'> </td>";
            echo "</tr>";
            echo "</table><p>";
            /** ---------------------------------------------------------------------------------------------- */
        }

        //Ограничиваем блок для печати
        echo "</div><p>";

        //Кнопка печати раскроев
        echo "<button onclick='CallPrint();'>Распечатать задание в порезку</button><p>";

    }

    /** отрисовка собранных бухт */
    public function show_completed_rolls_for_simple(){        //собираем статистику по сформированным рулонам
        $statistic_completed_rolls_count= 0;
        for ($x=0; $x < sizeof($this->completed_rolls); $x++){
            $statistic_completed_rolls_count = $statistic_completed_rolls_count + sizeof($this->completed_rolls[$x]);
        }
        echo "<hr>";
        echo "В раскрой было добавлено ".$statistic_completed_rolls_count." рулон(ов)<p>";


        //Ограничиваем блок для печати
        echo "<div id='print-content1'><p>";

        //Выводим таблицы с раскроями
        $roll_initial_width = 1000;                                                //поменять на $width_of_main_roll
        for($x = 0; $x < sizeof($this->completed_rolls); $x++){
            $test = sizeof($this->completed_rolls);

            /** Для каждой бухты: */
            $test_array = $this->completed_rolls[$x];

            /** Определяем длину каждой бухты */
            /** ------------------------------- Блок отрисовки одной бухты ----------------------------------*/
            echo "№".$x."<br>";
            echo "<table style='border-collapse: collapse' '>";
            /** Считаем остаток */
            $ostatok = 0;
            for($y = 0; $y < sizeof($test_array); $y++){
                $ostatok += $test_array[$y][2];
            }
            $ostatok = $roll_initial_width - $ostatok;
            $length_of_roll = $test_array[0][3];

            /** Заносим в талицу валки */
            echo "<tr>";
            echo "<td style='font-size:13pt; border: 1px solid black' colspan='".(sizeof($test_array)+1)."'>[Бухта ".$roll_initial_width." мм] [н/т полотно ] [остаток = ".$ostatok." мм]"." [длина = ".$length_of_roll." м] </td>";
            echo "</tr>";
            echo "<tr>";
            for($y = 0; $y < sizeof($test_array);$y++){
                /** Высчитываем ширину рулона в масштабе 1/2 */
                $roll_size = $test_array[$y][2]/1.5;
                echo "<td width=".$roll_size." style='font-size:12pt; border: 1px solid black' >";
                echo $test_array[$y][0]."<br>";
                echo "h=".$test_array[$y][1]."<br>";
                echo "<b>".$test_array[$y][2]."</b> мм<br>";
                //echo "(".$test_array[$y][3]." м)<br>";
                echo "</td>";
            }
            echo "<td width='.$ostatok.' style='font-size:9pt; border: 1px solid black; background-color: #ababab'> </td>";
            echo "</tr>";
            echo "</table><p>";
            /** ---------------------------------------------------------------------------------------------- */
        }

        //Ограничиваем блок для печати
        echo "</div><p>";

        //Кнопка печати раскроев
        echo "<button onclick='CallPrint();'>Распечатать задание в порезку</button><p>";

    }


    public function show_completed_roll($completed_roll_array){
        echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
        echo "<tr><td colspan='5' style='background-color: #ff6200; text-align: center; color: white'> ПОПАВШИЕ В РАСКРОЙ РУЛОНЫ </td></tr>";
        echo "<tr><td style=' border: 1px solid black'>№ п/п</td><td style=' border: 1px solid black'>Фильтр</td><td style=' border: 1px solid black'>Высота г/п</td><td style=' border: 1px solid black'>Ширина г/п</td><td style=' border: 1px solid black'>Длина полосы, м</td>";
        for ($x = 0; $x < (sizeof($completed_roll_array)); $x++){
            echo "<tr>";
            echo "<td style=' border: 1px solid black; background-color: cornflowerblue'>".($x+1)."</td>";
            echo "<td style=' border: 1px solid black; background-color: cornflowerblue'>".$completed_roll_array[$x][0]."</td>";
            echo "<td style=' border: 1px solid black; background-color: cornflowerblue'>".$completed_roll_array[$x][1]."</td>";
            echo "<td style=' border: 1px solid black; background-color: cornflowerblue'>".$completed_roll_array[$x][2]."</td>";
            echo "<td style=' border: 1px solid black; background-color: cornflowerblue'>".$completed_roll_array[$x][3]."</td>";
            echo "</tr>";
        }
        echo "";
        echo "</table>";
    }



    


    /** Определение рулона минимальной ширины в cut_array */
    function min_roll_search(){
        $roll = 10000;
        for ($x=0; $x < sizeof($this->cut_array_simple); $x++){
            if ($roll > $this->cut_array_simple[$x][2]){
                //$temp = $this->cut_array[$x][2];
                $roll = $this->cut_array_simple[$x][2];
            }
        }
        return $roll;
    }

    /** соритровка cut_array массива по убыванию длины полос*/
    public function sort_cut_arrays(){
        /** сортируем массиві для порезки по убіванию длині полос */
        usort($this->cut_array_carbon, function($a, $b){
            return ($b[3] - $a[3]);
        });
        usort($this->cut_array_simple, function($a, $b){
            return ($b[3] - $a[3]);
        });
    }


    /**                         O                          */
    /**                         O                          */
    /**                         O                          */
    /**                         .                          */


    /**Функция расширения диапазона и инициализации его  */
    public function extension_of_diapazon_for_carbon(){
        //echo "<p>trying something1";
        /** сортировка массива с полосами по убыванию длины*/
        $this->sort_cut_arrays();
        /** Проверяем есть в массиве cut_array_carbon полосы для добавления в диапазон */
        if (count($this->cut_array_carbon) == 0){ // если нет - возвращаем 0 и выходим из процедуры
           // echo "<p>extension_of_diapazon return 0<p>";
            return 0;
        }
        /**  определяем длину самой длинной полосы из оставшихся в массиве cut_array*/
        $length_of_longest_strip = $this->cut_array_carbon[0][3];
        /** Если в диапазоне есть уже (или еще) позиции то делим их , если диапазон пуст то просто используем
         * эту функцию как функцию инициализации диапазона */
        if (sizeof($this->diapazon) != 0){
            /**разбиваем полосы в массиве диапазона на части равные length_of_longest_strip + остаток:*/
            for ($x = 0;$x < sizeof($this->diapazon);$x++){
                /** проверяем сколько полос получится при делении */
                /** если 1 */
                if ((($this->diapazon[$x][3]/$length_of_longest_strip) >= 1)AND(($this->diapazon[$x][3]/$length_of_longest_strip) < 2)){
                    /** находим остаток */
                    $ostatok = $this->diapazon[$x][3]-$length_of_longest_strip;
                    //echo "Остаток= ".$ostatok;
                    /**  оставляем кусок в массиве равный length_of_longest_strip  */
                    $this->diapazon[$x][3]= $length_of_longest_strip;
                    /** создаем массив для добавления в cut_array_carbon */
                    $ostatok_array = array();
                    /** добавляем в него данные */
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $ostatok); //length
                    /** добавляем остаток в массив cut_array_carbon  */
                    array_push($this->cut_array_carbon, $ostatok_array);

                } else{
                    echo "<p> НЕ ОДНА ПОЛОСА";
                    /** если больше */
                    /** определяем количество кусков  в массиве равные length_of_longest_strip */
                    $count_pieces = floor($this->diapazon[$x][3] / $length_of_longest_strip);
                    /** находим остаток */
                    $ostatok = $this->diapazon[$x][3]-$length_of_longest_strip*$count_pieces;
                    /** меняем значение длины в этом куске */
                    $this->diapazon[$x][3] =  $length_of_longest_strip;
                    /** создаем массив для добавления в cut_array_carbon */
                    $ostatok_array = array();
                    /** добавляем в него данные */
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $ostatok); //length
                    /** добавляем остаток в массив cut_array_carbon */
                    array_push($this->cut_array_simple, $ostatok_array);
                    /** добавляем остальные такие же куски в cut_array_simple */
                    $ostatok_array = array();
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $length_of_longest_strip); //length
                    for ($z=0; $z < $count_pieces - 1 ; $z++){
                        array_push($this->diapazon, $ostatok_array);
                    }
                }
            }
        }
        /** Необходимо забрать полосы которые попадают теперь в диапазон  */
        /** Сортируем раскроенные массивы снова */
        $this->sort_cut_arrays();
        /** @var  $longest_strip - Длина наибольшей полосы в списке полос */
        $longest_strip = $this->cut_array_carbon[0][3];
        /** @var  $min_range_of_diapazon - нихняя граница диапазона */
        $min_range_of_diapazon = $longest_strip * 0.8;
        $checking = false;
        $counter = 0;
        //echo "<p>trying something2";
        while ($checking != true){
            if ($this->cut_array_carbon[$counter][3] >= $min_range_of_diapazon){
                array_push($this->diapazon, $this->cut_array_carbon[$counter]); // дописываем элемент в диапазон
                if ($counter == sizeof($this->cut_array_carbon)-1){ // если это последняя строка ставим маркер - закончить
                    $checking = true;
                }
                array_splice($this->cut_array_carbon,$counter,1); // и удаляем его из массива катэррей
            }else{
                if ($counter == sizeof($this->cut_array_carbon)-1){ // если это последняя строка ставим маркер - закончить
                    $checking = true;
                }
                $counter++;
            }
        }
       // echo "success";
        return 1; // возвращаем 1 если смогли расширить диапазон
    }

    /**Функция расширения диапазона и инициализации его  */
    public function extension_of_diapazon_for_simple(){
        $this->show_diapazon_for_simple('ДО ДОБАВЛЕНИЯ ПОЛОС');
        /** сортировка массива с полосами по убыванию длины*/
        $this->sort_cut_arrays();
        /** Проверяем есть в массиве cut_array_simple полосы для добавления в диапазон */
        if (count($this->cut_array_simple) == 0){ // если нет - возвращаем 0 и выходим из процедуры
            //$this->show_diapazon_for_simple('ПОСЛЕ  ДОБАВЛЕНИЯ');
            return 0; // ни чего не добавлено так как нет  полос в массиве
        }
        /**  определяем длину самой длинной полосы из оставшихся в массиве cut_array*/
        $length_of_longest_strip = $this->cut_array_simple[0][3];
        /** Если в диапазоне есть уже (или еще) позиции то делим их , если диапазон пуст то просто используем
         * эту функцию как функцию инициализации диапазона */
        if (sizeof($this->diapazon) != 0){ // Если в диапазоне есть позиции то делим их
            /**разбиваем полосы в массиве диапазона на части равные length_of_longest_strip + остаток:*/
            for ($x = 0;$x < sizeof($this->diapazon);$x++){
                /** проверяем сколько полос получится при делении */
                /** если 1 */
                if ((($this->diapazon[$x][3]/$length_of_longest_strip) >= 1)AND(($this->diapazon[$x][3]/$length_of_longest_strip) < 2)){
                    //echo "<p> ОДНА ПОЛОСА";
                    /** находим остаток */
                    $ostatok = $this->diapazon[$x][3]-$length_of_longest_strip;
                    /**  оставляем кусок в массиве равный length_of_longest_strip  */
                    $this->diapazon[$x][3]= $length_of_longest_strip;
                    /** создаем массив для добавления в cut_array_carbon */
                    $ostatok_array = array();
                    /** добавляем в него данные */
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $ostatok); //length
                    /** добавляем остаток в массив cut_array_carbon  */
                    array_push($this->cut_array_simple, $ostatok_array);

                } else{
                   // echo "<p> НЕ ОДНА ПОЛОСА";
                    /** если больше */
                    /** определяем количество кусков  в массиве равные length_of_longest_strip */
                    $count_pieces = floor($this->diapazon[$x][3] / $length_of_longest_strip);
                    /** находим остаток */
                    $ostatok = $this->diapazon[$x][3]-$length_of_longest_strip*$count_pieces;
                    /** меняем значение длины в этом куске */
                    $this->diapazon[$x][3] =  $length_of_longest_strip;
                    /** создаем массив для добавления в cut_array_carbon */
                    $ostatok_array = array();
                    /** добавляем в него данные */
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $ostatok); //length
                    /** добавляем остаток в массив cut_array_carbon */
                    array_push($this->cut_array_simple, $ostatok_array);
                    /** добавляем остальные такие же куски в cut_array_simple */
                    $ostatok_array = array();
                    array_push($ostatok_array, $this->diapazon[$x][0]); //filter
                    array_push($ostatok_array, $this->diapazon[$x][1]); //height
                    array_push($ostatok_array, $this->diapazon[$x][2]); //width
                    array_push($ostatok_array, $length_of_longest_strip); //length
                    for ($z=0; $z < $count_pieces - 1 ; $z++){
                        array_push($this->diapazon, $ostatok_array);
                    }
                }
            }
        }
        /** Необходимо забрать полосы которые попадают теперь в диапазон  */
        /** Сортируем раскроенные массивы снова */
        $this->sort_cut_arrays();
        /** @var  $longest_strip - Длина наибольшей полосы в списке полос */
        $longest_strip = $this->cut_array_simple[0][3];
        /** @var  $min_range_of_diapazon - нихняя граница диапазона */
        $min_range_of_diapazon = $longest_strip * 0.8;
        $checking = false;
        $counter = 0;
        //echo "<p>trying something2";
        while ($checking != true){
            if ($this->cut_array_simple[$counter][3] >= $min_range_of_diapazon){
                array_push($this->diapazon, $this->cut_array_simple[$counter]); // дописываем элемент в диапазон
                if ($counter == sizeof($this->cut_array_simple)-1){ // если это последняя строка ставим маркер - закончить
                    $checking = true;
                }
                array_splice($this->cut_array_simple,$counter,1); // и удаляем его из массива катэррей
            }else{
                if ($counter == sizeof($this->cut_array_simple)-1){ // если это последняя строка ставим маркер - закончить
                    $checking = true;
                }
                $counter++;
            }
        }
        //$this->show_diapazon_for_simple('КОНЕЦ ДОБАВЛЕНИЯ');
        return 1; // возвращаем 1 если смогли расширить диапазон
    }

    /** очистка диапазона */
    function clear_the_diapazon(){
        for ($x = sizeof($this->diapazon)-1; $x >=0 ; $x--){
            array_splice($this->diapazon,$x,1);
        }
    }


    /** Определяем достаточно ли ширины диапазона чтобы собрать бухту */
    /** Функция возвращает один если 1 ширины и 0 если ширины не достаточно */
    function check_wide_of_diapazon_for_carbon(){
        /** обнуляем переменную суммы ширины */
        $full_wide_of_diapazon = 0;
        /** Запускаем цикл подсчета всех ширин полос в диапазоне */
        for ($x = 0; $x < sizeof($this->diapazon)-1; $x++){
            $full_wide_of_diapazon = $full_wide_of_diapazon + $this->diapazon[$x][2];
        }
        /** Сравниваем достаточно ли ширины диапазона для сборки бухты */
        $max_wide_of_roll =1000 - 15;                  // переписать чтобю подтягивало значения из файла настроек
        if ($full_wide_of_diapazon <= $max_wide_of_roll){
            //echo "Ширина диапазона =".$full_wide_of_diapazon."єтого не достаточно";
            return 0; // не достаточно ширины - надо расширять диапазон
        }
        //echo "Ширина диапазона =".$full_wide_of_diapazon."єтого достаточно";
        return 1; // достаточно ширины - не надо расширять диапазон
    }

    /** Определяем достаточно ли ширины диапазона чтобы собрать бухту */
    /** Функция возвращает один если 1 ширины и 0 если ширины не достаточно */
    function check_wide_of_diapazon_for_simple(){
        /** обнуляем переменную суммы ширины */
        $full_wide_of_diapazon = 0;
        /** Запускаем цикл подсчета всех ширин полос в диапазоне */
        for ($x = 0; $x < sizeof($this->diapazon)-1; $x++){
            $full_wide_of_diapazon = $full_wide_of_diapazon + $this->diapazon[$x][2];
        }
        /** Сравниваем достаточно ли ширины диапазона для сборки бухты */
        $max_wide_of_roll =1000 - 15;                  // переписать чтобю подтягивало значения из файла настроек
        if ($full_wide_of_diapazon <= $max_wide_of_roll){
            //echo "Ширина диапазона =".$full_wide_of_diapazon."єтого не достаточно";
            //echo "<p> НЕ ДОСТАТОЧНО";
            return 0; // не достаточно ширины - надо расширять диапазон
        }
        //echo "Ширина диапазона =".$full_wide_of_diapazon."єтого достаточно";
        return 1; // достаточно ширины - не надо расширять диапазон
    }

    /** Функция раскроя для диапазона */
    public function cut_execute_for_diapazone_for_carbone ($width_of_main_roll, $max_gap, $min_gap, $print){
        /** на входе получаем массив с N элементов */

        /** @var  $completed_roll - массив для позиций собранной бухты */
        $completed_roll = array();

        /** @var  $temp_diapazone - временный массив для переноса обновленных значений диапазона */
        $temp_diapazone = array();

        /** @var  $min_width_of_roll - минимальная используемая ширина рулона, например: 945 = 1000 - 55(максимально-допустимый отход) */
        $min_width_of_roll = $width_of_main_roll - $max_gap;

        /** @var  $max_width_of_roll - минимальная используемая ширина рулона, например: 945 = 1000 - 15(минимально-допустимый отход) */
        $max_width_of_roll = $width_of_main_roll - $min_gap;

        $amount_of_elements = sizeof($this->diapazon);
        $amount_of_variants = pow(2,$amount_of_elements)-1;

        /** считаем возможные варианты сумм элементов массива, представив счетчик в двоичном наборе битов */
        for ($x=1; $x <=$amount_of_variants; $x++){ // 0 не считаем так как там все равно 0
            $counter_to_bin=str_pad( decbin($x), $amount_of_elements, '0', STR_PAD_LEFT);
            $summ = 0;
            /** цикл подсчета суммы ширины полос для текущей комбинации */
            for ($z = 0; $z <$amount_of_elements; $z++){
                $summ = $summ + $this->diapazon[$z][2]*str_split($counter_to_bin)[$z];
            }

            /** проверяем сумму, входит ли она в диапазон, в котором бухта считается готовой*/
            if (($summ >= $min_width_of_roll)&($summ <= $max_width_of_roll)){

                echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
                echo "<tr><td style='background-color: #1aff00; text-align: center; color: #0003c4' width='100%'> БУХТА СОБРАНА </td></tr>";

                /** Если ширина бухта попадает в диапазон, то останавливаем раскрой диапазона и переносим позиции
                 * собранной бухты  в completed_rolls */
                $this->show_diapazon_for_carbon("ДО РАСКРОЯ");
                for ($z = 0; $z <$amount_of_elements; $z++){ //
                    if (str_split($counter_to_bin)[$z] == '0'){// если позиция счетчика = 0 значит эта позиция остается в диапазоне, переносим ее во временный массив
                        array_push($temp_diapazone, $this->diapazon[$z]);
                    } else { // если позиция счетчика <> 0 значит эта позиция переносится в completed_roll
                        //echo "<p>RECORD";
                        //print_r_my($this->diapazon[$z]);
                        array_push($completed_roll,$this->diapazon[$z]);
                    }
                }
                /** Добавляем в массив собранных бухт собранную бухту */
                array_push($this->completed_rolls, $completed_roll);
                /** Отображаем собранную бухту */
                $this->show_completed_roll($completed_roll);
                /** Очищаем старый диапазон */
                $this->diapazon = $temp_diapazone;
                /** очищаем временный диапазон */
                $temp_diapazone = array();
                /** возвращаем единицу, как признак того что бухта собралась */
                $this->show_diapazon_for_carbon("ПОСЛЕ РАСКРОЯ");
                return 1;
            }
            /** Продолжаем цикл, если бухта в данной итерации не собралась */
        }
        /** Если мы сюда дошли - значит бухта ен собралась  :( */
       // echo "Бухта не собралась";
        return 0;
    }

    /** Функция раскроя для диапазона */
    public function cut_execute_for_diapazone_for_simple ($width_of_main_roll, $max_gap, $min_gap, $print){
        /** на входе получаем массив с N элементов */

        /** @var  $completed_roll - массив для позиций собранной бухты */
        $completed_roll = array();

        /** @var  $temp_diapazone - временный массив для переноса обновленных значений диапазона */
        $temp_diapazone = array();

        /** @var  $min_width_of_roll - минимальная используемая ширина рулона, например: 945 = 1000 - 55(максимально-допустимый отход) */
        $min_width_of_roll = $width_of_main_roll - $max_gap;

        /** @var  $max_width_of_roll - минимальная используемая ширина рулона, например: 945 = 1000 - 15(минимально-допустимый отход) */
        $max_width_of_roll = $width_of_main_roll - $min_gap;

        $amount_of_elements = sizeof($this->diapazon);
        $amount_of_variants = pow(2,$amount_of_elements)-1;

        /** считаем возможные варианты сумм элементов массива, представив счетчик в двоичном наборе битов */
        for ($x=1; $x <=$amount_of_variants; $x++){ // 0 не считаем так как там все равно 0
            $counter_to_bin=str_pad( decbin($x), $amount_of_elements, '0', STR_PAD_LEFT);
            $summ = 0;
            /** цикл подсчета суммы ширины полос для текущей комбинации */
            for ($z = 0; $z <$amount_of_elements; $z++){
                $summ = $summ + $this->diapazon[$z][2]*str_split($counter_to_bin)[$z];
            }

            /** проверяем сумму, входит ли она в диапазон, в котором бухта считается готовой*/
            if (($summ >= $min_width_of_roll)&($summ <= $max_width_of_roll)){

                echo "<table  style='border: 1px solid black; border-collapse: collapse; font-size: 14px;'>";
                echo "<tr><td style='background-color: #1aff00; text-align: center; color: #0003c4' width='100%'> БУХТА СОБРАНА </td></tr>";

                /** Если ширина бухта попадает в диапазон, то останавливаем раскрой диапазона и переносим позиции
                 * собранной бухты  в completed_rolls */
                $this->show_diapazon_for_simple("ДО РАСКРОЯ");
                for ($z = 0; $z <$amount_of_elements; $z++){ //
                    if (str_split($counter_to_bin)[$z] == '0'){// если позиция счетчика = 0 значит эта позиция остается в диапазоне, переносим ее во временный массив
                        array_push($temp_diapazone, $this->diapazon[$z]);
                    } else { // если позиция счетчика <> 0 значит эта позиция переносится в completed_roll
                        //echo "<p>RECORD";
                        //print_r_my($this->diapazon[$z]);
                        array_push($completed_roll,$this->diapazon[$z]);
                    }
                }
                /** Добавляем в массив собранных бухт собранную бухту */
                array_push($this->completed_rolls, $completed_roll);
                /** Отображаем собранную бухту */
                $this->show_completed_roll($completed_roll);
                /** Очищаем старый диапазон */
                $this->diapazon = $temp_diapazone;
                /** очищаем временный диапазон */
                $temp_diapazone = array();
                /** возвращаем единицу, как признак того что бухта собралась */
                $this->show_diapazon_for_simple("ПОСЛЕ РАСКРОЯ");
                return 1;
            }
            /** Продолжаем цикл, если бухта в данной итерации не собралась */
        }
        /** Если мы сюда дошли - значит бухта ен собралась  :( */
        // echo "Бухта не собралась";
        return 0;
    }


    # Условия завершения цикла раскроя:
    # если нельзя расширить диапазон -> check_wide_of_diapazon возвращает 0
    # если бухта не собирается -> cut_execute_for_diapazone возвращает 0
    # если не хватает ширины диапазона ->
    public function test_cut_execute_for_carbon()
    {
        /** Создаем диапазон для раскроя */
        $stop_marker = false;
        $extension_stop_marker = false;
        $execute_stop_marker = false;
        $check_wide_stop_marker= false;
        /** @var  $safety_counter - счетчик показывающий сколько итераций сделано при не возможности собрать рулон и ширине диапазона, больше чем бухта*/
        $safety_counter = 0;
        while ($stop_marker <> true) { //если ширины диапазона достаточно а собрать нельзя
            /** проверка не конец ли это массива, если конец все остатки корректируем */
            /** Если полоса самая длинная в массиве меньше 15, но больше или равна 10 приравниваем ее к 15, полосы короче 10 удалаем */
            if ($this->cut_array_carbon[0][3] < 15){
                $correction_array = array();
                for ($a = 0; $a < sizeof($this->cut_array_carbon); $a++){
                    if ($this->cut_array_carbon[$a][3] >= 10) {
                        array_push($correction_array, $this->cut_array_carbon[$a]);
                        $correction_array[$a][3] = 15;
                    }
                }
                $this->cut_array_carbon = $correction_array;
            }
            /** Проверяем достаточно ли ширины диапазона для сбора бухт(ы) */
            if ($this->check_wide_of_diapazon_for_carbon() == 0) {
                $check_wide_stop_marker = true;
                /** Расширение диапазона, если ширина диапазона меньше чем ширина масимально возможной бухты */
                if ($this->extension_of_diapazon_for_carbon() == 0){
                    $extension_stop_marker = true;
                }
            }
            /** Запускаем принудительное расширение диапазона, если safety_counter начал рости */
            if ($safety_counter == 2){
                $this->extension_of_diapazon_for_carbon() ;
                    echo "<p>SAFETY_COUNTER extension =0";
            }
            /** Собираем бухты столько раз сколько будет собираться, как имнимум один проход делается */
            do {
                $repeat = $this->cut_execute_for_diapazone_for_carbone(1000, 55, 15, 0);

            } while ($repeat == 1);
            if ($repeat == 0){
                $safety_counter++;
                $execute_stop_marker = true;
            }
            if ($safety_counter == 10000){
                $stop_marker = true;

            }
            /**  ни чего больше не можем сделать - останавливаем цикл */
           if (($execute_stop_marker == true)&($check_wide_stop_marker == true)&($extension_stop_marker == true)){
              $stop_marker = true;
           }
        }
        $this->show_diapazon_for_carbon("НЕ ВОШЕДШИЕ В РАСКРОЙ РУЛОНЫ:");
        $this->show_cut_array_carbon();
        $this->show_completed_rolls_for_carbon();
        $this->completed_rolls = array();
        /** обнуляем диапазон чтобы угольные позиции не попали в раскрой белых */
        $this->clear_the_diapazon();
    }


    public function test_cut_execute_for_simple()
    {
        /** Создаем диапазон для раскроя */
        $stop_marker = false;
        $extension_stop_marker = false;
        $execute_stop_marker = false;
        $check_wide_stop_marker= false;
        /** @var  $safety_counter - счетчик показывающий сколько итераций сделано при не возможности собрать рулон и ширине диапазона, больше чем бухта*/
        $safety_counter = 0;
        while ($stop_marker <> true) { //если ширины диапазона достаточно а собрать нельзя
            /** проверка не конец ли это массива, если конец все остатки корректируем */
            /** Если полоса самая длинная в массиве меньше 15, но больше или равна 10 приравниваем ее к 15, полосы короче 10 удалаем */
            if ($this->cut_array_simple[0][3] < 15){
                $correction_array = array();
                for ($a = 0; $a < sizeof($this->cut_array_simple); $a++){
                    if ($this->cut_array_simple[$a][3] >= 10) {
                        array_push($correction_array, $this->cut_array_simple[$a]);
                        $correction_array[$a][3] = 15;
                    }
                }
                $this->cut_array_simple = $correction_array;
            }
            /** Проверяем достаточно ли ширины диапазона для сбора бухт(ы) */
            if ($this->check_wide_of_diapazon_for_simple() == 0) {
                $check_wide_stop_marker = true;
                /** Расширение диапазона, если ширина диапазона меньше чем ширина масимально возможной бухты */
                if ($this->extension_of_diapazon_for_simple() == 0){
                    $extension_stop_marker = true;
                }
            }
            /** Запускаем принудительное расширение диапазона, если safety_counter начал рости */
            //if ($safety_counter == 2){
            if ($safety_counter > 2){

                echo "<br>safety_counter >2<br>";

                $this->show_diapazon_for_simple("РУЛОНЫ В ДИАПАЗОНЕ ДО РАСШИРЕНИЯ");

                $this->extension_of_diapazon_for_simple();

                $this->show_diapazon_for_simple("РУЛОНЫ В ДИАПАЗОНЕ после РАСШИРЕНИЯ");

            }
            /** Собираем бухты столько раз сколько будет собираться, как минимум один проход делается */
            do {
                $repeat = $this->cut_execute_for_diapazone_for_simple(1000, 55, 10, 0);

            } while ($repeat == 1);
            if ($repeat == 0){
                $safety_counter++;
                $execute_stop_marker = true;
                echo "[!] TEST".$safety_counter;
            }
            if ($safety_counter == 10000){
                $stop_marker = true;

            }
            /**  ни чего больше не можем сделать - останавливаем цикл */
            if (($execute_stop_marker == true)&($check_wide_stop_marker == true)&($extension_stop_marker == true)){
                $stop_marker = true;
                echo "<p>[!] НИ ЧЕГО НЕ МОЖЕМ СДЕЛАТЬ. ВСЕ МАРКЕРЫ ОСТАНОВА АКТИВНЫ [!]<p>";
            }
        }
        $this->show_diapazon_for_simple("ОСТАВШИЕСЯ В ДИАПАЗОНЕ РУЛОНы");
        $this->show_cut_array_simple();
        $this->show_completed_rolls_for_simple();
        $this->clear_the_diapazon();
    }



}

