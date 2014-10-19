<?php

namespace Dlid\DbYuml;


class CCache {

	private $neverCache = false;
	private $cachepath = null;
	private $cachetime = null;
	private $cachefile = null;
	private $imagefile = null;
	private $hash = null;

	private $cachedHash = null;


	public function __construct($options) {

		$this->neverCache = (isset($options['force']) && $options['force'] === true);
		$this->cachetime = $options['cachetime'];

		// The cache will be enabled if a cachepath is specified
		if( isset($options['cachepath']) ) {
			$this->cachepath = $options['cachepath'];

			$dirInfo = pathinfo($this->cachepath);
			$this->cachefile = $dirInfo['dirname'] . DIRECTORY_SEPARATOR . $dirInfo['basename'] . '.cache';
			$this->imagefile = $dirInfo['dirname'] . DIRECTORY_SEPARATOR . $dirInfo['basename'] . '.png';
		}

		$tableFormat = $options['formatTableName'];
		$columnFormat = $options['formatColumnName'];

		// Dummy columns and  table to determine if format functions are changed
		$dummyTable = new CTable('dummytable');
		$dummyColumn1 = new CColumn('dummycolumn1','TYPE', true, false, true);
		$dummyColumn2 = new CColumn('dummycolumn2','TYPE', false, true, true);
		$dummyColumn3 = new CColumn('dummycolumn3','TYPE', false, true, false);
		$dummyColumn4 = new CColumn('dummycolumn4','TYPE', true, false, false);

		// Create a hash of the options to determine if any options
		// that will alter the output image has changed
		$this->hash = md5(implode(', ', [
			$this->cachepath,
			$options['style'], 
			$options['scale'], 
			$options['sql_dialect'], 
			$tableFormat($dummyTable), 
			$columnFormat($dummyColumn1),
			$columnFormat($dummyColumn2),
			$columnFormat($dummyColumn3),
			$columnFormat($dummyColumn4)]));
		
	}


	/**
	 * Retreive the currently cached DslText
	 * or null if cache does not exist or has expired
	 * @return string The DslText from cache
	 */
	public function getDslText() {

		// Return null if caching is disabled
		if(!$this->isEnabled()) { return null; }

		$cachepath = $this->cachefile;


		if(is_file($cachepath)) {
			$file_content = @file_get_contents($cachepath);
			if( $file_content) {
				$cacheObject = json_decode($file_content);
				if( !$cacheObject ) {
					throw new \Exception("Could not decode JSON from " . $cachepath);
				}

				// Check the hash
				if( $cacheObject->hash == $this->hash) {
					// Check if the cache is expired.
					$expirationTime = strtotime ( $this->cachetime, $cacheObject->timestamp );
					if( $expirationTime ) {
						if( time() < $expirationTime  ) {
							return $cacheObject->dslText;
						}
					}
				}
			}
		}
		return null;
	}

	public function disableCache(){
		$this->neverCache = true;
	}

	/**
	 * Write the given Dsl text to cache
	 * @param  string $dslText The Dsl Text data to cache
	 */
	public function writeDslText($dslText) {

		if(!$this->isEnabled()) {
			return;
		}

		$cachepath = $this->cachefile;

		$data = json_encode( array( 
			'dslText' => $dslText, 
			'timestamp' => time(),
			'hash' => $this->hash)
		);

		if( is_file($cachepath) && !is_writable($cachepath)) {
			throw new \Exception("File or path is not writeable: " . $cachepath);
		}

		if( !@file_put_contents($cachepath, $data)) {
			throw new \Exception("Could not write cache file " . error_get_last()['message']);
		}
	}

	public function writeImage($data) {

		if(!$this->isEnabled()) {
			return;
		}

		$cachepath = $this->imagefile;

		if( is_file($cachepath) && !is_writable($cachepath)) {
			throw new \Exception("File or path is not writeable: " . $cachepath);
		}

		if( !@file_put_contents($cachepath, $data)) {
			throw new \Exception("Could not write cache file " . error_get_last()['message']);
		}

	}


	public function getImage() {

		if(!$this->isEnabled()) {
			return null;
		}

		// If the Dsl Text is not in cache, then the image is invalid
		if(is_null($this->getDslText())) {
			return null;
		}

		// If the image exists, return its content
		if( is_file($this->imagefile) ) {
			return file_get_contents($this->imagefile);
		}

		return null;

	}



	/**
	 * Check if caching is currently enabled
	 * @return boolean Returns true if cache is enabled
	 */
	public function isEnabled() {
		return $this->neverCache == false && !empty($this->cachepath) ;
	}

}