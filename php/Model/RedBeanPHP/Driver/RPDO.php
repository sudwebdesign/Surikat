<?php

namespace Surikat\Model\RedBeanPHP\Driver;

use Surikat\DependencyInjection\MutatorMagic;
use Surikat\Model\SqlFormatter;
use Surikat\Model\RedBeanPHP\Driver as Driver;
use Surikat\Model\RedBeanPHP\Logger as Logger;
use Surikat\Model\RedBeanPHP\QueryWriter\AQueryWriter as AQueryWriter;
use Surikat\Model\RedBeanPHP\RedException\SQL as SQL;
use Surikat\Model\RedBeanPHP\Logger\RDefault as RDefault;
use Surikat\Model\RedBeanPHP\Logger\RDefault\Debug as Debug;
use Surikat\Model\RedBeanPHP\PDOCompatible as PDOCompatible;

/**
 *\PDO Driver
 * This Driver implements the RedBean Driver API
 *
 * @file    RedBean/PDO.php
 * @desc   \PDO Driver
 * @author  Gabor de Mooij and the RedBeanPHP Community, Desfrenes
 * @license BSD/GPLv2
 *
 * (c) copyright Desfrenes & Gabor de Mooij and the RedBeanPHP community
 * This source file is subject to the BSD/GPLv2 License that is bundled
 * with this source code in the file license.txt.
 */
class RPDO implements Driver
{
	use MutatorMagic;
	/**
	* @var integer
	*/
	protected $max;
	
	/**
	 * @var string
	 */
	protected $dsn;

	/**
	 * @var boolean
	 */
	protected $debug = FALSE;

	/**
	 * @var Logger
	 */
	protected $logger = NULL;

	/**
	 * @var\PDO
	 */
	protected $pdo;

	/**
	 * @var integer
	 */
	protected $affectedRows;

	/**
	 * @var integer
	 */
	protected $resultArray;

	/**
	 * @var array
	 */
	protected $connectInfo = [];

	/**
	 * @var boolean
	 */
	protected $isConnected = FALSE;

	/**
	 * @var bool
	 */
	protected $flagUseStringOnlyBinding = FALSE;

	/**
	 * @var string
	 */
	protected $mysqlEncoding = '';

	/**
	 * Binds parameters. This method binds parameters to a\PDOStatement for
	 * Query Execution. This method binds parameters as NULL, INTEGER or STRING
	 * and supports both named keys and question mark keys.
	 *
	 * @param \PDOStatement $statement \PDO Statement instance
	 * @param  array        $bindings   values that need to get bound to the statement
	 *
	 * @return void
	 */
	protected function bindParams( $statement, $bindings )
	{
		foreach ( $bindings as $key => &$value ) {
			if ( is_integer( $key ) ) {
				if ( is_null( $value ) ) {
					$statement->bindValue( $key + 1, NULL,\PDO::PARAM_NULL );
				} elseif ( !$this->flagUseStringOnlyBinding && AQueryWriter::canBeTreatedAsInt( $value ) && $value <= $this->max  ) {
					$statement->bindParam( $key + 1, $value,\PDO::PARAM_INT );
				} else {
					$statement->bindParam( $key + 1, $value,\PDO::PARAM_STR );
				}
			} else {
				if ( is_null( $value ) ) {
					$statement->bindValue( $key, NULL,\PDO::PARAM_NULL );
				} elseif ( !$this->flagUseStringOnlyBinding && AQueryWriter::canBeTreatedAsInt( $value ) && $value <= $this->max  ) {
					$statement->bindParam( $key, $value,\PDO::PARAM_INT );
				} else {
					$statement->bindParam( $key, $value,\PDO::PARAM_STR );
				}
			}
		}
	}

	/**
	 * This method runs the actual SQL query and binds a list of parameters to the query.
	 * slots. The result of the query will be stored in the protected property
	 * $rs (always array). The number of rows affected (result of rowcount, if supported by database)
	 * is stored in protected property $affectedRows. If the debug flag is set
	 * this function will send debugging output to screen buffer.
	 *
	 * @param string $sql      the SQL string to be send to database server
	 * @param array  $bindings the values that need to get bound to the query slots
	 *
	 * @return void
	 *
	 * @throws SQL
	 */
	protected function runQuery( $sql, $bindings, $options = [] )
	{
		$this->connect();
		if($this->Dev_Level->SQL||$this->Dev_Level->DBSPEED)
			$this->debugger()->logOpen();
		
		$sql = str_replace('{#prefix}',$this->DB->getPrefix(),$sql);
		
		if ( $this->debug && $this->logger ) {
			$this->logger->log( $sql, $bindings );
		}
		if($this->Dev_Level->SQL)
			$this->debugger()->log(SqlFormatter::format($sql), $bindings);

		try {
			if ( strpos( 'pgsql', $this->dsn ) === 0 ) {
				$statement = $this->pdo->prepare( $sql, [\PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT => TRUE ] );
			} else {
				$statement = $this->pdo->prepare( $sql );
			}

			$this->bindParams( $statement, $bindings );

			if($this->Dev_Level->DBSPEED)
				$Chrono = $this->getNew('Dev\Chrono');
			$statement->execute();
			if($this->Dev_Level->DBSPEED){
				$this->debugger()->log('<span style="color:#d00;">'.$Chrono->display().'</span>');
				if(strpos($sql,'CREATE')!==0&&strpos($sql,'ALTER')!==0){
					if ( strpos( 'pgsql', $this->dsn ) === 0 ) {
						$explain = $this->pdo->prepare( 'EXPLAIN '.$sql, [\PDO::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT => TRUE ] );
					}
					else {
						$explain = $this->pdo->prepare( 'EXPLAIN '.$sql );
					}
					$this->bindParams( $explain, $bindings );
					$explain->execute();
					$explain = $explain->fetchAll();
					$this->debugger()->log('<span style="color:#333;">'.implode("\n",array_map(function($entry){
						return implode("\n",$entry);
					}, $explain)).'</span>');
				}
			}
			
			$this->affectedRows = $statement->rowCount();

			if ( $statement->columnCount() ) {

				$fetchStyle = ( isset( $options['fetchStyle'] ) ) ? $options['fetchStyle'] : NULL;
				
				if($fetchStyle!==false){
					$this->resultArray = $statement->fetchAll( $fetchStyle );

					if ( $this->debug && $this->logger ) {
						$this->logger->log( 'resultset: ' . count( $this->resultArray ) . ' rows' );
					}
					
					if($this->Dev_Level->SQL)
						$this->debugger()->log('resultset: <span style="color:#d00;">' . count( $this->resultArray ) . ' rows</span>');
				}
				else{
					return $statement;
				}
				
			} else {
				$this->resultArray = [];
			}
		} catch (\PDOException $e ) {
			//Unfortunately the code field is supposed to be int by default (php)
			//So we need a property to convey the SQL State code.
			$err = $e->getMessage();

			if ( $this->debug && $this->logger )
				$this->logger->log( 'An error occurred: ' . $err );
			
			if($this->Dev_Level->MODEL){
				if(!($this->Dev_Level->DBSPEED||$this->Dev_Level->SQL))
					$this->debugger()->logOpen();
					$this->debugger()->log('An error occurred: '.$err);
				if(!$this->Dev_Level->SQL)
					$this->debugger()->log(SqlFormatter::format($sql), $bindings);
				if(!($this->Dev_Level->DBSPEED||$this->Dev_Level->SQL))
					$this->debugger()->logClose();
			}
				
			$exception = new SQL( $err, 0 );
			$exception->setSQLState( $e->getCode() );

			throw $exception;
		}
		
		if($this->Dev_Level->SQL||$this->Dev_Level->DBSPEED)
			$this->debugger()->logClose();
	}

	/**
	 * Try to fix MySQL character encoding problems.
	 * MySQL < 5.5 does not support proper 4 byte unicode but they
	 * seem to have added it with version 5.5 under a different label: utf8mb4.
	 * We try to select the best possible charset based on your version data.
	 */
	protected function setEncoding()
	{
		$driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME );
		$version = floatval( $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION ) );

		if ($driver === 'mysql') {
			$encoding = ($version >= 5.5) ? 'utf8mb4' : 'utf8';
			$this->pdo->setAttribute(\PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES '.$encoding ); //on every re-connect
			$this->pdo->exec(' SET NAMES '. $encoding); //also for current connection
			$this->mysqlEncoding = $encoding;
		}
	}

	/**
	 * Returns the best possible encoding for MySQL based on version data.
	 *
	 * @return string
	 */
	public function getMysqlEncoding()
	{
		return $this->mysqlEncoding;
	}

	/**
	 * Constructor. You may either specify dsn, user and password or
	 * just give an existing\PDO connection.
	 * Examples:
	 *    $driver = new RPDO($dsn, $user, $password);
	 *    $driver = new RPDO($existingConnection);
	 *
	 * @param string|object $dsn    database connection string
	 * @param string        $user   optional, usename to sign in
	 * @param string        $pass   optional, password for connection login
	 *
	 */
	public function __construct( $dsn, $user = NULL, $pass = NULL )
	{
		if ( is_object( $dsn ) ) {
			$this->pdo = $dsn;

			$this->isConnected = TRUE;

			$this->setEncoding();
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION );
			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC );

			// make sure that the dsn at least contains the type
			$this->dsn = $this->getDatabaseType();
		} else {
			$this->dsn = $dsn;

			$this->connectInfo = [ 'pass' => $pass, 'user' => $user ];
		}
		
		//PHP 5.3 PDO SQLite has a bug with large numbers:
		if ( strpos( $this->dsn, 'sqlite' ) === 0 && PHP_MAJOR_VERSION === 5 && PHP_MINOR_VERSION === 3) {
			$this->max = 2147483647; //otherwise you get -2147483648 ?! demonstrated in build #603 on Travis.
		} elseif ( strpos( $this->dsn, 'cubrid' ) === 0 ) {
			$this->max = 2147483647; //bindParam in pdo_cubrid also fails...
		} else {
			$this->max = PHP_INT_MAX; //the normal value of course (makes it possible to use large numbers in LIMIT clause)
		}
	}

	/**
	 * Whether to bind all parameters as strings.
	 *
	 * @param boolean $yesNo pass TRUE to bind all parameters as strings.
	 *
	 * @return void
	 */
	public function setUseStringOnlyBinding( $yesNo )
	{
		$this->flagUseStringOnlyBinding = (boolean) $yesNo;
	}

	/**
	 * Establishes a connection to the database using PHP\PDO
	 * functionality. If a connection has already been established this
	 * method will simply return directly. This method also turns on
	 * UTF8 for the database and\PDO-ERRMODE-EXCEPTION as well as
	 *\PDO-FETCH-ASSOC.
	 *
	 * @throws\PDOException
	 *
	 * @return void
	 */
	public function connect()
	{
		if ( $this->isConnected ) return;
		try {
			$user = $this->connectInfo['user'];
			$pass = $this->connectInfo['pass'];

			$this->pdo = new\PDO(
				$this->dsn,
				$user,
				$pass
			);

			$this->setEncoding();
			$this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, TRUE );
			//cant pass these as argument to constructor, CUBRID driver does not understand...
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC);

			$this->isConnected = TRUE;
		} catch (\PDOException $exception ) {
			$matches = [];

			$dbname  = ( preg_match( '/dbname=(\w+)/', $this->dsn, $matches ) ) ? $matches[1] : '?';
			$msg = 'Could not connect to database (' . $dbname . ').';
			if($this->Dev_Level->MODEL)
				$msg .= ' '.$exception->getMessage();
			throw new\PDOException( $msg, $exception->getCode() );
		}
	}

	/**
	 * Directly sets PDO instance into driver.
	 * This method might improve performance, however since the driver does
	 * not configure this instance terrible things may happen... only use
	 * this method if you are an expert on RedBeanPHP, PDO and UTF8 connections and
	 * you know your database server VERY WELL.
	 *
	 * @param PDO $pdo PDO instance
	 *
	 * @return void
	 */
	public function setPDO( \PDO $pdo ) {
		$this->pdo = $pdo;
	}

	/**
	 * @see Driver::GetAll
	 */
	public function GetAll( $sql, $bindings = [] )
	{
		$this->runQuery( $sql, $bindings );

		return $this->resultArray;
	}
	
	function fetch($sql, $bindings = []){
		static $statement = null;
		if(!$statement)
			$statement = $this->exec($sql, $bindings);
		$fetch = $statement->fetch();
		if($fetch===false)
			$statement = false;
		return $fetch;
	}
	function exec($sql, $bindings = []){
		return $this->runQuery($sql, $bindings, [
			'fetchStyle' => false,
		]);
	}
	
	/**
	 * @see Driver::GetAssocRow
	 */
	public function GetAssocRow( $sql, $bindings = [] )
	{
		$this->runQuery( $sql, $bindings, [
				'fetchStyle' => \PDO::FETCH_ASSOC
			]
		);

		return $this->resultArray;
	}

	/**
	 * @see Driver::GetCol
	 */
	public function GetCol( $sql, $bindings = [] )
	{
		$rows = $this->GetAll( $sql, $bindings );

		$cols = [];
		if ( $rows && is_array( $rows ) && count( $rows ) > 0 ) {
			foreach ( $rows as $row ) {
				$cols[] = array_shift( $row );
			}
		}

		return $cols;
	}

	/**
	 * @see Driver::GetCell
	 */
	public function GetCell( $sql, $bindings = [] )
	{
		$arr = $this->GetAll( $sql, $bindings );

		$row1 = array_shift( $arr );
		$col1 = array_shift( $row1 );

		return $col1;
	}

	/**
	 * @see Driver::GetRow
	 */
	public function GetRow( $sql, $bindings = [] )
	{
		$arr = $this->GetAll( $sql, $bindings );

		return array_shift( $arr );
	}

	/**
	 * @see Driver::Excecute
	 */
	public function Execute( $sql, $bindings = [] )
	{
		$this->runQuery( $sql, $bindings );

		return $this->affectedRows;
	}

	/**
	 * @see Driver::GetInsertID
	 */
	public function GetInsertID()
	{
		$this->connect();

		return (int) $this->pdo->lastInsertId();
	}

	/**
	 * @see Driver::Affected_Rows
	 */
	public function Affected_Rows()
	{
		$this->connect();

		return (int) $this->affectedRows;
	}

	/**
	 * Toggles debug mode. In debug mode the driver will print all
	 * SQL to the screen together with some information about the
	 * results.
	 *
	 * @param boolean        $trueFalse turn on/off
	 * @param Logger $logger    logger instance
	 *
	 * @return void
	 */
	public function setDebugMode( $tf, $logger = NULL )
	{
		$this->connect();

		$this->debug = (bool) $tf;

		if ( $this->debug and !$logger ) {
			$logger = new RDefault();
		}

		$this->setLogger( $logger );
	}

	/**
	 * Injects Logger object.
	 * Sets the logger instance you wish to use.
	 *
	 * @param Logger $logger the logger instance to be used for logging
	 */
	public function setLogger( Logger $logger )
	{
		$this->logger = $logger;
	}

	/**
	 * Gets Logger object.
	 * Returns the currently active Logger instance.
	 *
	 * @return Logger
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	/**
	 * @see Driver::StartTrans
	 */
	public function StartTrans()
	{
		$this->connect();

		$this->pdo->beginTransaction();
	}

	/**
	 * @see Driver::CommitTrans
	 */
	public function CommitTrans()
	{
		$this->connect();

		$this->pdo->commit();
	}

	/**
	 * @see Driver::FailTrans
	 */
	public function FailTrans()
	{
		$this->connect();

		$this->pdo->rollback();
	}

	/**
	 * Returns the name of database driver for\PDO.
	 * Uses the\PDO attribute DRIVER NAME to obtain the name of the
	 *\PDO driver.
	 *
	 * @return string
	 */
	public function getDatabaseType()
	{
		$this->connect();

		return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME );
	}

	/**
	 * Returns the version number of the database.
	 *
	 * @return mixed $version version number of the database
	 */
	public function getDatabaseVersion()
	{
		$this->connect();

		return $this->pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION );
	}

	/**
	 * Returns the underlying PHP\PDO instance.
	 *
	 * @return\PDO
	 */
	public function getPDO()
	{
		$this->connect();

		return $this->pdo;
	}

	/**
	 * Closes database connection by destructing\PDO.
	 *
	 * @return void
	 */
	public function close()
	{
		$this->pdo         = NULL;
		$this->isConnected = FALSE;
	}

	/**
	 * Returns TRUE if the current\PDO instance is connected.
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->isConnected && $this->pdo;
	}
	
	private $debugger;
	private $DB;
	function debugger(){
		if(!isset($this->debugger))
			$this->debugger = new Debug;
		return $this->debugger;
	}
	function setDB($DB){
		$this->DB = $DB;
	}
}