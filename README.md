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

### Alternative ways to do it
#### Passing the PDO object as a query

You can also pass your PDO object as the first parameter. Other options can then be set using the second parameter.

```php
# Just the PDO:
$cdbyuml->setOptions($dbh);

# Some additional options
$cdbyuml->setOptions($dbh, [ 'proxy' => 'http://some-proxy.example.net:8080' ]);
```

**Note**: Using the method above you can not pass in the 'query' option in the second parameter. The internal function for retreiving data will then be used

#### Using the constructor instead of  setOptions

Finally you can also use the constructor in the very same way as setOptions:

```php
# Example passing just the PDO:
$cdbyuml = new \Dlid\DbYuml\CDbYuml($dbh);
```

##Composer

```sh
composer example here
```


License
----

MIT


[CURL]:http://php.net/manual/en/book.curl.php
[PHP]:http://php.net

