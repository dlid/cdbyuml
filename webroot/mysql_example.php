<?php

require('../autoloader.php');

$dbh = new PDO('mysql:host=localhost;dbname=mydb;', 'root', 'password', 
  array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));

$cdbyuml = new \Dlid\DbYuml\CDbYuml($dbh, [
  'cachepath'  => 'mysql_example', // path and name of cache file
  'cachetime'  => '15 minutes'       // re-check database structure only every 15 minutes
]);

$cdbyuml
  ->outputText(true) // uncomment to show generated text and sql queries
  ->outputImage();