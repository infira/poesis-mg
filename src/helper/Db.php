<?php

namespace Infira\pmg\helper;

use stdClass;
use mysqli;
use Exception;

class Db
{
	/**
	 * @var \mysqli
	 */
	private $mysqli;
	
	public        $dbName = false;
	public $name;
	
	private $lastQueryInfo;
	
	/**
	 * Connect using mysqli
	 *
	 * @param string      $name
	 * @param string      $host
	 * @param string      $user
	 * @param string      $pass
	 * @param string      $db
	 * @param int|null    $port   - if null default port will be used
	 * @param string|null $socket - if null default socket will be used
	 * @throws \Exception
	 */
	public function __construct(string $name, string $host, string $user, string $pass, string $db, int $port = null, string $socket = null)
	{
		$this->name   = $name;
		$this->mysqli = new mysqli($host, $user, $pass, $db, $port, $socket);
		if ($this->mysqli->connect_errno)
		{
			$err = 'Could not connect to database (<strong>' . $db . '</strong>) (' . $this->mysqli->connect_errno . ')' . $this->mysqli->connect_error . ' hostis :("<strong>' . $host . '</strong>")';
			if (!defined("DATABASE_CONNECTION_SUCESS"))
			{
				define("DATABASE_CONNECTION_SUCESS", false);
			}
			throw new Exception($err);
		}
		else
		{
			if (!defined("DATABASE_CONNECTION_SUCESS"))
			{
				define("DATABASE_CONNECTION_SUCESS", false);
			}
		}
		$this->mysqli->set_charset('utf8mb4');
		$this->query("SET collation_connection = utf8mb4_unicode_ci");
		$this->dbName = $db;
	}
	
	public function getName(): string
	{
		return $this->name;
	}
	
	/**
	 * Get currrent connection db name
	 *
	 * @return string
	 */
	public function getDbName(): string
	{
		return $this->dbName;
	}
	
	/**
	 * Close mysql connection
	 */
	public function close()
	{
		$this->mysqli->close();
	}
	
	// Run Queries #
	
	/**
	 * execuete mysqli_query
	 *
	 * @param string $query
	 * @throws \Exception
	 * @return \mysqli_result|bool
	 */
	public function query(string $query)
	{
		return $this->execute($query, "query");
	}
	
	/**
	 * Mysql real query
	 *
	 * @param string $query sql query
	 * @throws \Exception
	 * @return bool
	 */
	public function realQuery(string $query): bool
	{
		return $this->execute($query, "real_query");
	}

	//###################################################### Other helpers
	
	/**
	 * @param mixed $data
	 * @return string
	 */
	public function escape(string $data): string
	{
		return $this->mysqli->real_escape_string($data);
	}
	
	/**
	 * Returns last mysql insert_id
	 *
	 * @see https://www.php.net/manual/en/mysqli.insert-id.php
	 * @return int
	 */
	public function getLastInsertID(): int
	{
		return $this->mysqli->insert_id;
	}
	
	public function getLastQueryInfo(): stdClass
	{
		return $this->lastQueryInfo;
	}
	
	public function debugLastQuery()
	{
		debug($this->lastQueryInfo);
	}
	
	//###################################################### Private methods
	
	/**
	 * @param string $query
	 * @param string $type
	 * @throws \Exception
	 * @return bool|\mysqli_result
	 */
	private function execute(string $query, string $type)
	{
		// $runBeginTime = microtime(true);
		$this->lastQueryInfo          = new stdClass();
		$this->lastQueryInfo->dbName  = $this->dbName;
		$this->lastQueryInfo->runtime = microtime(true);
		if ($type == "query")
		{
			$sqlQueryResult = $this->mysqli->query($query);
		}
		elseif ($type == "real_query")
		{
			$sqlQueryResult = $this->mysqli->real_query($query);
		}
		elseif ($type == "multi_query")
		{
			$sqlQueryResult = $this->mysqli->multi_query($query);
		}
		else
		{
			throw new Exception("Unknown query type", ['queryType' => $type]);
		}
		$this->lastQueryInfo->runtime = microtime(true) - $this->lastQueryInfo->runtime;
		$this->lastQueryInfo->query   = $query;
		
		$db = $this->dbName;
		if ($this->mysqli->error)
		{
			$error = 'SQL "' . $db . '" error : ' . $this->mysqli->error . ' < br><br > ';
			$error .= "SQL \"$db\" query : " . $query;
			throw new Exception(str_replace("\n", '<br>', $error));
		}
		
		return $sqlQueryResult;
	}
	
	public function getMysqli(): mysqli
	{
		return $this->mysqli;
	}
}