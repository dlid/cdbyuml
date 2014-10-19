<?php

namespace Dlid\DbYuml;


class CForeignKey {

	private $columnName;
	private $foreignTableName;
	private $foreignColumnName;

	public function __construct($columnName, $foreignTableName, $foreignColumnName) {
		$this->columnName = $columnName;
		$this->foreignTableName = $foreignTableName;
		$this->foreignColumnName = $foreignColumnName;
	}

	/**
	 * Get the name of the local column
	 * @return string The name of the column
	 */
	public function getColumnName() {
		return $this->columnName;
	}

	/**
	 * Get the name of the foreign table
	 * @return string The table name
	 */
	public function getForeignTableName() {
		return $this->foreignTableName;
	}

	/**
	 * Get the name of the foreign column
	 * @return string The column name
	 */
	public function getForeignColumnName() {
		return $this->foreignColumnName;
	}


}