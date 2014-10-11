<?php
/**
 * 
 * A small library for creating Yuml class diagrams of a database
 * As part of the course phpmvc at Blekinge Tekniska Högskola
 * 
 * @author David Lidström
 * @license https://github.com/dlid/cdbyuml/blob/master/LICENSE MIT
 * @link https://github.com/dlid/cdbyuml
 */

namespace Dlid\DbYuml;

class CDbYuml {

	private $dbh = null;
	private $options = [];
	private $styles = ['scruffy','nofunky','plain'];


	private $dsl_text = null;
	private $dsl_cache = null;
	private $image = null;

	private $queries = array();

	public function __construct($options = null, $altOptions = null) {
		// Allow to set the options in the constructor
		if( $options!=null ) {
			$this->setOptions($options, $altOptions);
		}
	}

	/**
	 * Set the options to use 
	 * @param variable $options    array with options or PDO object
	 * @param variable $altOptions array with options if $options parameter is PDO object
	 */
	public function setOptions($options = [], $altOptions = []) {

		$default = [
		'sql_dialect' => 'sqlite',
		'proxy' => null,
		'proxyauth' => null,
		'cachetime' => '5 minutes',
		'style' => 'plain',
		'scale' => 100,
		'cachepath' => null,
		'query' => null,
		'close' => null,
		'force' => false // Set to true to ignore cached and always fetch a new diagram
		];

		// Allow to send in PDO object as param 1 and options as param 2
			if(is_a($options, '\PDO')) {
				$tmpOptions = is_array($altOptions) ? $altOptions : array();
				$tmpOptions['query'] = $options;
				$options = $tmpOptions;
			}

			$this->options = array_merge($default, $options);

			if( !in_array($this->options['style'], $this->styles)) {
				throw new \Exception("Valid 'style' values are " . implode(', ', $this->styles) );
			}

				if( !is_numeric($this->options['scale'])) {
				throw new \Exception("'scale' must be a percentage, where 100 is 'normal'");
			}

			if( !is_callable($this->options['query'])) {
				if( is_a($this->options['query'], '\PDO')) {
					$this->dbh = $this->options['query'];
					$this->options['sql_dialect'] = $this->dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);
					$this->options['query'] = array($this, 'executePdoQuery');
				} else {
					throw new \Exception("Query must be a callable method");
				}
			}


			if( !in_array($this->options['sql_dialect'], array('mysql', 'sqlite') )) {
				throw new \Exception("Only 'sqlite' and 'mysql' is supported for now");
			}
		}

		private function isCacheEnabled() {
			if( $this->options['force'] == true ) {
				return false;
			}
			return $this->options['cachepath'] !== null;
		}

		private function getUrl() {
			return "http://yuml.me/diagram/" . $this->options['style'] . ";scale:" . $this->options['scale'] . "/class/";
		}

		private function getEditUrl() {
			return "http://yuml.me/diagram/" . $this->options['style'] . "/class/draw";
		}

		/**
		 * POST the dsl_text to yuml.me and download the image
		 * @return [type] [description]
		 */
		public function downloadDiagram() {

			if($this->image !== null && !$this->options['force']) {
				return $this->image;
			}

			$this->getDslText();
			if($this->isCacheEnabled()) {
				if($this->dsl_cache && $this->dsl_cache == $this->dsl_text) {
					if(is_file($this->getPath('png'))) {
						header('x-CachedImageUsed: 1');
						$this->image = file_get_contents($this->getPath('png'));
						return $this;
					}
				}
			}

			$url = $this->getUrl();

			// Prepare string for posting
			$dslString = preg_replace(
				array("/\n/", "/,,/"),
				array(", ",   ","   ),
				trim($this->dsl_text));

			$postData = 'dsl_text='. urlencode($dslString);

			//open curl connection
			$ch = curl_init();

			//set the url, number of POST vars, POST data
			curl_setopt($ch,CURLOPT_URL, $url);
			curl_setopt($ch,CURLOPT_POST, 1);
			curl_setopt($ch,CURLOPT_POSTFIELDS, $postData);

			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			if($this->options['proxy']) {
				curl_setopt($ch, CURLOPT_PROXY, $this->options['proxy']);
			}

			if($this->options['proxyauth']) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->options['proxyauth'] );
			}

			curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-type: application/x-www-form-urlencoded'));

			// Execute POST
			$content = curl_exec ( $ch );
			$err = curl_errno ( $ch );
			$errmsg = curl_error ( $ch );
			$header = curl_getinfo ( $ch );
			$httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );

			// close connection
			curl_close ( $ch );

			if( $err ) {
				throw new \Exception("CURL Error: " . $err . " " . $errmsg . " (HTTP Code " . $httpCode . ")");
			}

			if( is_callable($this->options['close']) ) {
				$this->options['close']();
			}
			$this->image = $this->GetImageFromUrl("http://www.yuml.me/diagram/class/" . $content);

			if($this->isCacheEnabled()) {
				$this->writeImageFile($this->image);
			}

			return $this;

		}


		/**
		 * Output the diagram image to the browser
		 * @return [type] [description]
		 */
		public function outputImage($nocache = false) {

			if( $nocache === true) {
				$this->options['force'] = true;
			}

			$this->downloadDiagram();
			header('Content-type: image/png');
			echo $this->image;
			exit;
		}

		public function outputText($nocache = false) {

			if( $nocache === true) {
				$this->options['force'] = true;
			}

			$text = htmlentities($this->getDslText(), null, 'utf-8');
			$url = $this->getUrl();
			$editurl = $this->getEditUrl();
			$dialect = $this->getSQLDialect();

			$queryHtml = "";
			foreach( $this->queries as $query) {
				$sql = htmlentities($query['sql'], null, 'utf-8');
				$duration = htmlentities($query['duration'], null, 'utf-8');
				$rowcount = htmlentities($query['rowcount'], null, 'utf-8');
				$name = htmlentities($query['name'], null, 'utf-8');
				if(count($query['parameters']) > 0) {
					$parameters = "<pre>" . htmlentities(print_r($query['parameters'], 1), null, 'utf-8') ."</pre>";
				} else {
					$parameters = "";
				}

				$queryHtml.="<tr><th>$name</th><td ><pre>$sql</pre></td><td>$parameters</td><td>$rowcount</td><td>$duration</td></tr>";

			}

			if( $queryHtml ) {
				$queryHtml = <<<EOD
				<table border='1' cellpadding='10' cellspacing='2'>
					<caption>Executed queries ($dialect)</caption>
					<thead>
						<th>Name</th>
						<th>Query</th>
						<th>Parameters</th>
						<th>Returned rows</th>
						<th>Duration</th>
					</thead>
					<tbody>{$queryHtml}</tbody>
					</table>
EOD;
			} else {
				$queryHtml = "No queries executed";
			}

			$html = <<<EOD
				<div style='background-color: #fff; clear:both;'>
					<strong>dsl text</strong><br />
					<textarea style='width:100%; height: 20em; color: #000;'>$text</textarea>
				</div>
				<p><strong>Edit URL</strong><br /><a href='$editurl' target='_blank'>$editurl</a> (copy and paste the text above onto this page)</p>
				<p><strong>POST URL</strong><br /> $url (used by CDbYuml to generate the diagram image)</p>
				$queryHtml
EOD;
		echo $html;
			exit;
		}

		

		/**
		 * Save the diagram image to a location of your choice
		 * @param  [type] $path [description]
		 * @return [type]       [description]
		 */
		public function saveImage($path) {
			$this->downloadDiagram();

			if(!is_writable($path)) {
				throw new \Exception("File or path is not writeable: " . $path);
			}

			if( !@file_put_contents($path, $this->image)) {
				throw new \Exception("Could not write file " . error_get_last()['message']);
			}

			return $this;
		}


		private function writeImageFile($data) {

			$cachepath = $this->getPath("png");

			if(is_file($cachepath) && !is_writable($cachepath)) {
				throw new \Exception("File or path is not writeable: " . $cachepath);
			}

			if( !@file_put_contents($cachepath, $data)) {
				throw new \Exception("Could not write cache file " . error_get_last()['message']);
			}

		}

		private function writeCache($data) {

			$cachepath = $this->getPath("cache");

			$data = json_encode( array( 
						'data' => $data, 
						'timestamp' => time(),
						'hash' => $this->getHash()));

			if( is_file($cachepath) && !is_writable($cachepath)) {
				throw new \Exception("File or path is not writeable: " . $cachepath);
			}

			if( !@file_put_contents($cachepath, $data)) {
				throw new \Exception("Could not write cache file " . error_get_last()['message']);
			}
		}

		private function readCache() {
			$cachepath = $this->getPath("cache");

			$file_data = array( 
						'data' => null, 
						'timestamp' => null,
						'hash' => null);

			if(is_file($cachepath)) {
				$file_content = @file_get_contents($cachepath);
				if( $file_content) {
					$file_data = json_decode($file_content, true);
					if( !$file_data ) {
						throw new \Exception("Could not decode JSON from " . $cachepath);
					}
				}
			}

			return (object)$file_data;

		}

		private function readFromFile($extension) {
			return json_decode(file_get_contents($this->getPath($extension)));
		}

		private function getHash() {
			return md5($this->getUrl());
		}

		private function getPath($extension) {
			return $this->options['cachepath'] . "." . $extension ;
		}


		public function getDslText() {

			// Read from cache if available
			if( $this->isCacheEnabled() ) {
				if( is_file($this->getPath('cache'))) {
						$cache =  $this->readCache();
						if( $cache) {
							if( $cache->hash == $this->getHash() ){
								$this->dsl_text = $cache->data;
								$expirationTime = strtotime ( $this->options['cachetime'], $cache->timestamp );
								if( $expirationTime ) {
									if( time() >= $expirationTime  ) {
										$this->dsl_text = null;
										unset($cache);
									} else {
										header('x-DslCacheUsed: 1');
										$this->dsl_cache = $cache->data;
									}
								} else {
									// Not a valid expiration time. Invalidate text
									$this->dsl_text = null;
									unset($cache);
								}
							} else {
								unset($cache);
							}
						} else {
							unset($cache);
						}
				}
			}

			if( $this->dsl_text !== null && !$this->options['force'] ) {
				return $this->dsl_text;
			}

			// Extract metadata
			$tables = array();			
			foreach( $this->findAllTables() as $tableName) {
				$tables[$tableName] = [
					'columns' => $this->findColumnsInTable($tableName),
					'fk' => $this->findForeignKeysInTable($tableName)
				];
			}


			#echo "<pre>";print_r($tables);
			#exit;

			// Recursive function to create DslString
			$writtenTables = array();
			$writeYumlString = function($tblName, $tbl, $writeYumlString, $eol = "\n") use (&$writtenTables, $tables) {

				if( in_array($tblName, $writtenTables)) {
					if( $eol  == "") {
						return "[{$tblName}]";
					}
					return;
				}

				$writtenTables[] = $tblName;
				$nullablecols = array();
				$uniquecols = array();
				$fkcolumns = array();
				$fkDslString = "";
				$tableDslString = "[" . $tblName . "|";

				foreach( $tbl['fk'] as $fk ) { $fkcolumns[$fk['column_name']] = '1'; }

				for( $i=0; $i < count($tbl['columns']); $i++) {

					extract($tbl['columns'][$i]);

					$separator = $i > 0 ? ";" : null;
					$colName = "'" . $name . "'";
					$pk = ($pk == '1') ? '+' : null;
					$type = $type ? ' ' . $this->yuaml_escape(strtoupper($type)) : null;
					$null = ($notnull == '1') ? ' NOT NULL' : null;
					$fk = isset($fkcolumns[$name]) ? 'FK ' : null;

					if ($unique == '1') { $uniquecols[] = $name; }
					if ($notnull == "0") { $nullablecols[] = $name; }
					$str = "{$separator}{$pk}{$fk}{$colName}{$type}{$null}";

					if ($fk) { $fkDslString.=$str; } else { $tableDslString.=$str; }

				}
				
				$tableDslString .= ($fkDslString ? "|" . $fkDslString : null) .  "]";

				foreach( $tbl['fk'] as $fk ) {
					$rel = in_array($fk['column_name'], $nullablecols) ? "0..*-0..1" : "0..*-1";
					$rel = in_array($fk['column_name'], $uniquecols) ? "0..1-1" : $rel;

					if(!isset($tables[$fk['referenced_table_name']])) {
						throw new \Exception("table not found");
					}

					$tableDslString.= "\n[$tblName]" . $rel . $writeYumlString($fk['referenced_table_name'], $tables[$fk['referenced_table_name']], $writeYumlString, "");
				}
				return $tableDslString . $eol;
			};

			$dslString = "";

			foreach( $tables as $tblName => $tbl) {
				$dslString.=$writeYumlString($tblName, $tbl, $writeYumlString);
			}


			if($dslString && $this->isCacheEnabled() ) {
				$this->writeCache($dslString);
			}

			$this->dsl_text = $dslString;
			return $dslString;
		}

		/**
		 * Crude escape function. It's unclear how to escape commas so they're replaced
		 * @param  [type] $string [description]
		 * @return [type]         [description]
		 */
		private function yuaml_escape($string) {
			return preg_replace("/\s{2, }/", '', str_replace(',', ' ', $string));
		}

	/**
	 * Internal function to execute PDO queries when the 
	 * query option is set to a PDO object
	 * @param  string $query      The SQL query to execute
	 * @param  array $parameters  Parameters to bind to the query
	 * @return array             
	 */
	function executePdoQuery($query, $parameters) {
		$stmt = $this->dbh->prepare($query );

		if (!$stmt) {
			echo "Error in preparing query: "
			. $this->dbh->errorCode()
			. " "
			. htmlentities(print_r($this->dbh->errorInfo(), 1)) . " " 
			. htmlentities($query);
			exit;
		}

		$res = $stmt->execute($parameters);

		if (!$res) {
			echo "Error in executing query: "
			. $stmt->errorCode()
			. " "
			. htmlentities(print_r($stmt->errorInfo(), 1)) . " " 
			. htmlentities($query);

			exit;
		}

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	function GetImageFromUrl($url)
	{


		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		if($this->options['proxy']) {
			curl_setopt($ch, CURLOPT_PROXY, $this->options['proxy']);
		}

		if($this->options['proxyauth']) {
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->options['proxyauth'] );
		}

		curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-type: application/x-www-form-urlencoded'));

		//execute post
		$content = curl_exec ( $ch );
		$err = curl_errno ( $ch );
		$errmsg = curl_error ( $ch );
		$header = curl_getinfo ( $ch );
		$httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );

		curl_close($ch);
	
		if( $err ) {
			throw new \Exception("CURL Error: " . $err . " " . $errmsg . " (HTTP Code " . $httpCode . ")");
		}

		
		return $content;
	}

	/**
	 * Find all tables in the current database
	 * @return array
	 */
	public function findAllTables() {

		$rows = $this->executeDialectQuery('List tables',
			[
			'sqlite' => "SELECT [name]\nFROM sqlite_master\nWHERE type='table'\n AND [name] != 'sqlite_sequence'",
			'mysql' => "SHOW TABLES"
			]);

		// Get the table names (value of first column)
		$rows = array_map(function ($row) {
			return current($row);
		}, $rows);

		return $rows;

	}

	/**
	 * Get all columns for a specific table
	 * @param  string $tableName The name of the table
	 * @return array
	 */
	public function findColumnsInTable($tableName) {

		$escapedTableName = $this->escapeName($tableName);

		$rows = $this->executeDialectQuery('List columns',
			[
			'sqlite' => "PRAGMA table_info({$escapedTableName})",
			'mysql' => "SHOW COLUMNS FROM {$escapedTableName}"
			]);

		$rows = array_map( array($this, 'mapColumnInfo'), $rows);
		$uniqueColumnNames = $this->findUniqueColumnsInTable($tableName);


		foreach($rows as &$row) {
			if( in_array($row['name'], $uniqueColumnNames)) {
				$row['unique'] = 1;
			}
		}

		#echo "<pre>";
	#	print_r($rows);
	#	exit;

		return $rows;
	}

	/**
	 * List indexes for a table to identify unique foreign keys
	 * @param  string $tableName Name of the tabler
	 * @return array
	 */
	private function findUniqueColumnsInTable($tableName) {
		$uniqueColumns = array();


		$escapedTableName = $this->escapeName($tableName);
		$rows = $this->executeDialectQuery('List indicies',
			[
			'sqlite' => "PRAGMA index_list({$escapedTableName})",
			'mysql' => "SHOW INDEXES FROM {$escapedTableName}"
			]);

		$rows = array_map( array($this, 'mapIndexInfo'), $rows);

		// For SQLite we need another query per index to get the column names
		if($this->getSQLDialect() == 'sqlite' && count($rows) > 0) {
			$params = array($tableName);
			$macros = array();
			foreach($rows as $row ) {
				$params[] = $row['name'];
				$macros[] = '?';
			}

			$macros = implode(',', $macros);

			$indexInfo = $this->executeDialectQuery('List index columns', "SELECT *\nFROM sqlite_master\nWHERE [type] = 'index' AND [tbl_name] = ?\n AND [name] IN ({$macros})\n AND [sql] <> ''\nORDER BY name;", $params);
			foreach($indexInfo as $info) {
				if( preg_match("/ ON \[{$info['tbl_name']}\] \((.*)\)/", $info['sql'], $m)) {
					if( strpos($info['sql'], 'UNIQUE INDEX ') !== false ) { 
						$columns = explode(',', $m[1]);
						foreach($columns as $col) {
							$colName = rtrim(ltrim($col, '['), ']');
							if(!in_array($colName, $uniqueColumns)) {
								$uniqueColumns[] = $colName;
							}							
						}
					}
				}
			}
		} else {

			$uniqueColumns = array_filter(array_map(function($item) {
				if( $item['unique'] == 1 ) { return $item['column']; }
				return null;
			}, $rows));

		}
		return $uniqueColumns;
	}

	/**
	 * Map index details to a normalized format
	 * @param  array $row Database row
	 * @return array 	Normalized row
	 */
	private function mapIndexInfo($row) {

		$newRow = [];
		switch( $this->getSQLDialect() ){
			case 'sqlite': 

			$newRow = [
				'name' => $row['name'],
				'unique' => $row['unique'] == "1" ? 1 : 0,
				'column' => null
			];

			break;
			case 'mysql': 
				$newRow = [
					'name' => $row['Key_name'],
					'unique' => $row['Non_unique'] == "0" ? 1 : 0,
					'column' => $row['Column_name']
				];
			break;
		}

		return $newRow;
	}

	/**
	 * Find all foreign keys for a specific table
	 * @param  string $tableName
	 * @return array
	 */
	public function findForeignKeysInTable($tableName) {
		$escapedTableName = $this->escapeName($tableName);

		$rows = $this->executeDialectQuery('List FK',
			[
			'sqlite' => "PRAGMA foreign_key_list({$escapedTableName})",
			'mysql' => "SELECT\n `column_name`,\n `constraint_name`,\n `referenced_table_name`,\n `referenced_column_name`\nFROM `information_schema`.`key_column_usage`\nWHERE `table_name` = ?\n AND `referenced_table_name` IS NOT NULL;"
			],
			[
				'mysql' => array($tableName)
			]);

		#echo "<pre>";print_r($rows);#exit;

		return array_map( array($this, 'mapForeignKeyInfo'), $rows);
	}

	/**
	 * Very simple escape of a table or column name
	 * @param  [type] $name [description]
	 * @return [type]       [description]
	 */
	private function escapeName($name) {
		switch( $this->getSQLDialect() ){
			case 'sqlite': return "[$name]"; 
			case 'mysql': return "`$name`";
		}
		return $name;
	}


	/**
	 * Map dialect specific foreign key information to a normalized value
	 * @param  array  $row Database row to map
	 * @return array       Normalized database row
	 */
	private function mapForeignKeyInfo($row) {
		$newRow = [];
		switch( $this->getSQLDialect() ){
			case 'sqlite': 
				$newRow = [
				'referenced_table_name' => $row['table'],
				'referenced_column_name' => $row['to'],
				'column_name' => $row['from']
				];
			break;
			case 'mysql':

				$newRow = [
					'referenced_table_name' => $row['referenced_table_name'],
					'referenced_column_name' => $row['referenced_column_name'],
					'column_name' => $row['column_name']
				];
			break;
		}
		return $newRow;
	}

	/**
	 * Map dialect specific column information to a normalized value
	 * @param  array  $row Database row to map
	 * @return array       Normalized database row
	 */
	private function mapColumnInfo($row) {
		$newRow = [];
		switch( $this->getSQLDialect() ){
			case 'sqlite': 
			$newRow = [
			'name' => $row['name'],
			'notnull' => $row['notnull'],
			'default' => $row['dflt_value'],
			'pk' => $row['pk'],
			'type' => $row['type'],
					'unique' => 0 // Fetch later in a separeate query
					];
					break;
					case 'mysql': 
					$newRow = [
					'name' => $row['Field'],
					'notnull' => $row['Null'] == 'NO' ? 1 : 0,
					'default' => $row['Default'],
					'pk' => $row['Key'] == 'PRI',
					'type' => $row['Type'],
					'unique' => 0 // Fetch later in a separeate query
					];

					break;
				}
				return $newRow;
			}



	/**
	 * Transform object to array if it is not an array already
	 * @param  [type] $row [description]
	 * @return [type]      [description]
	 */
	private function normalizeRows($row) {
		if( is_object($row)) {
			return get_object_vars($row);
		} else if( is_array($row)) {
			return $row;
		} 
		return null;
	}

	/**
	 * Execute a database query depending on the current sql dialect
	 * @param  variable $queryArray SQL string or associative array of queries
	 * @param  variable $parameters Array of parameters or associative array of parameters
	 * @return array
	 */
	private function executeDialectQuery($name, $queryArray, $parameters = []) {
		
		$sql = "";
		$param = array();
		$dialect = $this->getSQLDialect();
		
		if( is_string($queryArray) ) {
			$sql = $queryArray;
		} else {
			if( isset($queryArray[$dialect]) ) {
				$sql = $queryArray[$dialect];
			} else {
				throw new \Exception("Missing query for active sql_dialect");
			}
		}

		if(isset($parameters[$dialect])) {
			$parameters = $parameters[$dialect];
		} else {
			// If we have an associative array but not the current dialect, use an empty array
			if( array_keys($parameters) !== range(0, count($parameters) - 1) ) {
				$parameters = array();
			}
		}

		$this->queries[] = array('name' => $name, 'sql' => $sql, 'parameters' => $parameters, 'rowcount' => 0, 'duration' => 0);
		$startTime = microtime(true);  
		$rows = array_map(array($this, 'normalizeRows'), $this->callFetchFunction($sql, $parameters));
		$endTime = microtime(true);  

		$this->queries[count($this->queries)-1]['rowcount'] = count($rows);
		$this->queries[count($this->queries)-1]['duration'] = number_format($endTime - $startTime, 10);
		return $rows; 

	}


	

	/**
	 * Get the current SQL dialect
	 * @return string
	 */
	public function getSQLDialect() {
		return $this->options['sql_dialect'];
	}

	/**
	 * Call the current query function
	 * @param  string $query    
	 * @param  array $parameters
	 * @return array
	 */
	private function callFetchFunction($query, $parameters) {
		return $this->options['query']($query, $parameters);
	}


}