<?php

namespace Dlid\DbYuml;

class CDialectBase extends \ArrayIterator {
	
	protected $queryCallback = null;
	protected $tables = [];
	protected $tableNames = [];
	protected $current = 0;

	public function setQueryFunction($callbackFn) {
		if( is_callable($callbackFn) ) {
			$this->queryCallback = $callbackFn;
		} else {
			throw new \Exception("Parameter was not a callable function");
		}
	}

	/**
	 * Execute the query to retreive a requested dataset 
	 * @param  string $sql        The SQL to execute
	 * @param  array $parameters  Parameters to bind (use ? macros)
	 * @param  string $name       A describing name of the query
	 * @return array              An array of rows (associative array)
	 */
	public function query($sql, $parameters, $name) {
		$fn = $this->queryCallback;
		return $fn($sql, $parameters, $name);
	}

	/**
	 * Get all tables
	 * @return CTable[] An array of available tables
	 */
	public function getTables() {
		return $this->tables;
	}

	//
	// ArrayIterator
	// 

	/**
	 * Rewind the array iterator
	 */
	public function rewind() {
		$this->tableNames = array_keys($this->tables);
		$this->current = 0;
	}

	/**
	 * Get the current iterator item
	 * @return CTable The current table
	 */
	public function current() {
		return $this->tables[$this->tableNames[$this->current]];
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
		return $this->current <  count($this->tableNames);
	}

	/**
	 * Set an item by name
	 * @param  string $offset Array key to get
	 * @param  CTable $value  The CTable to set
	 */
  public function offsetSet($offset, $value) 
  {
      if (is_null($offset)) {
          throw new \Exception("No table name specified");
      } 
      else {
				$this->tables[$offset] = $value;
      }
  }

  /**
   * Get number of tables
   * @return int number of tables available
   */
  public function count() 
  {
      return count($this->tables);
  }

  /**
   * Check if table exists
   * @param  string $offset Table name
   * @return bool           True if table exists
   */
  public function offsetExists($offset) {
      return isset($this->tables[$offset]);
  }

		/**
		 * Remove a table
		 * @param  string $offset Table name
		 */
  public function offsetUnset($offset) 
  {
      unset($this->tables[$offset]);
  }

  /**
   * Return a table by name
   * @param  string $offset Table name
   * @return CTable         The table or null
   */
  public function offsetGet($offset) 
  {
      return isset($this->tables[$offset]) 
          ? $this->tables[$offset] 
          : null;
  }

}