<?php
/**
 * Represents a database table
 */
namespace Dlid\DbYuml;


class CTable  extends \ArrayIterator {

	private $name = null;
	private $columns = [];
	private $columnNames = [];
	private $current = 0;
	private $foreignKeys = [];

	public function __construct($name) {
		$this->name = $name;
	}

	public function getName() {
		return $this->name;
	}


	/**
	 * Add a foreign key
	 * @param CForeignKey $foreignKey The foreign key to add
	 */
	public function addForeignKey($foreignKey) {
		$this->foreignKeys[] = $foreignKey;
	}

	/**
	 * Get column names for Foreign Keys
	 * @return string[] Array of column names
	 */
	public function getForeignKeyColumns() {
		$columns = array();
		foreach( $this->foreignKeys as $fk) {
			$columns[] = $fk->getColumnName();
		}
		return $columns;
	}

	/**
	 * Get all foreign keys
	 * @return CForeignKey[] List of foreign keys
	 */
	public function getForeignKeys() {
		return $this->foreignKeys;
	}


	//
	// ArrayIterator
	// 

	/**
	 * Rewind the array iterator
	 */
	public function rewind() {
		$this->columnNames = array_keys($this->columns);
		$this->current = 0;
	}

	/**
	 * Get the current iterator item
	 * @return CTable The current table
	 */
	public function current() {
		return $this->columns[$this->columnNames[$this->current]];
	}

	/**
	 * Get the current iterator item
	 * @return CTable The current table
	 */
	public function key() {
		return $this->columnNames[$this->current];
	}

	/**
	 * Move to the next item
	 */
	public function next() {
		$this->current++;
	}

	/**
	 * Check if there are more items
	 * @return bool Returns true if there are more items
	 */
	public function valid() {
		return $this->current <  count($this->columnNames);
	}

	/**
	 * Set an item by name
	 * @param  string $offset Array key to get
	 * @param  CColumn $value  The CTable to set
	 */
  public function offsetSet($offset, $value) 
  {
      if (is_null($offset)) {
          throw new \Exception("No table name specified");
      } 
      else {
				$this->columns[$offset] = $value;
      }
  }

  /**
   * Get number of columns
   * @return int number of columns available
   */
  public function count() 
  {
      return count($this->columns);
  }

  /**
   * Check if table exists
   * @param  string $offset Table name
   * @return bool           True if table exists
   */
  public function offsetExists($offset) {
      return isset($this->columns[$offset]);
  }

		/**
		 * Remove a table
		 * @param  string $offset Table name
		 */
  public function offsetUnset($offset) 
  {
      unset($this->columns[$offset]);
  }

  /**
   * Return a table by name
   * @param  string $offset Table name
   * @return CColumn         The table or null
   */
  public function offsetGet($offset) 
  {
      return isset($this->columns[$offset]) 
          ? $this->columns[$offset] 
          : null;
  }

}