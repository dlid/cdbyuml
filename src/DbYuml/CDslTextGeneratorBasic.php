<?php
/**
 * Class that will generate the DSL Text for yuml.me
 */
namespace Dlid\DbYuml;

class CDslTextGeneratorBasic implements IDslTextGenerator {
	
	private $writtenTables;


	private $tableFormat = null;
	private $columnFormat = null;

	/**
	 * Constructor
	 * @param function $tableFormatFn  The function that will format your table name
	 * @param function $columnFormatFn The function that will format your column name
	 */
	public function __construct($tableFormatFn, $columnFormatFn) {
		$this->tableFormat = $tableFormatFn;
		$this->columnFormat = $columnFormatFn;
	}

	/**
	 * Generate Dsl Text based on given data
	 * @param  CDialectBase $tables The DialectBase generating the tables
	 * @return string The Dsl Text
	 */
	public function execute($tables) {

		$this->writtenTables = array();

		$dslString = "";
		foreach( $tables as $tbl) {
			$dslString.=$this->generateTableDsl($tables, $tbl);
		}

		$dslString = preg_replace(
				array("/\n/", "/,,/"),
				array(", ",   ","   ),
				trim($dslString));

		return $dslString;

	}

	/**
	 * Crude escape function. It's unclear how to escape commas so they're replaced
	 * @param  string $string String to escape
	 * @return string         Escaped string
	 */
	private function yuaml_escape($string) {
		return preg_replace("/\s{2, }/", '', str_replace(',', ' ', $string));
	}

	/**
	 * Recursive function to generate 
	 * @param  CDialectBase $tables [description]
	 * @param  CTable $tbl    [description]
	 * @param  string $eol    [description]
	 */
	private function generateTableDsl($tables, $tbl, $eol = "\n"){
		$nullablecols = array();
		$uniquecols = array();
		$tblName = $tbl->getName();
		$tableFormat = $this->tableFormat;

		$this->writtenTables[] = $tblName;
		$fkcolumns = $tbl->getForeignKeyColumns();

		list($tableDslString, $fkDslString) = $this->getDslTextForTable($tbl, $nullablecols, $uniquecols, $fkcolumns);

		$returnText = "[" . $tableFormat($tbl) . "|" . $tableDslString;
		$returnText .= ($fkDslString ? "|" . $fkDslString : null) .  "]";
		$this->addForeignKeys($returnText, $tbl, $tables, $nullablecols, $uniquecols);
		return $returnText . $eol;
	}

	/**
	 * Get the Dsl text for the columns in a table
	 * @param  CTable $tbl 	 The table
	 * @return string[]      The table and dsl string
	 */
	private function getDslTextForTable($tbl, &$nullablecols, &$uniquecols, $fkcolumns) {
		$colFormat = $this->columnFormat;
		$fkDslString = '';
		$tableDslString = '';
		$i = 0;
		foreach( $tbl as $col) {
			$separator = $i > 0 ? ";" : null;
			$str = $separator . $colFormat($col);

			if($col->getNullable()) $nullablecols[] = $col->getName();
			if($col->getUnique()) $uniquecols[] = $col->getName();

			if(in_array($col->getName(), $fkcolumns)){
				$fkDslString.=$str;
			} else {
				$tableDslString.=$str;
			}
			$i++;
		}
		return array($tableDslString, $fkDslString);
	}

	/**
	 * Function to generate tables related to another
	 * @param  CDialectBase $tables [description]
	 * @param  CTable $tbl    [description]
	 * @return string         [description]
	 */
	private function generateTableDslRecursive($tables, $tbl) {
		if( in_array($tbl->getName(), $this->writtenTables)) {
				$tableFormat = $this->tableFormat;
				$tblName = $tableFormat($tbl);
				return "[{$tblName}]";
		} else {
			return $this->generateTableDsl($tables, $tbl,  "");
		}
	}

	/**
	 * Add foreign keys
	 * @param string $tableDslString [description]
	 * @param CTable $tbl            [description]
	 * @param CDialectBase $tables         [description]
	 * @param string[] $nullablecols   [description]
	 * @param string[] $uniquecols     [description]
	 */
	private function addForeignKeys(&$tableDslString, $tbl, $tables, $nullablecols, $uniquecols) {
		$tblName = $tbl->getName();
		foreach( $tbl->getForeignKeys() as $fk ) {
			$rel = in_array($fk->getColumnName(), $nullablecols) ? "0..*-0..1" : "0..*-1";
			$rel = in_array($fk->getColumnName(), $uniquecols) ? "0..1-1" : $rel;

			if(!isset($tables[$fk->getForeignTableName()])) {
				throw new \Exception("table not found");
			}
			$tableDslString.= "\n[$tblName]" . $rel . $this->generateTableDslRecursive($tables, $tables[$fk->getForeignTableName()]);
		}


	} 

}