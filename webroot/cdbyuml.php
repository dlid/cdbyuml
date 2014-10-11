<?php 
/**
 * This is a Anax pagecontroller.
 *
 */

// Get environment & autoloader.
require __DIR__.'/config_with_app.php'; 

// Include database support
$di->setShared('db', function() {
    $db = new \Mos\Database\CDatabaseBasic();
    $db->setOptions(require ANAX_APP_PATH . 'config/database_sqlite.php');
    $db->connect();
    return $db;
});


$app->router->add('', function() use ($app, $di) {
    $app->theme->setTitle("Yuml test");

    $imgSrc = $app->url->create('dbdiagram');
    $debugUrl = $app->url->create('dbdebug');

    $html = <<<EOD
    <h1>Generated database diagram</h1>
    <p>This example make use of CDatabase to generate a database diagram</p>
    <p>[ <a href="$debugUrl">show debug info</a> ]</p>
    <img src='$imgSrc' />
EOD;

    $app->views->add('me/page', [
        'content' => $html,
        'byline' => null
    ]);
});

// Include support for generating yUML diagrams
$di->set('yuml', function() use ($di) {
    $db = new \Dlid\DbYuml\CDbYuml();
    $db->setOptions([  
        'cachepath' => 'dali14',
        'dialect' => 'sqlite',
        'query' => function($query, $parameters) use ($di) {
            $di->db->execute($query, $parameters);
            return $di->db->fetchAll();
        }
    ]);
    return $db;
});

$app->router->add('dbdiagram', function() use ($app, $di) {
  $app->theme->setTitle("Yuml test");
  $di->yuml->outputImage();
});

$app->router->add('dbdebug', function() use ($app, $di) {
  // By passing true as parameter, all caching will be ignored
  // This is to make sure all queries are executed so they will be available
  // for review
  $di->yuml->outputText(true);
});

 
$app->router->handle();
$app->theme->render();