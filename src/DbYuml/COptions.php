<?php

namespace Dlid\DbYuml;


class COptions extends \ArrayIterator {

	private $options = array();
	private $styles = ['scruffy','nofunky','plain'];
	private $dialects = [
		'sqlite' => '\Dlid\DbYuml\CSQLiteDialect',
		'mysql' => '\Dlid\DbYuml\CMySQLDialect'
	];

	public function __construct($options, $altOptions) {
		$default = [
			'sql_dialect' => 'sqlite',
			'proxy' => null,
			'proxyauth' => null,
			'cachetime' => '5 minutes',
			'style' => 'plain',
			'scale' => 100,
			'cachepath' => null,
			'query' => null,
			'close' => null,
			'force' => false, // Set to true to ignore cached and always fetch a new diagram

			'formatTableName' => array($this, 'formatTableName'),
			'formatColumnName' => array($this, 'formatColumnName'),
			'generator' => '\Dlid\DbYuml\CDslTextGeneratorBasic'
		];

		// Allow to send in PDO object as param 1 and options as param 2
		if(is_a($options, '\PDO')) {
			$tmpOptions = is_array($altOptions) ? $altOptions : array();
			$tmpOptions['query'] = $options;
			$options = $tmpOptions;
		}

		$this->options = array_merge($default, $options);
		$this->ensureOptionStyle($this->options['style']);
		$this->ensureOptionScale($this->options['scale']);
		$this->ensureOptionFormatTableName($this->options['formatTableName']);
		$this->ensureOptionFormatColumnName($this->options['formatColumnName']);
		$this->ensureOptionQuery($this->options);
		$this->ensureOptionGenerator($this->options['generator']);
		$this->ensureOptionSqlDialect($this->options['sql_dialect']);

	}


	/**
	 * Basic function to return the name of the table
	 * @param  CTable $table The table in question
	 * @return string        The name as shown in the diagram
	 */
	public function formatTableName($table) {
		return $table->getName();
	}

	public function formatColumnName($col) {

		$colName = "'" . $col->getName() . "'";
		$pk = $col->getPk() ? '+' : null;
		$type = $col->getType() ? ' ' . strtoupper($col->getType()) : null;
		$null = (!$col->getNullable() ? ' NOT NULL' : null);

		return "{$pk}{$colName}{$type}{$null}";
	}


	/**
	 * Internal function to execute PDO queries when the 
	 * query option is set to a PDO object
	 * @param  string $query      The SQL query to execute
	 * @param  array $parameters  Parameters to bind to the query
	 * @return array             
	 */
	function executePdoQuery($query, $parameters) {
		$stmt = $this->dbh->prepare($query );

		if (!$stmt) {
			echo "Error in preparing query: "
			. $this->dbh->errorCode()
			. " "
			. htmlentities(print_r($this->dbh->errorInfo(), true)) . " " 
			. htmlentities($query);
			exit;
		}

		$res = $stmt->execute($parameters);

		if (!$res) {
			echo "Error in executing query: "
			. $stmt->errorCode()
			. " "
			. htmlentities(print_r($stmt->errorInfo(), true)) . " " 
			. htmlentities($query);

			exit;
		}

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}



	/**
	 * Based on the sql_dialect, get the IDialect class that should be used
	 * @param  string $dialect The friendly name to convert to the class name
	 */
	private function ensureOptionSqlDialect(&$dialect) {
		if( isset( $this->dialects[$dialect] )) {
			$dialect = $this->dialects[$dialect];
		}

		// Attempt to create class
		$className = $dialect;
		if(class_exists($className)) {
			if(get_parent_class($className) == 'Dlid\DbYuml\CDialectBase') {
				$this['dialectClass'] = $className;
			} else {
				throw new \Exception("Class {$className} must extend CDialectBase");
			}
		} else {
			throw new \Exception("Unable to load dialect class " . $className);
		}


	}

	private function ensureOptionStyle($style) {
		if( !in_array($style, $this->styles)) {
			throw new \Exception("Valid 'style' values are " . implode(', ', $this->styles) );
		}
	}

	private function ensureOptionScale($scale) {
		if( !is_numeric($scale) || intval($scale) < 0  ) {
			throw new \Exception("'scale' must be a percentage, where 100 is 'normal'");
		}
	}

	public function ensureOptionFormatTableName($callback) {
		if( !is_callable($callback)) {
			throw new \Exception("'formatTableName' must be a callable function");
		}
	}

	public function ensureOptionFormatColumnName($callback) {
		if( !is_callable($callback)) {
			throw new \Exception("'formatColumnName' must be a callable function");
		}
	}

	public function ensureOptionQuery(&$options) {

		// query parameter
		if( !is_callable($options['query'])) {
			if( is_a($options['query'], '\PDO')) {
				$this->dbh = $options['query'];
				$options['sql_dialect'] = $this->dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);
				$options['query'] = array($this, 'executePdoQuery');
			} else {
				throw new \Exception("Query must be a callable method");
			}
		}
	}

	private function ensureOptionGenerator($className) {
		if( class_exists($className) ) {
			if( in_array('Dlid\DbYuml\IDslTextGenerator', class_implements($className)) ) {
					$this['generatorClass'] = $className;
			} else {
				throw new \Exception("Class {$className} must implement IDslTextGenerator");
			}
		} else {
			throw new \Exception("Generator class does not exist " . $this->options['generator']);
		}
	}


	/**
	 * Set an item by name
	 * @param  string $offset Array key to get
	 * @param  object $value  The value to set
	 */
  public function offsetSet($offset, $value) 
  {
		$this->options[$offset] = $value;
  }

  /**
   * Check if item exists
   * @param  string $offset key name
   * @return bool           True if table exists
   */
  public function offsetExists($offset) {
      return isset($this->options[$offset]);
  }

	/**
	 * Remove an item
	 * @param  string $offset key name
	 */
  public function offsetUnset($offset) 
  {
      unset($this->options[$offset]);
  }

  /**
   * Return a value by name
   * @param  string $offset Value name
   * @return object         The table or null
   */
  public function offsetGet($offset) 
  {
      return isset($this->options[$offset]) 
          ? $this->options[$offset] 
          : null;
  }
}