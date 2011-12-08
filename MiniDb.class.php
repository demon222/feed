<?php

/**
 * Smaller db access class to avoid using squirrel for speed
 * It's basically a wrapper for mysqli
 * 
 * It uses Exception rather than GeneralException to avoid hacing to load all
 * the stuff that comes with GeneralException
 * 
 * @author _ianbarker
 * @package tools
 * 
 */

class MiniDb {

	private $obj = false;
	private $db;

	private $debug_level = 0;

	/**
	 * 
	 * Creates a new instance of the MiniDb class
	 * The config file is used to get the database connection details
	 * @param string $config_file path to config file
	 * @throws Exception
	 */
	public function __construct($host, $user, $pass, $database) {

		$this->debug_level = 1;

		// connect to host
		$db = new mysqli($host, $user, $pass);
		if ($db->connect_error) {
			throw new Exception($db->connect_error, $db->connect_errno);
		}

		// select db
		$db->select_db($database);
		if ($db->error) {
			throw new Exception($db->error, $db->errno);
		}

		// utf8
		$db->set_charset('utf-8');

		$this->db = $db;

	}

	/**
	 * 
	 * Runs a query on the connected database
	 * @param string $query
	 * @return mysqli_result 
	 * @throws Exception
	 */
	public function query($query) {

		$result = $this->db->query($query, MYSQLI_USE_RESULT);
		if ($result) {

			return $result;

		} else {

			throw new Exception($this->db->error . '<br> ' . $query);

		}
	}

	/**
	 * 
	 * Runs a query on the connected database
	 * Used for multiple results
	 * @param string $query
	 * @return array
	 * @throws Exception
	 */
	public function queryAssoc($query) {

		$result = $this->db->query($query, MYSQLI_USE_RESULT);
		if ($result) {

			$data = array();
			while ($row = $result->fetch_assoc()) {
				$data[] = $row;
			}

			return $data;

		} else {

			throw new Exception($this->db->error);

		}
	}

	/**
	 *
	 * Runs a query on the connected database
	 * Used for single cell results
	 * @param string $query
	 * @return string
	 * @throws Exception
	 */
	public function queryCell($query) {

		$result = $this->db->query($query, MYSQLI_USE_RESULT);
		if ($result) {

			$data = $result->fetch_row();
			return $data[0];

		} else {

			throw new Exception($this->db->error);

		}
	}

	/**
	 * 
	 * Runs mysqli::real_escape_string on the input
	 * @return mixed the escaped input
	 * @param mixed $string
	 */
	public function escape($string) {

		return $this->db->real_escape_string($string);
	}

	public function __destruct() {

		$this->db->close();
	}

	/**
	 * 
	 * Returns the current debug level
	 */
	public function getDebugLevel() {

		return $this->debug_level;
	}

}
