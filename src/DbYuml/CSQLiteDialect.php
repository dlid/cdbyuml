<?php

namespace Dlid\DbYuml;

class CSQLiteDialect extends CDialectBase {

	private function getColumns($tableName) {

		$uniqueColumns = [];
		// get columns in table
		$columnRows = $this->query("PRAGMA table_info({$tableName})", [], 'List columns in ' . $tableName);

		// get list of unique columns
		$indexInfo = $this->query("SELECT *\nFROM sqlite_master\nWHERE [type] = 'index' AND [tbl_name] = ? AND [sql] <> ''\nORDER BY name;", array($tableName), 'List index columns');
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

		return array($columnRows, $uniqueColumns);
	}


	private function addColumns(&$newTable, $columnRows, $uniqueColumns) {
			foreach( $columnRows as $colRow) {
				$name = $colRow['name'];
				$type = $colRow['type'];
				$notnull = intval($colRow['notnull']) == 1 ? true : false;
				$pk = intval($colRow['pk']) == 1 ? true : false;

				$newColumn = new CColumn( $name, $type, $notnull, $pk, in_array($name, $uniqueColumns) );
				$newTable[$name] = $newColumn;
			}
	}

	private function addForeignKeys(&$newTable) {
		// List foreign keys
		$tableName = $newTable->getName();
		$fkRows = $this->query("PRAGMA foreign_key_list({$tableName})", [], 'List Foreign Keys');
		foreach( $fkRows as $fkRow) {
			$localColumn = $fkRow['from'];
			$foreignTable = $fkRow['table'];
			$foreignColumn = $fkRow['to'];

			$newFk = new CForeignKey($localColumn, $foreignTable, $foreignColumn);
			$newTable->addForeignKey($newFk);
		}
	}

	/**
	 * Retreive all metadata from the SQLite database
	 * @return \Dlid\DbYuml\CTable[] [description]
	 */
	public function execute() {

		// Get tables
		$tableRows = $this->query("SELECT [name]\nFROM sqlite_master\nWHERE type='table'\n AND [name] != 'sqlite_sequence'", [], 'List tables');
		foreach( $tableRows as $tableRow) {
			$newTable = new CTable($tableRow['name']);
			$tableName = $tableRow['name'];
			
			list($columnRows, $uniqueColumns) = $this->getColumns($tableName);
			$this->addColumns($newTable, $columnRows, $uniqueColumns);
			$this->addForeignKeys($newTable);

			$this[$tableName] = $newTable;
		}
	}

}