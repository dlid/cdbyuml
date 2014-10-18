[![Build Status](https://travis-ci.org/dlid/cdbyuml.svg?branch=master)](https://travis-ci.org/dlid/cdbyuml)

[![Code Coverage](https://scrutinizer-ci.com/g/dlid/cdbyuml/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dlid/cdbyuml/?branch=master)

[![Code Coverage](https://scrutinizer-ci.com/g/dlid/cdbyuml/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/dlid/cdbyuml/?branch=master)

CDbYuml
=========

CDbYuml will create class diagrams from your PDO sqlite or mysql datasource using [yuml.me](http://yuml.me).

  - Flexible so you can use your favorite datasbase access layer
  - Easy to use with an existing PDO connection (see example below)

CDbYuml can be used with many other database librariees due to the fact that you will define how the queries are executed:

 - CDbYuml will use your callback function to query database for metadata
 - CDbYuml will then generate the Yuml string based on the dataase metadata
 - The Yuml string is posted to yuml.me and the generated diagram is downloaded

Version
----

0.2

Tech
-----------

CDbYuml has the following requirements: 

* [CURL] - The Client URL extension must be enabled in PHP
* [PHP] - tested with PHP 5.4

Note about complex diagrams
-----------

For very large and/or databases this may not be the library you are looking for. It has been tested with simple database structures with a few foreign keys of different kinds.

If the diagram is not generated like you want, you can always send me the generated text and I can try to figure out how to make it compatible.

Options
--------------
```php
$cdbyuml->setOptions([
 'proxy'        =>   null,       // Proxy address
 'proxyauth'    =>   null,       // Proxy authentication (username:password)
 'query'        =>   null,       // \PDO Object or custom function to fetch data from database
 'sql_dialect'  =>  'sqlite',    // sqlite or mysql. Determines which queries to run
 'style'        =>  'plain',     // Yuml.me styles (plain, scruffy or nofunky)
 'scale'        =>  100,         // Yuml.me scale (100 = 100%) 
 'close'        =>  null,        // Optional callback function to close database
 'force'        =>  false,       // Ignore all caching.
 'cachepath'    =>  null,        // Full path to the cachefile
 'cachetime'    =>  '15 minutes' // Maximum time before re-validating database structure
]);
```

Methods
--------------

* **setOptions** ( *$options* ) - Configure the library. See option above
* **outputImage** ( *[$force = true]* ) - generate diagram and output the image to the browser
* **outputText** ( *[$force = true]* ) - output a HTML page with debug information
* **__construct** ( *$options* ) - Configure the library. Same parameters as setOptions

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
   #->outputText() // Uncomment to see debug information
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
$cdbyuml = new \Dlid\DbYuml\CDbYuml([
 'query' => $dbh,
 'cachepath' => '/users/david/temp/cache/dbcache1'
]);
```

Caching
--------------

I recommend using caching for your diagrams. If you do not, then CDbYuml will query your database for it's structure and download a new diagram from yuml.me **every time**. Please cache and everyone will be so, so happy!


```php
$cdbyuml->setOptions([
 'cachefile' => '/somepath/db_diagram',
 'cachetime' => '5 minutes'
]);
```

In the above examle, if you specify **cachepath** and **cachetime** two things will happend:

 - The yuml text will be saved to the file /somepath/db_diagram.cache
 - The downloaded image will be saved to the file /somepath/db_diagram.png

The cache will be invalidated if:

 - you change the 'style' parameter
 - you change the 'scale' parameter
 - the cachetime has expired (five minutes since last .cache-file was written)

If 'style' or 'scale' changes, the database structure will be extracted again and a new diagram will be downloaded.

If the cachetime expires then the database will be queried again for it's structure and:

 - if the structure has changed, then a new diagram will be downloaded
 - otherwise the /somepath/db_diagram.png image will be used

##Composer

You can add [dlid/cdbyuml](https://packagist.org/packages/dlid/cdbyuml) to your composer.json file like this.

```sh
 "require": {
  "dlid/cdbyuml": "dev-master"
 }
```


License
----

MIT


[CURL]:http://php.net/manual/en/book.curl.php
[PHP]:http://php.net

