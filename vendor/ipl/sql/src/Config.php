<?php

namespace ipl\Sql;

use InvalidArgumentException;
use Traversable;

/**
 * SQL connection configuration
 */
class Config
{
    /**
     * Create a new SQL connection configuration from the given configuration key-value pairs
     *
     * @param   array|Traversable   $config Configuration key-value pairs
     */
    public function __construct($config)
    {
        if (! is_array($config) && ! $config instanceof Traversable) {
            throw new InvalidArgumentException('Config expects array or Traversable');
        }

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Type of the DBMS
     *
     * @var string
     */
    public $db;

    /**
     * Database host
     *
     * @var string
     */
    public $host;

    /**
     * Database port
     *
     * @var int
     */
    public $port;

    /**
     * Database name
     *
     * @var string
     */
    public $dbname;

    /**
     * Username to use for authentication
     *
     * @var string
     */
    public $username;

    /**
     * Password to use for authentication
     *
     * @var string
     */
    public $password;

    /**
     * Character set for the connection
     *
     * If you want to use the default charset as configured by the database, don't set this property.
     *
     * @var string
     */
    public $charset;

    /**
     * PDO connect options
     *
     * Array of key-value pairs that should be set when calling {@link Connection::connect()} in order to establish a DB
     * connection.
     *
     * @var array
     */
    public $options;
}
