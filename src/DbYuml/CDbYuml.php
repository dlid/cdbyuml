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
	private $options = null;
	private $styles = ['scruffy','nofunky','plain'];
	private $dialects = [
		'sqlite' => '\Dlid\DbYuml\CSQLiteDialect',
		'mysql' => '\Dlid\DbYuml\CMySQLDialect'
	];

	private $dslText = null;
	private $image = null;
	private $cache = null;

	private $dialectClass = null;
	private $generatorClass = null;

	private $queries = array();

	public function __construct($options = null, $altOptions = null) {
		// Allow to set the options in the constructor
		if( $options!=null ) {
			$this->setOptions($options, $altOptions);
		}
	}

	/**
	 * Basic function to return the name of the table
	 * @param  CTable $table The table in question
	 * @return string        The name as shown in the diagram
	 */
	public function formatTableName($table) {
		return $table->getName();
	}

	public function formatColumnName($col) {

		$colName = "'" . $col->getName() . "'";
		$pk = $col->getPk() ? '+' : null;
		$type = $col->getType() ? ' ' . strtoupper($col->getType()) : null;
		$null = (!$col->getNullable() ? ' NOT NULL' : null);

		return "{$pk}{$colName}{$type}{$null}";
	}

	private function ensureOptionStyle($style) {
		if( !in_array($style, $this->styles)) {
			throw new \Exception("Valid 'style' values are " . implode(', ', $this->styles) );
		}
	}

	private function ensureOptionScale($scale) {
		if( !is_numeric($scale) || intval($scale) < 0  ) {
			throw new \Exception("'scale' must be a percentage, where 100 is 'normal'");
		}
	}

	public function ensureOptionFormatTableName($callback) {
		if( !is_callable($callback)) {
			throw new \Exception("'formatTableName' must be a callable function");
		}
	}

	public function ensureOptionFormatColumnName($callback) {
		if( !is_callable($callback)) {
			throw new \Exception("'formatColumnName' must be a callable function");
		}
	}

	public function ensureOptionQuery(&$options) {

		// query parameter
		if( !is_callable($options['query'])) {
			if( is_a($options['query'], '\PDO')) {
				$this->dbh = $options['query'];
				$options['sql_dialect'] = $this->dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);
				$options['query'] = array($this, 'executePdoQuery');
			} else {
				throw new \Exception("Query must be a callable method");
			}
		}
	}

	private function ensureOptionGenerator($className) {
		if( class_exists($className) ) {
			if(class_implements($className, (string)'Dlid\DbYuml\IDslTextGenerator')) {
					$this->generatorClass = $className;
			} else {
				throw new \Exception("Class {$className} must implement IDslTextGenerator");
			}
		} else {
			throw new \Exception("Generator class does not exist " . $this->options['generator']);
		}
	}

	/**
	 * Based on the sql_dialect, get the IDialect class that should be used
	 * @param  string $dialect The friendly name to convert to the class name
	 */
	private function ensureOptionSqlDialect(&$dialect) {
		if( isset( $this->dialects[$dialect] )) {
			$dialect = $this->dialects[$dialect];
		}

		// Attempt to create class
		$className = $dialect;
		if(class_exists($className)) {
			if(get_parent_class($className) == 'Dlid\DbYuml\CDialectBase') {
				$this->dialectClass = $className;
			} else {
				throw new \Exception("Class {$className} must extend CDialectBase");
			}
		} else {
			throw new \Exception("Unable to load dialect class " . $className);
		}


	}

	/**
	 * Set the options to use 
	 * @param variable $options    array with options or PDO object
	 * @param variable $altOptions array with options if $options parameter is PDO object
	 */
	public function setOptions($options, $altOptions = []) {

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
		'force' => false, // Set to true to ignore cached and always fetch a new diagram

		'formatTableName' => array($this, 'formatTableName'),
		'formatColumnName' => array($this, 'formatColumnName'),
		'generator' => '\Dlid\DbYuml\CDslTextGeneratorBasic'
		];

		// Allow to send in PDO object as param 1 and options as param 2
		if(is_a($options, '\PDO')) {
			$tmpOptions = is_array($altOptions) ? $altOptions : array();
			$tmpOptions['query'] = $options;
			$options = $tmpOptions;
		}

		$this->options = array_merge($default, $options);
		$this->ensureOptionStyle($this->options['style']);
		$this->ensureOptionScale($this->options['scale']);
		$this->ensureOptionFormatTableName($this->options['formatTableName']);
		$this->ensureOptionFormatColumnName($this->options['formatColumnName']);
		$this->ensureOptionQuery($this->options);
		$this->ensureOptionGenerator($this->options['generator']);
		$this->ensureOptionSqlDialect($this->options['sql_dialect']);

		// Initialize cache class
		$this->cache = new CCache($this->options);

	}


	private function getEditUrl() {
		return "http://yuml.me/diagram/" . $this->options['style'] . "/class/draw";
	}

	private function getDslText() {
		$cache = $this->cache;

		// Ensure that we have the Dsl Text
		$dslText = $cache->getDslText();
		if( is_null($dslText) ) {
			
			// Generate database metadata using the current dialect
			$dbl = new $this->dialectClass;
			$dbl->setQueryFunction(array($this, 'callQueryFunction'));
			$dbl->execute();

			// Generate the Dsl Text using our generator Class
			$gen = new $this->generatorClass($this->options['formatTableName'], $this->options['formatColumnName']);
			$dslText = $gen->execute($dbl);
		}

		return $dslText;

	}

	private function getDiagramImage($dslText) {
		$cache = $this->cache;

		// Ensure that we have the Diagram image
		$image = $cache->getImage();
		if(is_null($image)) {
			$http = new CYumlHttpRequest($this->options);
			$image = $http->readImage($dslText);
		}
		return $image;
	}

	/**
	 * Main function that will check cache and
	 * ensure cached or refreshed information depending on status
	 */
	private function execute() {

		if(!$this->options) {
			throw new \Exception("Options not set");
		}

		$cache = $this->cache;

		$dslText = $this->getDslText();
		$image = $this->getDiagramImage($dslText);

		// Ensure cache is updated
		$cache->writeDslText($dslText);
		$cache->writeImage($image);

		// Update globals
		$this->dslText = $dslText;
		$this->image = $image;
	}

		/**
		 * Output the diagram image to the browser
		 * @return boolean Set to true to ignore cache
		 */
		public function outputImage($nocache = false) {
			if( $nocache === true) {
				$this->cache->disableCache();
			}
			$this->execute();
			if(!headers_sent()) {
				header('Content-type: image/png');
				echo $this->image;
			}
		}

		public function outputText($nocache = false) {

			if( $nocache === true) {
				$this->cache->disableCache();
			}

			$this->execute();


			$text = htmlentities($this->dslText, null, 'utf-8');
			$editurl = $this->getEditUrl();

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
					<caption>Executed queries</caption>
					<thead><th>Name</th><th>Query</th><th>Parameters</th><th>Returned rows</th>
					<th>Duration</th></thead><tbody>{$queryHtml}</tbody>
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
				$queryHtml
EOD;
		echo $html;
			exit;
		}


		/**
		 * Save the diagram image to a location of your choice
		 * @param  string $path [description]
		 */
		public function saveImage($path) {
			$this->execute();

			if(!is_writable($path)) {
				throw new \Exception("File or path is not writeable: " . $path);
			}

			CCache::writeFile($path, $this->image);

			return $this;
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
			. htmlentities(print_r($this->dbh->errorInfo(), true)) . " " 
			. htmlentities($query);
			exit;
		}

		$res = $stmt->execute($parameters);

		if (!$res) {
			echo "Error in executing query: "
			. $stmt->errorCode()
			. " "
			. htmlentities(print_r($stmt->errorInfo(), true)) . " " 
			. htmlentities($query);

			exit;
		}

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Transform object to array if it is not an array already
	 * @param  object $row The object to transform
	 * @return array       The resulting array
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
	 * Internally used method to call the specified query function
	 * @param  string $sql          SQL Query 
	 * @param  string[] $parameters Array of parameters
	 * @param  string $name         Describing title of the query
	 * @return array[]              Array of rows
	 */
	public function callQueryFunction($sql, $parameters, $name) {
		
		$this->queries[] = array('name' => $name, 'sql' => $sql, 'parameters' => $parameters, 'rowcount' => 0, 'duration' => 0);
		$startTime = microtime(true);  
		$rows = array_map(array($this, 'normalizeRows'), $this->options['query']($sql, $parameters));
		$endTime = microtime(true);  

		$this->queries[count($this->queries)-1]['rowcount'] = count($rows);
		$this->queries[count($this->queries)-1]['duration'] = number_format($endTime - $startTime, 10);

		return $rows;
	}

}