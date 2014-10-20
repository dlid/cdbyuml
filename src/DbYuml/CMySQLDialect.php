<?php

namespace Dlid\DbYuml;

class CMySQLDialect extends CDialectBase {

	private function getColumns($tableName) {

		$uniqueColumns = [];
		
		// get columns in table
		$columnRows = $this->query("SHOW COLUMNS FROM `{$tableName}`", [], 'List columns in ' . $tableName);

		// get list of unique columns
		$indexRows = $this->query("SHOW INDEXES FROM `{$tableName}`", [], 'List index columns');
		foreach( $indexRows as $indexRow ) {
			if($indexRow['Non_unique'] == "0") {
				$uniqueColumns[] = $indexRow['Column_name'];
			}
		}

		return array($columnRows, $uniqueColumns);
	}

	/**
	 * Retreive all metadata from the MySQL database
	 * @return \Dlid\DbYuml\CTable[] [description]
	 */
	public function execute() {

		// Get tables
		$tableRows = $this->query("SHOW TABLES", [], 'List tables');
		foreach( $tableRows as $tableRow) {

			$tableName = current($tableRow);
			$newTable = new CTable($tableName);

			list($columnRows, $uniqueColumns) = $this->getColumns($tableName);
			$this->addColumns($newTable, $columnRows, $uniqueColumns);
			$this->addMySqlForeignKeys($newTable);
			
			$this[$tableName] = $newTable;
		}
	}

	private function addColumns(&$newTable, $columnRows, $uniqueColumns) {
			foreach( $columnRows as $colRow) {
				$newTable[$colRow['Field']] = new CColumn( $colRow['Field'], 
					$colRow['Type'], 
					($colRow['Null'] == 'NO' ? true : false), 
					$colRow['Key'] == 'PRI', in_array($colRow['Field'], $uniqueColumns) );
			}
	}

	private function addMySqlForeignKeys(&$newTable) {
		$fkRows = $this->query("SELECT\n `column_name`,\n `constraint_name`,\n `referenced_table_name`,\n `referenced_column_name`\nFROM `information_schema`.`key_column_usage`\nWHERE `table_name` = ?\n AND `referenced_table_name` IS NOT NULL;", [$newTable->getName()], 'List Foreign Keys');
		foreach( $fkRows as $fkRow) {
			$newFk = new CForeignKey($fkRow['column_name'], $fkRow['referenced_table_name'], $fkRow['referenced_column_name']);
			$newTable->addForeignKey($newFk);
		}
	}

}