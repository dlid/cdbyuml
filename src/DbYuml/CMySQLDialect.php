<?php

namespace Dlid\DbYuml;

class CMySQLDialect extends CDialectBase {

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
			
			// List columns
			foreach( $columnRows as $colRow) {
				$name = $colRow['Field'];
				$type = $colRow['Type'];
				$notnull = ($colRow['Null'] == 'NO' ? true : false);
				$pk = $colRow['Key'] == 'PRI';

				$newColumn = new CColumn( $name, $type, $notnull, $pk, in_array($name, $uniqueColumns) );
				$newTable[$name] = $newColumn;
			}


			// List foreign keys
			$fkRows = $this->query("SELECT\n `column_name`,\n `constraint_name`,\n `referenced_table_name`,\n `referenced_column_name`\nFROM `information_schema`.`key_column_usage`\nWHERE `table_name` = ?\n AND `referenced_table_name` IS NOT NULL;", [$tableName], 'List Foreign Keys');
			foreach( $fkRows as $fkRow) {
				$localColumn = $fkRow['column_name'];
				$foreignTable = $fkRow['referenced_table_name'];
				$foreignColumn = $fkRow['referenced_column_name'];

				$newFk = new CForeignKey($localColumn, $foreignTable, $foreignColumn);
				$newTable->addForeignKey($newFk);
			}
			$this[$tableName] = $newTable;
		}
	}

}