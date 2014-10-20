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

	private $dslText = null;
	private $image = null;
	private $cache = null;
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
	public function setOptions($options, $altOptions = []) {

		$this->options = new COptions($options, $altOptions);

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
			$dbl = new $this->options['dialectClass'];
			$dbl->setQueryFunction(array($this, 'callQueryFunction'));
			$dbl->execute();

			// Generate the Dsl Text using our generator Class
			$className = $this->options['generatorClass'];
			$gen = new $className($this->options['formatTableName'], $this->options['formatColumnName']);
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

		/**
		 * Write Dsl Text to output
		 * @param  boolean $nocache [description]
		 * @return CDbYuml           [description]
		 */
		public function outputText($nocache = false) {

			if( $nocache === true) {
				$this->cache->disableCache();
			}

			$this->execute();

			$text = new CTextOutput($this->dslText, $this->getEditUrl(), $this->queries);
			echo $text;


			return $this;
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