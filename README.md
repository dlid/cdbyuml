CDbYuml
=========

CDbYuml will create class diagrams from your PDO sqlite or mysql datasource using [yuml.me](yuml.me).

  - Flexible so you can use your favorite datasbase access layer
  - Easy to use with an existing PDO connection (see example below)

CDbYuml can be used with many other database librariees due to the fact that you will define how the queries are executed:

 - CDbYuml will use your callback function to query database for metadata
 - CDbYuml will then generate the Yuml string based on the dataase metadata
 - The Yuml string is posted to yuml.me and the generated diagram is downloaded

Version
----

0.1

Tech
-----------

CDbYuml has the following requirements: 

* [CURL] - The Client URL extension must be enabled in PHP
* [PHP] - tested with PHP 5.4

Sample usage
--------------
In the following example a sqlite database is created with a number of tables.

CDbYuml is then used to generate the diagram of the table.

```php
// Open a database connection
$dbh = new PDO('sqlite:mydatabase.sqlite3');

// Initialize CDBYuml
$cdbyuml = new \Dlid\DbYuml\CDbYuml();
$cdbyuml->setOptions([
   'sql_dialect' => 'sqlite',

    // Callback function query the database for metadata
    'query' => function($query, $parameters) use ($dbh) {
        // Pass along query and parameters to our open PDO connection
        $stmt = $dbh->prepare($query);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
]);

// Fetch metadata from database and generate the YUml string
$cdbyuml->execute()
   // Output the generated diagram
   ->outputImage();


```

##### Composer

```sh
composer example here
```


License
----

MIT


[CURL]:http://php.net/manual/en/book.curl.php
[PHP]:http://php.net

