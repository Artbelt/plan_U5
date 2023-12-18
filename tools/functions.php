<?php

function choose_height(){
    $x = random_int(1,5);
    switch ($x){
        case 1:
            $height = 24;
            break;
        case 2:
            $height = 33;
            break;
        case 3:
            $height = 40;
            break;
        case 4:
            $height = 48;
            break;
        case 5:
            $height = 60;
            break;
    }
    return $height;
}

function choose_width(){
    $width = random_int(70,250);
    return $width;
}

function choose_pleats_count(){
    $count = random_int(50,90);
    return $count;
}