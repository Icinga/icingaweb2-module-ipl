<?php

namespace ipl\Sql;

use BadMethodCallException;
use Exception;
use InvalidArgumentException;
use PDO;
use ipl\Sql\Adapter\AdapterInterface;
use ipl\Sql\Contracts\QuoterInterface;
use ipl\Stdlib\Loader\PluginLoader;

/**
 * Connection to a SQL database using the native PDO for database access
 */
class Connection implements QuoterInterface
{
    use PluginLoader;

    /** @var Config */
    protected $config;

    /** @var PDO */
    protected $pdo;

    /** @var QueryBuilder */
    protected $queryBuilder;

    /** @var AdapterInterface */
    protected $adapter;

    /**
     * Create a new database connection using the given config for initialising the options for the connection
     *
     * {@link init()} is called after construction.
     *
     * @param   Config|\Traversable|array   $config
     *
     * @throws  InvalidArgumentException    If there's no adapter for the given database available
     */
    public function __construct($config)
    {
        $config = $config instanceof Config ? $config : new Config($config);

        $this->addPluginLoader('adapter', __NAMESPACE__ . '\\Adapter');

        $adapter = $this->eventuallyLoadPlugin('adapter', $config->db);

        if ($adapter === null) {
            throw new InvalidArgumentException("Can't load database adapter for '{$config->db}'.");
        }

        $this->adapter = $adapter;
        $this->config = $config;

        $this->init();
    }

    /**
     * Proxy PDO method calls
     *
     * @param   string  $name           The name of the PDO method to call
     * @param   array   $arguments      Arguments for the method to call
     *
     * @return  mixed
     *
     * @throws  BadMethodCallException  If the called method does not exist
     *
     */
    public function __call($name, array $arguments)
    {
        $this->connect();

        if (! method_exists($this->pdo, $name)) {
            $class = get_class($this);
            $message = "Call to undefined method $class::$name";

            throw new BadMethodCallException($message);
        }

        return call_user_func_array([$this->pdo, $name], $arguments);
    }

    /**
     * Initialise the database connection
     *
     * If you have to adjust the connection after construction, override this method.
     */
    public function init()
    {
    }

    /**
     * Get the database adapter
     *
     * @return  AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get the connection configuration
     *
     * @return  Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the query builder for the database connection
     *
     * @return  QueryBuilder
     */
    public function getQueryBuilder()
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = new QueryBuilder($this->adapter);
        }

        return $this->queryBuilder;
    }

    /**
     * Create and return the PDO instance
     *
     * This method is called via {@link connect()} to establish a database connection.
     * If the default PDO needs to be adjusted for a certain DBMS, override this method.
     *
     * @return PDO
     */
    protected function createPdoAdapter()
    {
        $adapter = $this->getAdapter();

        $config = $this->getConfig();

        return new PDO(
            $adapter->getDsn($config),
            $config->username,
            $config->password,
            $adapter->getOptions($config)
        );
    }

    /**
     * Connect to the database, if not already connected
     *
     * @return  $this
     */
    public function connect()
    {
        if ($this->pdo !== null) {
            return $this;
        }

        $this->pdo = $this->createPdoAdapter();

        if ($this->config->charset !== null) {
            $this->exec(sprintf('SET NAMES %s', $this->pdo->quote($this->config->charset)));
        }

        $this->adapter->setClientTimezone($this);

        return $this;
    }

    /**
     * Disconnect from the database
     *
     * @return  $this
     */
    public function disconnect()
    {
        $this->pdo = null;

        return $this;
    }

    /**
     * Check whether the connection to the database is still available
     *
     * @param   bool    $reconnect  Whether to automatically reconnect
     *
     * @return  bool
     */
    public function ping($reconnect = true)
    {
        try {
            $this->exec('SELECT 1');
        } catch (Exception $e) {
            if (! $reconnect) {
                return false;
            }

            $this->disconnect();

            return $this->ping(false);
        }

        return true;
    }

    /**
     * Fetch and return all result rows as sequential array
     *
     * @param   Select|string   $stmt   The SQL statement to prepare and execute.
     *
     * @param   array           $values Values to bind to the statement
     *
     * @return  array
     */
    public function fetchAll($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetchAll();
    }

    /**
     * Fetch and return the first column of all result rows as sequential array
     *
     * @param   Select|string   $stmt   The SQL statement to prepare and execute.
     *
     * @param   array           $values Values to bind to the statement
     *
     * @return  array
     */
    public function fetchCol($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * Fetch and return the first row of the result rows
     *
     * @param   Select|string   $stmt   The SQL statement to prepare and execute.
     *
     * @param   array           $values Values to bind to the statement
     *
     * @return  array
     */
    public function fetchOne($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetch();
    }

    /**
     * Alias of {@link fetchOne()}
     */
    public function fetchRow($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetch();
    }

    /**
     * Fetch and return all result rows as an array of key-value pairs
     *
     * First column is the key and the second column is the value.
     *
     * @param   Select|string   $stmt   The SQL statement to prepare and execute.
     *
     * @param   array           $values Values to bind to the statement
     *
     * @return  array
     */
    public function fetchPairs($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Fetch and return the first column of the first result row
     *
     * @param   Select|string   $stmt   The SQL statement to prepare and execute.
     *
     * @param   array           $values Values to bind to the statement
     *
     * @return  string
     */
    public function fetchScalar($stmt, array $values = null)
    {
        return $this->prepexec($stmt, $values)
            ->fetchColumn(0);
    }

    /**
     * Prepare and execute the given statement
     *
     * @param   Delete|Insert|Select|Update|string  $stmt   The SQL statement to prepare and execute
     * @param   string|array                        $values Values to bind to the statement, if any
     *
     * @return  \PDOStatement
     */
    public function prepexec($stmt, $values = null)
    {
        if ($values !== null && ! is_array($values)) {
            $values = [$values];
        }

        if (is_object($stmt)) {
            list($stmt, $values) = $this->getQueryBuilder()->assemble($stmt);
        }

        $this->connect();

        $sth = $this->pdo->prepare($stmt);
        $sth->execute($values);

        return $sth;
    }

    /**
     * Prepare and execute the given Select query
     *
     * @param   Select  $select
     *
     * @return  \PDOStatement
     */
    public function select(Select $select)
    {
        list($stmt, $values) = $this->getQueryBuilder()->assembleSelect($select);

        return $this->prepexec($stmt, $values);
    }

    /**
     * Insert a table row with the specified data
     *
     * @param   string                      $table  The table to insert data into. The table specification must be in
     *                                              one of the following formats: 'table' or 'schema.table'
     * @param   array|object|\Traversable   $data   Row data in terms of column-value pairs
     *
     * @return  \PDOStatement
     *
     * @throws  \InvalidArgumentException   If data type is invalid
     */
    public function insert($table, $data)
    {
        $insert = (new Insert())
            ->into($table)
            ->values($data);

        return $this->prepexec($insert);
    }

    /**
     * Update table rows with the specified data, optionally based on a given condition
     *
     * @param   string|array                $table      The table to update. The table specification must be in one of
     *                                                  the following formats:
     *                                                  'table', 'table alias', ['alias' => 'table']
     * @param   array|object|\Traversable   $data       The columns to update in terms of column-value pairs
     * @param   mixed                       $condition  The WHERE condition
     * @param   string                      $operator   The operator to combine multiple conditions with,
     *                                                  if the condition is in the array format
     *
     * @return  \PDOStatement
     *
     * @throws  \InvalidArgumentException   If data type is invalid
     */
    public function update($table, array $data, $condition = null, $operator = Sql::ALL)
    {
        $update = (new Update())
            ->table($table)
            ->set($data);

        if ($condition !== null) {
            $update->where($condition, $operator);
        }

        return $this->prepexec($update);
    }

    /**
     * Delete table rows, optionally based on a given condition
     *
     * @param   string|array    $table      The table to delete data from. The table specification must be in one of the
     *                                      following formats: 'table', 'table alias', ['alias' => 'table']
     * @param   mixed           $condition  The WHERE condition
     * @param   string          $operator   The operator to combine multiple conditions with,
     *                                      if the condition is in the array format
     *
     * @return  \PDOStatement
     */
    public function delete($table, $condition = null, $operator = Sql::ALL)
    {
        $delete = (new Delete())
            ->from($table);

        if ($condition !== null) {
            $delete->where($condition, $operator);
        }

        return $this->prepexec($delete);
    }

    /**
     * Begin a transaction
     *
     * @return  bool    Whether the transaction was started successfully
     */
    public function beginTransaction()
    {
        $this->connect();

        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return  bool    Whether the transaction was committed successfully
     */
    public function commitTransaction()
    {
        return $this->pdo->commit();
    }

    /**
     * Roll back a transaction
     *
     * @return  bool    Whether the transaction was rolled back successfully
     */
    public function rollBackTransaction()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Run the given callback in a transaction
     *
     * @param   callable    $callback   The callback to run in a transaction.
     *                                  This connection instance is passed as parameter to the callback
     *
     * @return  mixed                   The return value of the callback
     *
     * @throws  Exception               If and error occurs when running the callback
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();

        try {
            $result = call_user_func($callback, $this);
            $this->commitTransaction();
        } catch (Exception $e) {
            $this->rollBackTransaction();

            throw $e;
        }

        return $result;
    }

    public function quoteIdentifier($identifier)
    {
        return $this->getAdapter()->quoteIdentifier($identifier);
    }
}
