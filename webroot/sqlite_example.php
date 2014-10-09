<?php

/**
 * Database wrapper, provides a database API for the framework but hides details of implementation.
 *
 */


require('../src/DbYuml/CDbYuml.php');

$file_db = new PDO('sqlite:school.sqlite3');

$file_db->exec('DROP TABLE IF EXISTS [teacher]');
$file_db->exec('DROP TABLE IF EXISTS [course]');
$file_db->exec('DROP TABLE IF EXISTS [person]');
$file_db->exec('DROP TABLE IF EXISTS [student]');

 // Create table messages
$file_db->exec("CREATE TABLE [teacher] (
  [id] INTEGER PRIMARY KEY AUTOINCREMENT, 
  [first_name] VARCHAR(40), 
  [last_name] VARCHAR(40), 
  [email] VARCHAR(250));");

 // Create table messages
$file_db->exec("CREATE TABLE [course] (
  [id] INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, 
  [start] DATETIME NOT NULL, 
  [end] DATETIME NOT NULL, 
  [teacher_id] CHAR NOT NULL CONSTRAINT [FK_course_teacher] REFERENCES [teacher]([id]));");

 // Create table messages
$file_db->exec("CREATE TABLE [person] (
  [id] INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, 
  [first_name] VARCHAR(40) NOT NULL, 
  [last_name] VARCHAR(40) NOT NULL, 
  [email] VARCHAR(250) NOT NULL, 
  [identity] created NOT NULL);");

 // Create table messages
$file_db->exec("CREATE TABLE [student] (
  [id] INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, 
  [person_id] INTEGER NOT NULL CONSTRAINT [FK_student_person] REFERENCES [person]([id]), 
  [course_id] INTEGER NOT NULL CONSTRAINT [FK_student_course] REFERENCES [course]([id]), 
  [created] DATETIME NOT NULL, 
  [aborted] DATETIME);");


$cdbyuml = new \Dlid\DbYuml\CDbYuml($file_db, [
  'cachepath'  => 'school.sqlite', // path and name of cache file
  'cachetime'  => '15 minutes'       // re-check database structure only every 15 minutes
]);

$cdbyuml->outputImage();