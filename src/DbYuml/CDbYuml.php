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

			if(isset($this->options['query'])) {
				#echo $this->getDslText();
			}
		}

		private function getUrl() {
			return "http://yuml.me/diagram/" . $this->options['style'] . ";scale:" . $this->options['scale'] . "/class/";
		}


		public function downloadDiagram() {

			if($this->image !== null && !$this->options['force']) {
				return $this->image;
			}

			$this->getDslText();
			if($this->options['cachepath']) {
				if($this->dsl_cache && $this->dsl_cache == $this->dsl_text) {
					if(is_file($this->getPath('png'))) {
						$this->image = file_get_contents($this->getPath('png'));
					}
					return $this;
				}
			}

			$url = $this->getUrl();

			#header('Content-Type: text/plain'); echo $umlString;exit;

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

			$this->writeToFile('png', $this->image, true);

			return $this;

		}


		public function outputImage() {
			$this->downloadDiagram();
			header('Content-type: image/png');
			echo $this->image;
			exit;
		}

		public function saveImage($path) {
			$this->downloadDiagram();
			file_put_contents($path, $this->image);
			return $this;
		}


		private function writeToFile($extension, $data, $raw = false) {

			if( !$raw) {
				$data = json_encode( array( 
						'data' => $data, 
						'timestamp' => time(),
						'hash' => $this->getHash()));
			}

			file_put_contents($this->getPath($extension), $data);
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

			if( $this->options['cachepath'] && is_file($this->getPath('cache'))) {
					$cache =  $this->readFromFile('cache');
					if( $cache) {
						if( $cache->hash == $this->getHash() ){
							$this->dsl_text = $cache->data;
							$expirationTime = strtotime ( $this->options['cachetime'], $cache->timestamp );
							if( $expirationTime ) {
								if( time() >= $expirationTime  ) {
									$this->dsl_text = null;
									unset($cache);
								} else {
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
					$type = $type ? ' ' . strtoupper($type) : null;
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
					$tableDslString.= "\n[$tblName]" . $rel . $writeYumlString($fk['referenced_table_name'], $tables[$fk['referenced_table_name']], $writeYumlString, "");
				}
				return $tableDslString . $eol;
			};

			$dslString = "";

			foreach( $tables as $tblName => $tbl) {
				$dslString.=$writeYumlString($tblName, $tbl, $writeYumlString);
			}


			if($dslString) {
				$this->writeToFile('cache', $dslString);
			}

			$this->dsl_text = $dslString;
			return $dslString;
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
			. htmlentities(print_r($this->dbh->errorInfo(), 1));
			exit;
		}

		$res = $stmt->execute($parameters);

		if (!$res) {
			echo "Error in executing query: "
			. $stmt->errorCode()
			. " "
			. htmlentities(print_r($stmt->errorInfo(), 1));
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

		$rows = $this->executeDialectQuery([
			'sqlite' => "SELECT [name] FROM sqlite_master WHERE type='table' AND [name] != 'sqlite_sequence'",
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

		$rows = $this->executeDialectQuery([
			'sqlite' => "PRAGMA table_info({$escapedTableName})",
			'mysql' => "SHOW COLUMNS FROM `{escapedTableName}`"
			]);

		$rows = array_map( array($this, 'mapColumnInfo'), $rows);
		$uniqueColumnNames = $this->findUniqueColumnsInTable($tableName);

		foreach($rows as &$row) {
			if( in_array($row['name'], $uniqueColumnNames)) {
				$row['unique'] = 1;
			}
		}

		return $rows;
	}

	/**
	 * List indexes for a table to identify unique foreign keys
	 * @param  string $tableName Name of the tabler
	 * @return array
	 */
	private function findUniqueColumnsInTable($tableName) {
		$uniqueColumns = array();

		// For SQLite we need another query per index to get index columns
		if($this->getSQLDialect() == 'sqlite') {

			$escapedTableName = $this->escapeName($tableName);
			$rows = $this->executeDialectQuery([
				'sqlite' => "PRAGMA index_list({$escapedTableName})",
				'mysql' => "SHOW COLUMN FROM `{escapedTableName}`"
				]);

			$rows = array_map( array($this, 'mapIndexInfo'), $rows);

			$params = array($tableName);
			$macros = array();
			foreach($rows as $row ) {
				$params[] = $row['name'];
				$macros[] = '?';
			}

			$macros = implode(',', $macros);
			$indexInfo = $this->executeDialectQuery("SELECT * FROM sqlite_master WHERE [type] = 'index' AND [tbl_name] = ? AND [name] IN ({$macros}) AND [sql] <> '' ORDER BY name;", $params);
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
			'unique' => $row['unique'],
			'column' => null
			];

			break;
			case 'mysql': 
			throw new \Exception("Not implemented");
			$newRow = [
			'referenced_table_name' => $row['referenced_table_name'],
			'referenced_column_name' => $row['referenced_column_name'],
			'column_name' => $row['column_name']
			];
			break;
		}

		return $row;
	}

	/**
	 * Find all foreign keys for a specific table
	 * @param  string $tableName
	 * @return array
	 */
	public function findForeignKeysInTable($tableName) {
		$tableName = $this->escapeName($tableName);

		$rows = $this->executeDialectQuery([
			'sqlite' => "PRAGMA foreign_key_list({$tableName})",
			'mysql' => "SELECT `column_name`, `constraint_name`, `referenced_table_name` FROM `information_schema`.`key_column_usage` WHERE `table_name` = `{$tableName}`;"
			]);

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
	private function executeDialectQuery($queryArray, $parameters = []) {
		
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
			$param = $parameters[$dialect];
		} else if( is_array($parameters)) {
			$param = $parameters;
		}

		return array_map(array($this, 'normalizeRows'), $this->callFetchFunction($sql, $parameters));

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