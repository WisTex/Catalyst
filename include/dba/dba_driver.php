<?php
/**
 * @file dba_driver.php
 * @brief Some database related functions and database classes.
 *
 * This file contains the abstract database driver class dba_driver, the
 * database class DBA and some functions for working with databases.
 */

/**
 * @brief Database classs with database factory method.
 *
 * The factory will return a database driver which is an implementation of the
 * abstract dba_driver class.
 */
class DBA {

	static public $dba = null;
	static public $dbtype = null;
	static public $scheme = 'mysql';
	static public $logging = false;

	static public $install_script = 'schema_mysql.sql';
	static public $null_date = '0001-01-01 00:00:00';
	static public $utc_now = 'UTC_TIMESTAMP()';
	static public $tquot = "`";


	/**
	 * @brief Returns the database driver object.
	 *
	 * @param string $server DB server name (or PDO dsn - e.g. mysqli:foobar.com;)
	 * @param string $port DB port
	 * @param string $user DB username
	 * @param string $pass DB password
	 * @param string $db database name
	 * @param string $dbtype 0 for mysql, 1 for postgres
	 * @param bool $install Defaults to false
	 * @return null|dba_driver A database driver object (dba_pdo) or null if no driver found.
	 */
	static public function dba_factory($server,$port,$user,$pass,$db,$dbtype,$install = false) {

		self::$dba = null;
		self::$dbtype = intval($dbtype);

		if(self::$dbtype == DBTYPE_POSTGRES) {
			if(!($port))
				$port = 5432;

			self::$install_script = 'schema_postgres.sql';
			self::$utc_now = "now() at time zone 'UTC'";
			self::$tquot = '"';
			self::$scheme = 'pgsql';
		}
		else {

			// attempt to use the pdo driver compiled-in mysqli socket
			// if using 'localhost' with no port configured.
			// If this is wrong you'll need to set the socket path specifically
			// using a server name of 'mysql:unix_socket=/socket/path', setting /socket/path
			// as needed for your platform

			if((!($port)) && ($server !== 'localhost'))
				$port = 3306;
		}

		require_once('include/dba/dba_pdo.php');
		self::$dba = new dba_pdo($server,self::$scheme,$port,$user,$pass,$db,$install);

		define('NULL_DATE', self::$null_date);
		define('ACTIVE_DBTYPE', self::$dbtype);
		define('TQUOT', self::$tquot);

		return self::$dba;
	}

}

/**
 * @brief Abstract database driver class.
 *
 * This class gets extended by the real database driver class. We used to have
 * dba_mysql, dba_mysqli or dba_postgres, but we moved to PDO and the only
 * implemented driver is dba_pdo.
 */
abstract class dba_driver {
	// legacy behavior

	public $db;

	public  $debug = 0;
	public  $connected = false;
	public  $error = false;

	/**
	 * @brief Connect to the database.
	 *
	 * This abstract function needs to be implemented in the real driver.
	 *
	 * @param string $server DB server name
	 * @param string $scheme DB scheme
	 * @param string $port DB port
	 * @param string $user DB username
	 * @param string $pass DB password
	 * @param string $db database name
	 * @return bool
	 */
	abstract function connect($server, $scheme, $port, $user, $pass, $db);

	/**
	 * @brief Perform a DB query with the SQL statement $sql.
	 *
	 * This abstract function needs to be implemented in the real driver.
	 *
	 * @param string $sql The SQL query to execute
	 */
	abstract function q($sql);

	/**
	 * @brief Escape a string before being passed to a DB query.
	 *
	 * This abstract function needs to be implemented in the real driver.
	 *
	 * @param string $str The string to escape.
	 */
	abstract function escape($str);

	/**
	 * @brief Close the database connection.
	 *
	 * This abstract function needs to be implemented in the real driver.
	 */
	abstract function close();

	/**
	 * @brief Return text name for db driver
	 *
	 * This abstract function needs to be implemented in the real driver.
	 */
	abstract function getdriver();

	function __construct($server, $scheme, $port, $user,$pass,$db,$install = false) {
		if(($install) && (! $this->install($server, $scheme, $port, $user, $pass, $db))) {
			return;
		}
		$this->connect($server, $scheme, $port, $user, $pass, $db);
	}

	function get_null_date() {
		return \DBA::$null_date;
	}

	function get_install_script() {
		$platform_name = \Zotlabs\Lib\System::get_platform_name();
		if(file_exists('install/' . $platform_name . '/' . \DBA::$install_script))
			 return 'install/' . $platform_name . '/' . \DBA::$install_script;

		return 'install/' . \DBA::$install_script;
	}

	function get_table_quote() {
		return \DBA::$tquot;
	}

	function utcnow() {
		return \DBA::$utc_now;
	}

	function install($server,$scheme,$port,$user,$pass,$db) {
		if (!(strlen($server) && strlen($user))){
			$this->connected = false;
			$this->db = null;
			return false;
		}

		if(strlen($server) && ($server !== 'localhost') && ($server !== '127.0.0.1') && (! strpbrk($server,':;'))) {
			if(! z_dns_check($server)) {
				$this->error = sprintf( t('Cannot locate DNS info for database server \'%s\''), $server);
				$this->connected = false;
				$this->db = null;
				return false;
			}
		}

		return true;
	}

	/**
	 * @brief Sets the database driver's debugging state.
	 *
	 * @param int $dbg 0 to disable debugging
	 */
	function dbg($dbg) {
		$this->debug = $dbg;
	}

	function __destruct() {
		if($this->db && $this->connected) {
			$this->close();
		}
	}

	function quote_interval($txt) {
		return $txt;
	}

	function optimize_table($table) {
		q('OPTIMIZE TABLE '.$table);
	}

	function concat($fld, $sep) {
		return 'GROUP_CONCAT(DISTINCT '.$fld.' SEPARATOR \''.$sep.'\')';
	}

	function escapebin($str) {
		return $this->escape($str);
	}

	function unescapebin($str) {
		return $str;
	}

} // end abstract dba_driver class


//
// Procedural functions
//

function printable($s) {
	$s = preg_replace("~([\x01-\x08\x0E-\x0F\x10-\x1F\x7F-\xFF])~",".", $s);
	$s = str_replace("\x00",'.',$s);
	if(x($_SERVER,'SERVER_NAME'))
		$s = escape_tags($s);

	return $s;
}

/**
 * @brief set database driver debugging state.
 *
 * @param int $state 0 to disable debugging
 */
function dbg($state) {
	global $db;

	if(\DBA::$dba)
		\DBA::$dba->dbg($state);
}

/**
 * @brief Escape strings being passed to DB queries.
 *
 * Always escape strings being used in DB queries. This function returns the
 * escaped string. Integer DB parameters should all be proven integers by
 * wrapping with intval().
 *
 * @param string $str A string to pass to a DB query
 * @return string Return an escaped string of the value to pass to a DB query.
 */
function dbesc($str) {

	if(is_null_date($str))
		$str = NULL_DATE;

	if(\DBA::$dba && \DBA::$dba->connected)
		return(\DBA::$dba->escape($str));
	else
		return(str_replace("'", "\\'", $str));
}
function dbescbin($str) {
	return \DBA::$dba->escapebin($str);
}

function dbunescbin($str) {
	return \DBA::$dba->unescapebin($str);
}

function dbescdate($date) {
	if(is_null_date($date))
		return \DBA::$dba->escape(NULL_DATE);

	return \DBA::$dba->escape($date);
}

function db_quoteinterval($txt) {
	return \DBA::$dba->quote_interval($txt);
}

function dbesc_identifier($str) {
	return \DBA::$dba->escape_identifier($str);
}

function db_utcnow() {
	return \DBA::$dba->utcnow();
}

function db_optimizetable($table) {
	\DBA::$dba->optimize_table($table);
}

function db_concat($fld, $sep) {
	return \DBA::$dba->concat($fld, $sep);
}

function db_use_index($str) {
	return \DBA::$dba->use_index($str);
}

/**
 * @brief Execute a SQL query with printf style args.
 *
 * printf style arguments %s and %d are replaced with variable arguments, which
 * should each be appropriately dbesc() or intval().
 *
 * SELECT queries return an array of results or false if SQL or DB error. Other
 * queries return true if the command was successful or false if it wasn't.
 *
 * Example:
 * @code{.php}$r = q("SELECT * FROM %s WHERE `uid` = %d",
 *         'user', 1);@endcode
 *
 * @param string $sql The SQL query to execute
 * @return bool|array
 */
function q($sql) {

	$args = func_get_args();
	array_shift($args);

	if(\DBA::$dba && \DBA::$dba->connected) {
		$stmt = vsprintf($sql, $args);
		if($stmt === false) {
			db_logger('dba: vsprintf error: ' .
				print_r(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 1), true),LOGGER_NORMAL,LOG_CRIT);
		}
		if(\DBA::$dba->debug)
			db_logger('Sql: ' . $stmt, LOGGER_DEBUG, LOG_INFO);

		return \DBA::$dba->q($stmt);
	}

	/*
	 * This will happen occasionally trying to store the
	 * session data after abnormal program termination
	 */

	db_logger('dba: no database: ' . print_r($args,true),LOGGER_NORMAL,LOG_CRIT);

	return false;
}

/**
 * @brief Raw DB query, no arguments.
 *
 * This function executes a raw DB query without any arguments.
 *
 * @param string $sql The SQL query to execute
 */
function dbq($sql) {

	if(\DBA::$dba && \DBA::$dba->connected)
		$ret = \DBA::$dba->q($sql);
	else
		$ret = false;

	return $ret;
}



// Caller is responsible for ensuring that any integer arguments to
// dbesc_array are actually integers and not malformed strings containing
// SQL injection vectors. All integer array elements should be specifically
// cast to int to avoid trouble.

function dbesc_array_cb(&$item, $key) {
	if(is_string($item)) {
		if(is_null_date($item))
			$item = NULL_DATE;
		$item = dbesc($item);
	}
}


function dbesc_array(&$arr) {
	$bogus_key = false;
	if(is_array($arr) && count($arr)) {
		$matches = false;
		foreach($arr as $k => $v) {
			if(preg_match('/([^a-zA-Z0-9\-\_\.])/',$k,$matches)) {
				logger('bogus key: ' . $k);
				$bogus_key = true;
			}
		}
		array_walk($arr,'dbesc_array_cb');
		if($bogus_key) {
			$arr['BOGUS.KEY'] = 1;
			return false;
		}
	}
	return true;
}

function db_getfunc($f) {
	$lookup = array(
		'rand'=>array(
			DBTYPE_MYSQL=>'RAND()',
			DBTYPE_POSTGRES=>'RANDOM()'
		),
		'utc_timestamp'=>array(
			DBTYPE_MYSQL=>'UTC_TIMESTAMP()',
			DBTYPE_POSTGRES=>"now() at time zone 'UTC'"
		),
		'regexp'=>array(
			DBTYPE_MYSQL=>'REGEXP',
			DBTYPE_POSTGRES=>'~'
		),
		'^'=>array(
			DBTYPE_MYSQL=>'^',
			DBTYPE_POSTGRES=>'#'
		)
	);
	$f = strtolower($f);
	if(isset($lookup[$f]) && isset($lookup[$f][ACTIVE_DBTYPE]))
		return $lookup[$f][ACTIVE_DBTYPE];

	db_logger('Unable to abstract DB function "'. $f . '" for dbtype ' . ACTIVE_DBTYPE, LOGGER_DEBUG, LOG_ERR);
	return $f;
}

function db_load_file($f) {
	// db errors should get logged to the logfile
	$str = @file_get_contents($f);
	$arr = explode(';', $str);
	if($arr) {
		foreach($arr as $a) {
			if(strlen(trim($a))) {
				$r = dbq(trim($a));
			}
		}
	}
}


// The logger function may make DB calls internally to query the system logging parameters.
// This can cause a recursion if database debugging is enabled.
// So this function preserves the current database debugging state and then turns it off
// temporarily while doing the logger() call

function db_logger($s,$level = LOGGER_NORMAL,$syslog = LOG_INFO) {

	if(\DBA::$logging || ! \DBA::$dba)
		return;

	$saved = \DBA::$dba->debug;
	\DBA::$dba->debug = false;
	\DBA::$logging = true;
	logger($s,$level,$syslog);
	\DBA::$logging = false;
	\DBA::$dba->debug = $saved;
}


function db_columns($table) {

	if($table) {
		if(ACTIVE_DBTYPE === DBTYPE_POSTGRES) {
			$r = q("SELECT column_name as field FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '%s'",
				dbesc($table)
			); 
			if($r) {
				return ids_to_array($r,'field');
			}
		}
		else {
			$r = q("show columns in %s",
				dbesc($table)
			);
			if($r) {
				return ids_to_array($r,'Field');
			}
		}
	}

	return [];
}