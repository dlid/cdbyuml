Use in Anax
=========

This library was created as a part of the kmom05 in [DV1486 Databased webapplications with PHP and MVC framework](http://edu.bth.se/utbildning/utb_kurstillfalle.asp?lang=en&KtAnmKod=C5403&KtTermin=20142) at Blekinge Institute of Technology.

A part of this task was to include instructions of how to use the library in the Anax-MVC framework used during the course.

Setup the controller
----

First let's create a basic ANAX Controller. We include the config and create a start route

```php
<?php 
/**
 * This is a Anax pagecontroller.
 *
 */

// Get environment & autoloader.
require __DIR__.'/config_with_app.php'; 

$app->router->add('', function() use ($app, $di) {
  $app->theme->setTitle("Yuml test");
  $app->views->add('me/page', [
        'content' => 'hi',
        'byline' => null
    ]);
});

$app->router->handle();
$app->theme->render();
```

Include CDatabase
----

You can easily use CDbYuml with the CDatabase library, so let's start with including that library in our new controller.

```php
// Include database support
$di->setShared('db', function() {
    $db = new \Mos\Database\CDatabaseBasic();
    $db->setOptions(require ANAX_APP_PATH . 'config/database_sqlite.php');
    $db->connect();
    return $db;
});
```

Include CDbYuml
----

Now we can include the CDbYuml library. If you install with composer the autoloader will automatically find the class files.

Because we use a custom library we must also specify what SQL to use. In the example below we use sqlite.

Since CDbYuml have no way of knowing how CDatabase works, we specify a custom query function that simply make use of
CDatabase to return the required database result.

```php
// Include support for generating yUML diagrams
$di->set('yuml', function() use ($di) {
    $db = new \Dlid\DbYuml\CDbYuml();
    $db->setOptions([  
        'dialect' => 'sqlite',
        'query' => function($query, $parameters) use ($di) {
            $di->db->execute($query, $parameters);
            return $di->db->fetchAll();
        }
    ]);
    return $db;
});
```

Create a route for the diagram image
----

Now we have the database and the CDbYuml library available. So now we create a new route that will generate and output the final diagram to the browser.

This route will return the actual image.

```php
$app->router->add('dbdiagram', function() use ($app, $di) {
  $di->yuml->outputImage();
});
```

Aim your web browser towards /webroot/cdbyuml.php/dbdiagram to see the image

Create a route for debug information
----

CDbYuml can also dump the generated markup that is send to generate the diagram, including all the SQL queries used to gather information from the database.

Let's create a route to output all that data as well.

```php
$app->router->add('dbdebug', function() use ($app, $di) {
  // By passing true as parameter, all caching will be ignored
  // This is to make sure all queries are executed so they will be available
  // for review
  $di->yuml->outputText(true);
});
```

Aim your web browser towards /webroot/cdbyuml.php/dbdebug to see the debug information


Update the home route to show the image
----

Now we have two routes to display the image and show debug information. Let's update the home route to show the image and link to the debug-page.

```php
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
```

Example file
----
This full controller example is available in the webroot folder

