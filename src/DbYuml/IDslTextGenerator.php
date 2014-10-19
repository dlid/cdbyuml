<?php

namespace Dlid\DbYuml;

interface IDslTextGenerator {

	/**
	 * Constructor
	 * @param function $tableFormatFn  The function that will format your table name
	 * @param function $columnFormatFn The function that will format your column name
	 */
	public function __construct($tableFormatFn, $columnFormatFn);

	/**
	 * Generate Dsl Text based on given data
	 * @param  CDialectBase $tables The DialectBase generating the tables
	 * @return string The Dsl Text
	 */
	public function execute($tables);

	

}