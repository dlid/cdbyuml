<?php

namespace Dlid\DbYuml;

class CYumlHttpRequest {

	private $url;	
	private $proxy = null;
	private $proxyauth = null;

	public function __construct($options) {
		$this->url = "http://yuml.me/diagram/" . $options['style'] . ";scale:" . $options['scale'] . "/class/";
		
		if(isset($options['proxy']) && $options['proxy']!=null) {
			$this->proxy = $options['proxy'];
		}
		if(isset($options['proxyauth']) && $options['proxyauth']!=null) {
			$this->proxyauth = $options['proxyauth'];
		}
	}

	/**
	 * Post Dsl Text to yuml.me and download the image
	 * given in the response
	 * @param  string $dslText The DslText to create the diagram from
	 * @return string          Raw image data
	 */
	public function readImage($dslText) {

		$filename = $this->curl(array(
			CURLOPT_URL => $this->url,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => 'dsl_text='. urlencode($dslText),
			CURLOPT_HTTPHEADER => array('Content-type: application/x-www-form-urlencoded'),
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_RETURNTRANSFER => 1
		));

		$image = $this->curl(array(
			CURLOPT_URL => "http://www.yuml.me/diagram/class/" . $filename,
			CURLOPT_POST => 0,
			CURLOPT_HTTPHEADER => array('Content-type: application/x-www-form-urlencoded'),
			CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_RETURNTRANSFER => 1
		));

		return $image;
	}

	/**
	 * Execute a CURL request given the specified options
	 * @param  array $options Array of CURL options
	 * @return object         Response data
	 */
	private function curl($options) {
		$ch = curl_init();

		if($this->proxy) {
			curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
		}

		if($this->proxyauth) {
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyauth );
		}

		curl_setopt_array($ch,$options);

		// Execute
		$content = curl_exec ( $ch );
		$err = curl_errno ( $ch );
		$errmsg = curl_error ( $ch );
		$httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );

		curl_close ( $ch );

		if( $err ) {
			throw new \Exception("CURL Error: " . $err . " " . $errmsg . " (HTTP Code " . $httpCode . ")");
		}

		return $content;

	}

}