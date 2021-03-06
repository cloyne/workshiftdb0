<?php
require_once('default.inc.php');
$col_names = array('workshift', 'hours', 'category','description');
$col_styles = array_fill(0,2,'');
$col_styles[] = 'input';
$col_styles[] = 'textarea';
$table_name = 'master_shifts';
$delete_flag = false;
$order_exp = 'workshift';
$col_sortable = array('pre_process_default','pre_process_num',
                      'pre_process_default','pre_process_default');

function mung_whole_row(&$row) {
  static $workshift = null, $hours = null;
  $skip_row = true;
  if ($row['workshift'] !== $workshift || 
      $row['hours'] !== $hours) {
    $workshift = $row['workshift'];
    $hours = $row['hours'];
    return true;
  }
  $row = null;
}
$mung_whole_row = 'mung_whole_row';
$title_page = 'Set Shift Descriptions';
require_once("$php_includes/table_edit.php");
?>
      
