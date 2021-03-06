<?php
$php_start_time = array_sum(explode(' ',microtime()));
require_once('default.admin.inc.php');
require 'UploadWorkshiftBackup.php';
$temp_houses = getopt('h::');
if (isset($temp_houses['h'])) {
  $houses = $temp_houses['h'];
  if (!is_array($houses)) {
    $houses = array($houses);
  }
}

$scratch_dir = "$php_includes/scratch";
$backup_dir = "$scratch_dir/backupadmin";
#if (false) {
if (file_exists($backup_dir)) {
  $dh = opendir($backup_dir);
  while ($fname = readdir($dh)) {
    if($fname=='.' || $fname=='..') continue;
    unlink("$backup_dir/$fname") || 
      janak_error("Couldn't delete $fname from $backup_dir");
  }
}
else {
  if (!file_exists($scratch_dir)) {
    mkdir($scratch_dir);
  }
  mkdir($backup_dir);
}
set_error_handler('janak_errhandler');
$done_houses = array();
$max_time_allowed = 10;
$uploader = new UploadWorkshiftBackup();
$db->SetFetchMode(ADODB_FETCH_NUM);
foreach ($houses as $house_name) {
  //  fwrite(STDERR,"doing $house_name\n");
  $url_array['db'] = "$db_basename$house_name";
  $done_archives = array();
  $db->Connect($url_array['server'],$url_array['user'],$url_array['pwd'],
               $url_array['db']);
  $res = $db->Execute("select `archive` from `GLOBAL_archive_data` where " .
                      "`emailed` is NULL order by `semester_start`");
  while ($row = $res->FetchRow()) {
    //    fwrite(STDERR,"doing " . $row[0]);
    $filename = "$backup_dir/$house_name";
    ($handle = fopen($filename,"w")) ||
      janak_error("Couldn't open $filename");
    fwrite($handle,"set autocommit=0;\nbegin;\n") ||
      janak_error("Couldn't start writing to $filename");
    $db->Execute("set autocommit=0");
    $db->Execute("begin");
    $tbl_res = $db->Execute('show tables like ?',
                            array(quote_mysqlreg("$archive_pre{$row[0]}_") . 
                                  '%'));
    while ($tbl_row = $tbl_res->FetchRow()) {
      $tbl = $tbl_row[0];
      fwrite($handle,"drop table if exists `$tbl`;\n") ||
        janak_error("Couldn't write to $filename");
      ($create_res = $db->Execute("show create table `$tbl`")) ||
        janak_error("Couldn't get table definition for $tbl");
      fwrite($handle,$create_res->fields[1] . ";\n") ||
        janak_error("Couldn't write to $filename");
      ($data_res = $db->Execute("select * from `$tbl`")) ||
        janak_error("Couldn't select from $tbl");
    while ($data_row = $data_res->FetchRow()) {
      fwrite($handle,
               "INSERT INTO `$tbl` VALUES (" . 
               join(',',array_map('db_quote',$data_row)) . ");\n") ||
          janak_error("Couldn't write to $filename");
      }
    }
    $db->Execute("commit");
    $db->Execute("set autocommit=1");
    fwrite($handle,"commit;\nset autocommit=1;\n") ||
      janak_error("Couldn't write to $filename");
    $zipfile = $backup_dir .'/' . $row[0] . '.zip';
    exec("$zipexec -j " . escapeshellarg($zipfile) . ' ' . 
         escapeshellarg($filename) . " 2>&1",$output,$return_var);
    if ($return_var) {
      janak_error(implode("\n",$output));
    }
    try {
      $uploader->upload($zipfile,$house_name);
    }
    catch (Exception $e) {
      janak_error($e->getMessage());
    }
    $done_archives[] = $row[0];
    $db->Execute("UPDATE `GLOBAL_archive_data` SET `emailed` = 1 where `archive` = ?",
                 array($row[0])) ||
      janak_error("Couldn't update emailed flag for " . $row[0]);
    unlink($filename) || 
      janak_error("Couldn't delete $filename");
    unlink($zipfile) || janak_error("Couldn't delete $zipfile");
    
  }
}

function db_quote($str) {
  global $db;
  return $db->quote($str);
}

?>
