<?php
/* Licensed under the Apache License, Version 2.0
 * See the LICENSE and NOTICE file for further information
 */

/**
 * Database factory. Creates database connections based on the
 * configuration settings.
 *
 * Example of a config.yml entry:
 *
 * \code
 * db:
 *    default:
 *        driver: mdb2
 *        dsn: blah@localhost/foo
 *    ordbm:
 *        driver: doctrine
 *        dsn: blubb@localhost/baz
 *        modeldir: localinc/api/model/data
 *    raw:
 *        driver: pdo
 *        dsn: mysql:localhost;dbname=cookie
 *        user: scooby
 *        pass: doo
 * \endcode
 *
 * @config <b>db</b> (hash): Contains named database connections. The
 *         keys are the names, each connection is a hash with the config
 *         values.
 * @config <b>db-><em>conn</em>->driver</b> (string): Specifies the driver
 *         to use. "api_db_" is prepended to the string to get a class
 *         name to load. The default driver is "mdb2". \n
 *         The "conn" part is the connection name and can be
 *         changed to whatever name you desire.
 * @config <b>db-><em>conn</em>->dsn</b> (string): Database source name.
 *         This is the connection string for the database. It's exact
 *         syntax depends on the driver. \n
 *         The "conn" part is the connection name and can be
 *         changed to whatever name you desire.
 */
// TODO delete class?
class api_db {
    /** All instances loaded so far by api_db::factory(). */
    protected static $instances = array();

    public function __construct() {
    }

    /**
     * Returns the database connection specified by $name.
     *
     * @param $name string: Database connection name
     * @param $force bool: Force a new connection even if the connection
     *                     has already been opened.
     * @return DatabaseConnection: Database connection or false if the
     *         database connection doesn't exist. The driver's
     *         getDBConnection() method is used to retrieve that connection.
     */
    public static function factory($config, $name = "default", $force = false, $log = null) {
        if (isset(self::$instances[$name]) && $force == false) {
            return self::$instances[$name];
        }

        $db = $config->db;
        if (empty($db[$name])) {
            return false;
        }


        self::$instances[$name] = self::get($db[$name], $log);
        return self::$instances[$name];
    }

    /**
     * Overwrites an adapter with another.
     * This is especially helpful for testing, as you can set your 'write' and
     * 'read' connection to use you 'test' environment.
     *
     * @param $name string: Database connection name to overwrite
     * @param $name string: Database connection to use instead
     * @return Boolean: True on success, False on error
     */
    public static function setAdapter($name, $newname) {
        $db = api_config::getInstance()->db;
        if (empty($db[$newname])) {
            return false;
        }

        self::$instances[$name] = self::get($db[$newname]);
        return true;
    }

    /**
     * Overwrites an instance for a certain config with a custom one
     * This is helpful if you create your own mock db adapter
     *
     * @param String $name The name of the adapter
     * @param Object $instance The instance to bet set
     */
    public static function setInstance($name, $instance) {
        self::$instances[$name] = $instance;
    }

    /**
     * Clear all loaded database connections. Useful for enforce new
     * connections in testing scenarios.
     */
    public static function reset() {
        self::$instances = array();
    }

    /**
     * Returns a connection from the driver using it's getDBConnection()
     * method.
     *
     * @param $config array: Configuration for the connection to load.
     * @return DatabaseConnection: Database connection retrieved from
     *         the driver.
     */
    protected static function get($config, $log = null) {
        if (empty($config['dsn']) && empty($config['host'])) {
            return false;
        }

        if (empty($config['driver'])) {
            $config['driver'] = "mdb2";
        }

        $driver = "api_db_".$config['driver'];
        $db = new $driver;
        return $db->getDBConnection($config, $log);
    }

}
