<?php
require_once ('tools/tools.php');
require_once ('settings.php');

echo
    '<style type="text/css">'.
        'TH, TD {'.
        'border: 1px solid black; '.
   '}'.
  '</style>';



$parts = $_POST['part'];

if ($parts == 'wireframe'){ component_analysis_wireframe($_POST['order']);}
if ($parts == 'prefilter'){ component_analysis_prefilter($_POST['order']);}
if ($parts == 'paper_package'){ component_analysis_paper_package($_POST['order']);}
if ($parts == 'g_box'){ component_analysis_group_box($_POST['order']);}
if ($parts == 'box'){ component_analysis_box($_POST['order']);}