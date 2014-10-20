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
		$tblName = $tbl->getName();
		$colFormat = $this->columnFormat;
		$tableFormat = $this->tableFormat;

		if( in_array($tblName, $this->writtenTables)) {
			if( $eol  == "") {
				$tblName = $tableFormat($tbl);
				return "[{$tblName}]";
			}
			return;
		}

		$this->writtenTables[] = $tblName;
		$nullablecols = array();
		$uniquecols = array();
		$fkDslString = "";
		$tableDslString = "[" . $tableFormat($tbl) . "|";
		$fkcolumns = $tbl->getForeignKeyColumns();

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

		$tableDslString .= ($fkDslString ? "|" . $fkDslString : null) .  "]";
		$this->addForeignKeys($tableDslString, $tbl, $tables, $nullablecols, $uniquecols);
		return $tableDslString . $eol;
	}

	/**
	 * Add foreign keys
	 * @param [type] $tableDslString [description]
	 * @param [type] $tbl            [description]
	 * @param [type] $tables         [description]
	 * @param [type] $nullablecols   [description]
	 * @param [type] $uniquecols     [description]
	 */
	private function addForeignKeys(&$tableDslString, $tbl, $tables, $nullablecols, $uniquecols) {
		$tblName = $tbl->getName();
		foreach( $tbl->getForeignKeys() as $fk ) {
			$rel = in_array($fk->getColumnName(), $nullablecols) ? "0..*-0..1" : "0..*-1";
			$rel = in_array($fk->getColumnName(), $uniquecols) ? "0..1-1" : $rel;

			if(!isset($tables[$fk->getForeignTableName()])) {
				throw new \Exception("table not found");
			}
			$tableDslString.= "\n[$tblName]" . $rel . $this->generateTableDsl($tables, $tables[$fk->getForeignTableName()],  "");
		}


	} 

}