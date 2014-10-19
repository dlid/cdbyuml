<?php

namespace Dlid\DbYuml;


class CColumn {

	private $name = null;
	private $type = null;
	private $nullable = null;
	private $unique = null;
	private $pk = null;

	public function __construct($name, $type, $nullable, $pk, $unique) {
		$this->name = $name;
		$this->type = $type;
		$this->nullable = $nullable;
		$this->pk = $pk;
		$this->unique = $unique;
	}

	/**
	 * Get the name of the column
	 * @return string The name of the column
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Get the column type
	 * @return string The column type as a text string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get if this column can have null value
	 * @return bool True if column can contain null
	 */
	public function getNullable() {
		return $this->nullable;
	}

	/**
	 * Get if this column is unique in the table
	 * @return bool True if the column is unique
	 */
	public function getUnique() {
		return $this->unique;
	}

	/**
	 * Set if column is unique
	 * @param bool $newValue Set to true if the column is unique
	 */
	public function setUnique($newValue) {
		$this->unique = $value;
	}

	/**
	 * Set if column is nullable
	 * @param bool $newValue Set to true if the column allow null values
	 */
	public function setNullable($newValue) {
		$this->nullable = $newValue;
	}

	/**
	 * Set the column type
	 * @param string $newValue The textual representation of the column type
	 */
	public function setType($newValue) {
		$this->type = $newValue;
	}

	/**
	 * Get if the column is a primary key
	 * @return bool True if it is a primary key
	 */
	public function getPk() {
		return $this->pk;
	}

	/**
	 * Set if the column is a primary key
	 * @param bool $newValue True if it's a primary key
	 */
	public function setPk($newValue) {
		$this->pk = $newVallue;
	}


}