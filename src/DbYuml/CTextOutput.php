<?php

namespace Dlid\DbYuml;

class CTextOutput {

	public function __toString() {
		return $this->html;
	}

	/**
	 * Generate the debug text output
	 * @param string $dslText The DSL text to output
	 * @param string $editUrl The URL where to edit the text yourself
	 * @param array $queries  The executed queries
	 */
	function __construct($dslText, $editUrl, $queries) {

		$text = htmlentities($dslText, null, 'utf-8');

		$queryHtml = "";
		foreach( $queries as $query) {
			$sql = htmlentities($query['sql'], null, 'utf-8');
			$duration = htmlentities($query['duration'], null, 'utf-8');
			$rowcount = htmlentities($query['rowcount'], null, 'utf-8');
			$name = htmlentities($query['name'], null, 'utf-8');
			if(count($query['parameters']) > 0) {
				$parameters = "<pre>" . htmlentities(print_r($query['parameters'], true), null, 'utf-8') ."</pre>";
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
			<p><strong>Edit URL</strong><br /><a href='$editUrl' target='_blank'>$editUrl</a> (copy and paste the text above onto this page)</p>
			$queryHtml
EOD;
		$this->html = $html;
	}

}