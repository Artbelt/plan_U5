<?php
$x = 10;

echo decbin($x);
$amount_of_elements = 5;
$amount_of_variants = pow(2,$amount_of_elements)-1;
$k = 5;

echo "<p>amount_of_variants = ".$amount_of_variants;

$s = decbin($x);
for ($a=1;$a<=$amount_of_variants;$a++){

echo "<p> a = " .$a."<p>";

//$b=sprintf("%'.09d\n", decbin($a));
$b=str_pad( decbin($a), $k, '0', STR_PAD_LEFT);
echo $b;
//echo "count s = ".count(decbin($a));



}

?>