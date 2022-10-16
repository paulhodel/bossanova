<?php

namespace bossanova\Database;

use bossanova\Mail\Mail;
use bossanova\Model\Model;
use bossanova\Config\Config;

class Database
{
    /**
     * The final query statement will be created as a string in this variable
     *
     * @var $query
     */
    public $query;

    /**
     * Global error container
     *
     * @var $error
     */
    public $error = '';

    /**
     * Global debug, set true to show all queries executed
     *
     * @var $debug
     */
    public static $debug = false;

    /**
     * Global debug, set true to show all queries executed
     *
     * @var $debug_email
     */
    public static $debug_email = '';

    /**
     * Database name
     *
     * @var $database_name
     */
    public $database_name = '';

    /**
     * * Database type
     *
     * @var $database_type = ''
     */
    public $database_type = '';

    /**
     * Global instance holder
     *
     * @var array
     */
    private static $instance = [];

    /**
     * Connection resource handler for the database connections
     *
     * @var $connection
     */
    private $connection;

    /**
     * Singleton class
     */
    private function __construct()
    {
    }

    /**
     * Cannot be clonned
     */
    private function __clone()
    {
    }

    /**
     * This method create the first instance, create the connection and return
     * the singleton connection from the second call.
     *
     * @param  string $id     Define an arbitrary instance name
     * @param  array  $config Database connetion configuration
     * @return $this
     */
    public static function getInstance($id = null, $config = null)
    {
        if (!isset(self::$instance[$id]) || !self::$instance[$id] || isset($config)) {
            try {
                if ($config[0] && $config[1] && $config[2] && $config[3] && $config[4]) {
                    // Create a instance of the database connection
                    self::$instance[$id] = new self;

                    $tp   = $config[0];
                    $host = $config[1];
                    $user = $config[2];
                    $pass = $config[3];
                    $name = $config[4];

                    // Bind a PDO connection to our object
                    try {
                        self::$instance[$id]->connection = new \PDO("{$tp}:host={$host};dbname={$name}", $user, $pass);
                    } catch (\PDOException $e) {
                        self::$instance[$id] = null;
                        \bossanova\Error\Error::handler("It was not possible to connect to the database {$name}", $e);
                    }

                    // Keep the database name for this connection
                    self::$instance[$id]->database_name = $config[4];
                    self::$instance[$id]->database_type = $config[0];
                } else {
                    self::$instance[$id] = null;
                }
            } catch (\Exception $e) {
                // Not possible to connect in the database
                self::$instance[$id] = null;
                \bossanova\Error\Error::handler("It was not possible to connect to the database {$config[4]}", $e);
            }
        }

        // Check and set the email debug mode in case of the global bossanova
        // directive exists
        if (defined("BOSSANOVA_DATABASE_DEBUG") && (BOSSANOVA_DATABASE_DEBUG != '')) {
            self::$debug_email = BOSSANOVA_DATABASE_DEBUG;
        }

        return self::$instance[$id];
    }

    /**
     * Set schema
     *
     * @param  string $id
     * @return void
     */
    public function setSchema($schema)
    {
        $this->connection->exec('SET search_path TO ' . $schema);
    }

    /**
     * Return database name for an instance
     *
     * @param  string $id
     * @return string $database_name
     */
    public function getName($id)
    {
        $database_name = self::$instance[$id]->database_name;

        return $database_name;
    }

    /**
     * Set the database debug mode on/off
     * @param boolean $mode true or false
     * @return void
     */
    public function setDebug($mode)
    {
        self::$debug = (boolean)$mode;
    }

    /**
     * Set the database email debug mode on/off. This method allows the developer to receive an email
     * with all debug information when an SQL error occours.
     * @param boolean $mode true or false
     * @return void
     */
    public function setDebugEmail($email)
    {
        self::$debug_email = $email;
    }

    /**
     * Bind the input parameters
     * @param string $val Param content
     * @param boolean $database_function Do nothing because it is a database_function
     * @return string $val Content string binded
     */
    public function bind($val) {
        return $this->parse($val);
    }

    public function parse($val)
    {
        if (is_array($val)) {
            if (count($val)) {
                $data = array();

                foreach ($val as $k => $v) {
                    if (trim($v) === "" || trim($v) === "null") {
                        $data[$k] = "null";
                    } else {
                        if (is_numeric($v)) {
                            $data[$k] = $v;
                        } else {
                            $data[$k] = "'". str_replace("'", "''", $v) . "'";
                        }
                    }
                }

                // Final array with complete data
                $val = $data;
            }
        } elseif (trim($val) === "" || trim($val) === "null") {
            $val = "null";
        } else {
            if (! is_numeric($val)) {
                $val = "'". str_replace("'", "''", $val) . "'";
            }
        }

        return $val;
    }

    /**
     * Keep the table reference name to assembly the query
     *
     * @param string $table Table name
     * @return self
     */
    public function table($tableName)
    {
        $this->query = array();
        $this->query['table'] = $tableName;

        return $this;
    }

    /**
     * Keep the colums names to assembly the query
     *
     * @param  mixed $column string for Select or array for Insert and Updates
     * @return void
     */
    public function column($column, $filter = false)
    {
        if ($filter == true) {
            $column = $this->bind($column);
        }

        $this->query['column'] = $column;

        return $this;
    }

    /**
     * Keep the left join string to assembly the query
     *
     * @param  string $tableName
     * @param  string $arguments
     * @return void
     */
    public function leftJoin($tableName, $arguments)
    {
        if (! isset($this->query['join'])) {
            $this->query['join'] = "";
        }

        $this->query['join'] .= " LEFT JOIN $tableName ON ($arguments)";

        return $this;
    }

    /**
     * Keep the right join string to assembly the query
     *
     * @param  string $tableName
     * @param  string $arguments
     * @return void
     */
    public function rightJoin($tableName, $arguments)
    {
        if (! isset($this->query['join'])) {
            $this->query['join'] = "";
        }

        $this->query['join'] .= " RIGHT JOIN $tableName ON ($arguments)";

        return $this;
    }

    /**
     * Keep the inner join string to assembly the query
     *
     * @param  string $tableName
     * @param  string $arguments
     * @return void
     */
    public function innerJoin($tableName, $arguments)
    {
        if (! isset($this->query['join'])) {
            $this->query['join'] = "";
        }

        $this->query['join'] .= " INNER JOIN $tableName ON ($arguments)";

        return $this;
    }

    /**
     * Keep the group by string to assembly the query
     *
     * @param  string $groupBy
     * @return void
     */
    public function group($groupBy)
    {
        $this->query['group'] = $groupBy;

        return $this;
    }

    /**
     * Keep the order by string to assembly the query
     *
     * @param  string $order Order by string
     * @return void
     */
    public function order($orderBy)
    {
        $this->query['order'] = $orderBy;

        return $this;
    }

    /**
     * Keep the limit by string to assembly the query
     * @param  string $limit
     * @return void
     */
    public function limit($limit)
    {
        $this->query['limit'] = $limit;

        return $this;
    }

    /**
     * Offset
     * @param  string $offset
     * @return void
     */
    public function offset($offset)
    {
        $this->query['offset'] = $offset;

        return $this;
    }

    /**
     * Keep the having by string to assembly the query
     *
     * @param string $having having
     * @return void
     */
    public function having($having)
    {
        $this->query['having'] = $having;

        return $this;
    }

    /**
     * bindParams
     * @return void
     */
    public function bindParam()
    {
        if (! isset($this->query['params'])) {
            $this->query['params'] = [];
        }

        if (! isset($this->query['argument'])) {
            $this->query['argument'] = [];
        }

        $this->query['argument'][] = func_get_args();

        return $this;
    }

    /**
     * Keep the array of arguments to assembly the where in the query
     * @param string $i Number of the argument
     * @param string $k Column name
     * @param mixed $v Number or string value
     * @param string $o Operator (default is an equal)
     * @return void
     */
    public function argument($mixed, $k = null, $v = null, $o = "=")
    {
        if (is_array($mixed)) {
            foreach($mixed as $k => $v) {
                if (is_array($v)) {
                    $this->query['argument'][$k] = "$v[0] $v[2] $v[1]";
                } else {
                    $this->query['argument'][$k] = $v;
                }
            }
        } else {
            $this->query['argument'][$mixed] = "$k $o $v";
        }

        return $this;
    }

    /**
     * Assembly the where with the arguments saved
     * @param string $where Logical argument distribution in the where, ex. ((1) OR (2)) AND (3)
     * @return void
     */
    public function where($where = null)
    {
        if (isset($where)) {
            // Create custom logical operations based on the indexes. example: ((1) OR (2)) AND (3)
            if (isset($this->query['argument'])) {
                // Necessary operation to avoid brackets clash with SQL arguments
                $this->query['where'] = $where;
                $this->query['where'] = str_replace("(", "[[", $this->query['where']);
                $this->query['where'] = str_replace(")", "]]", $this->query['where']);

                foreach ($this->query['argument'] as $k => $v) {
                    // Replace each argument in the logical defined string in the input of this method
                    if (is_array($v)) {
                        $this->query['where'] = str_replace("[[$k]]", "($v[0])", $this->query['where']);
                        // Values
                        preg_match("/:(\w*)/i", $v[0], $test);
                        $this->query['params'][$test[1]] = $v[1];
                    } else {
                        $this->query['where'] = str_replace("[[$k]]", "($v)", $this->query['where']);
                    }
                }

                // Make sure to return the original syntax
                $this->query['where'] = str_replace("[[", "(", $this->query['where']);
                $this->query['where'] = str_replace("]]", ")", $this->query['where']);
            }
        } else {
            // Default is an AND between all arguments
            $where = '';

            if (isset($this->query['argument']) && count($this->query['argument'])) {
                foreach ($this->query['argument'] as $k => $v) {
                    if ($where) {
                        $where .= " AND ";
                    }
                    if (is_array($v)) {
                        $where .= "($v[0])";
                        // Values
                        preg_match("/:(\w*)/i", $v[0], $test);
                        $this->query['params'][$test[1]] = $v[1];
                    } else {
                        $where .= "($v)";
                    }
                }
            }

            $this->query['where'] = $where;
        }

        return $this;
    }

    /**
     * Define a manual query to be executed
     * @param string $query Manual complete query
     * @return void
     */
    public function setQuery($query)
    {
        $this->query = [];
        $this->query['query'] = $query;

        return $this;
    }

    /**
     * Return the query already in this instance
     * @param void
     * @return string $query Complete assembled query considering all inputs using other methods
     */
    public function getQuery()
    {
        $query = "";

        if (isset($this->query['query'])) {
            $query = $this->query['query'];
        }

        return $query;
    }

    /**
     * Assembly a new SELECT usign all definitions
     * @param void
     * @return    (string) stringSQL Statment
     */
    public function select()
    {
        // Create select statement based on the arguments defined so far

        if (! isset($this->query['column'])) {
            $this->query['query'] = "SELECT *";
        } else {
            if (is_array($this->query['column'])){
                $this->query['column'] = implode(',',$this->query['column']);
            }
            $this->query['query'] = "SELECT " . $this->query['column'];
        }

        if (! isset($this->query['where'])) {
            if (isset($this->query['argument']) && count($this->query['argument'])) {
                $this->where();
            }
        }

        if (isset($this->query['table'])) {
            $this->query['query'] .= " FROM " . $this->query['table'];
        }
        if (isset($this->query['join'])) {
            $this->query['query'] .= " " . $this->query['join'];
        }
        if (isset($this->query['where'])) {
            $this->query['query'] .= " WHERE " . $this->query['where'];
        }
        if (isset($this->query['group'])) {
            $this->query['query'] .= " GROUP BY " . $this->query['group'];
        }
        if (isset($this->query['having'])) {
            $this->query['query'] .= " HAVING " . $this->query['having'];
        }
        if (isset($this->query['order'])) {
            $this->query['query'] .= " ORDER BY " . $this->query['order'];
        }
        if (isset($this->query['limit'])) {
            $this->query['query'] .= " LIMIT " . $this->query['limit'];
        }
        if (isset($this->query['offset'])) {
            $this->query['query'] .= " OFFSET " . $this->query['offset'];
        }

        return $this;
    }

    /**
     * Assembly a new SELECT usign all definitions and return the complete SELECT SQL
     * @param void
     * @return void
     */
    public function getSelect()
    {
        $this->select();

        return $this->query['query'];
    }

    /**
     * Assembly a new INSERT usign all definitions
     * @param void
     * @return    (string) stringSQL Statment
     */
    public function insert()
    {
        // Create insert statement based on the arguments defined so far
        $this->query['names'] = "";
        $this->query['values'] = "";

        foreach ($this->query['column'] as $k => $v) {
        // Null values
            if ($v === '') {
                $v = "null";
            }

            // Insert values
            if ($this->query['names'] != "") {
                $this->query['names'] .= ", ";
            }
            $this->query['names'] .= "$k";

            if ($this->query['values'] != "") {
                $this->query['values'] .= ", ";
            }
            $this->query['values'] .= "$v";
        }

        $this->query['query'] = "INSERT INTO " . $this->query['table'] . " (" . $this->query['names'] . ")
            VALUES (" . $this->query['values'] . ")";

        return $this;
    }

    /**
     * Assembly a new INSERT usign all definitions and return the complete INSERT SQL
     * @param void
     * @return    (string) stringSQL Statment
     */
    public function getInsert()
    {
        $this->insert();

        return $this->query['query'];
    }

    /**
     * Assembly a new UPDATE usign all definitions
     * @param    (void)
     * @return    (string) stringSQL Statment
     */
    public function update()
    {
        // Create update statement based on the arguments defined so far
        $this->query['set'] = '';

        foreach ($this->query['column'] as $k => $v) {
            if ($this->query['set'] != "") {
                $this->query['set'] .= ", ";
            }

            $this->query['set'] .= "$k = $v";
        }

        $this->query['query'] = "UPDATE "  .$this->query['table'] . " SET " . $this->query['set'];

        if (!isset($this->query['where'])) {
            if (isset($this->query['argument']) && count($this->query['argument'])) {
                $this->Where();
            }
        }

        if (isset($this->query['where'])) {
            $this->query['query'] .= " WHERE " . $this->query['where'];
        }

        return $this;
    }

    /**
     * Assembly a new UPDATE usign all definitions and return the complete UPDATE SQL
     * @param    (void)
     * @return    (string) stringSQL Statment
     */
    public function getUpdate()
    {
        $this->update();

        return $this->query['query'];
    }

    /**
     * Assembly a new DELETE usign all definitions
     * @param    (void)
     * @return    (string) stringSQL Statment
     */
    public function delete()
    {
        // Create delete statement based on the arguments defined so far

        $this->query['query'] = "DELETE FROM " . $this->query['table'];

        if (!isset($this->query['where'])) {
            if (isset($this->query['argument']) && count($this->query['argument'])) {
                $this->Where();
            }
        }

        if (isset($this->query['where'])) {
            $this->query['query'] .= " WHERE " . $this->query['where'];
        }

        return $this;
    }

    /**
     * Assembly a new DELETE usign all definitions and return the complete DELETE SQL
     * @param    (void)
     * @return    (string)    SQL Statment
     */
    public function getDelete()
    {
        $this->delete();

        return $this->query['query'];
    }

    /**
     * Check if the record exists and decide between insert and update
     * @param    (void)
     * @return    (string)    SQL Statment
     */
    public function checkAndSave($debug = null, $pk = null, $sq = null)
    {
        $id = 0;

        $this->select();
        $result = $this->execute($debug);

        if ($row = $this->fetch_assoc($result)) {
            if (isset($row[$pk])) {
                $id = $row[$pk];
            }
            $this->update();
            $this->execute($debug);
        } else {
            $this->insert();
            $result = $this->execute($debug);
            $id = $this->insert_id($sq);
        }

        return $id;
    }

    /**
     * Begin transaction
     * @param void
     * @return void
     */
    public function begin()
    {
        $this->connection->beginTransaction();
    }

    /**
     * Comite the transaction
     * @param void
     * @return void
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * Rollback the transaction
     * @param void
     * @return void
     */
    public function rollBack()
    {
        $this->connection->rollBack();
    }

    /**
     * Execute the query in the memory
     * @param int $debug 0 => Normal execution
     *                      1 => Return the query without any execution
     *                      2 => Execute and print the query and print any erros
     *                      3 => Execute the query and print any erros and exit the script execution
     * @return resource $result Query result #Resource
     */
    public function execute($debug = 0)
    {
        // Check status from global debug
        if (self::$debug == true) {
            $debug = 2;
        }

        // In case no statement was created, assume it is a select statement
        if (!isset($this->query['query']) || !$this->query['query']) {
            $this->select();
        }

        // Prepare and execute query
        if ($debug == 1) {
            $result = $this->query['query'];
        } else {
            $result = $this->connection->prepare($this->query['query']);

            if (isset($this->query['params'])) {
                foreach ($this->query['params'] as $k => $v) {
                    $result->bindValue(":$k", $v);
                }
            }

            $i = microtime(true);
            $result->execute();
            $f = microtime(true);
            $row = $result->errorInfo();

            if ($debug == 2) {
            // Debug mode two show SQL debug information
                $t = $f - $i;

                echo $this->query['query'] . "<br>\n($t)<br>\n". $row[1] . " " . $row[2];
                if (isset($this->query['params'])) {
                    print_r($this->query['params']);
                }
            } elseif ($debug == 3) {
                // Debug mode three interrupt the script if any error is found
                if ($row[1] && $row[2]) {
                    $t = $f - $i;

                    echo $this->query['query'] . "<br>\n($t)<br>\n". $row[1] . " " . $row[2];
                    if (isset($this->query['params'])) {
                        print_r($this->query['params']);
                    }
                    exit;
                }
            }

            // Check if there is any error in the SQL
            if ($row[1] && $row[2]) {
                if ($this->error != $row[1] . " " . $row[2]) {
                    // If is defined any email, send this error by email
                    if (self::$debug_email) {
                        $email = self::$debug_email;

                        $server = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';

                        // Tracking SQL execution time
                        $t = $f - $i;

                        // Get debug string information
                        $debug_text = $this->errorInformation();

                        // Preparent email content
                        $text = $this->query['query'] . "<br>\n($t)<br>\n". $row[1] . " " . $row[2] . "$debug_text\n";

                        // Get preferable mail adapter
                        $adapter = Config::get('mail');
                        // Create instance
                        $mail = new Mail($adapter);
                        // Send debug email
                        $mail->sendmail($email, "Bossanova::debug ($server)", "$text", $email);
                    }
                }

                // Set global error
                $this->error = $row[1] . " " . $row[2];
            }
        }

        return $result;
    }

    public function errorInformation()
    {
        // Debug information string
        $trace = debug_backtrace();

        // String to be returned
        $debug_text  = "\n<br>";
        $debug_text .= "GET<br>" . print_r($_GET, true);
        $debug_text .= "POST<br>" . print_r($_POST, true);
        $debug_text .= "SERVER<br>" . print_r($_SERVER, true);
        $debug_text .= "DEBUG<br>" . print_r($trace, true);

        if (isset($_SESSION)) {
            $debug_text .= "SESSION<br>" . print_r($_SESSION, true);
        }

        return $debug_text;
    }

    /**
     * Return the numbers of rows from the select
     * @param string $pk primary key to base the counting
     * @return int $total total number
     */
    public function rows($pk)
    {
        // Quantity of rows: postgresql compatibility
        $query = "SELECT COUNT(code) AS total FROM (SELECT $pk AS code";

        if (isset($this->query['table'])) {
            $query .= " FROM " . $this->query['table'];
        }
        if (isset($this->query['join'])) {
            $query .= " " . $this->query['join'];
        }
        if (isset($this->query['where'])) {
            $query .= " WHERE " . $this->query['where'];
        }
        $query .= " GROUP BY $pk) t";

        $result = $this->connection->prepare($query);
        $result->execute();
        $row = $this->fetch_assoc($result);

        return $row['total'];
    }

    /**
     * Return the last id based on the sequence for postgresql or get last id for mysql
     * @param string sequence used for postgres
     * @return int return the for the inserted record
     */
    public function insert_id($result = null)
    {
        // Mysql and PostgreSQL have a different approach.
        if (is_string($result)) {
            $id = $this->connection->lastInsertId($result);
        } else {
            $id = $this->connection->lastInsertId();
        }

        return $id;
    }

    /**
     * Get the next id
     * @param string sequence used for postgres
     * @return int return the for the inserted record
     */
    public function nextId($seq = null)
    {
        // Mysql and PostgreSQL have a different approach.
        if (is_string($seq)) {
            $result = $this->connection->prepare("select nextval('$seq') as id");
            $result->execute();
            $row = $this->fetch_assoc($result);
            $id = $row['id'];
        } else {
            // @TODO mysql
            $id = $this->connection->lastInsertId();
        }

        return $id;
    }

    /**
     * Return the record fetched in an associative array
     * @param resource $result resource from the execution
     * @return array $row2 associative array with all the record
     */
    public function fetch_assoc($result)
    {
        return $result->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Return the record fetched in an associative array
     * @param resource $result resource from the execution
     * @return array $row2 associative array with all the record
     */
    public function fetch_row($result)
    {
        return $result->fetch(\PDO::FETCH_NUM);
    }

    /**
     * Return all records in a multiple array
     * @param resource $result resource from the execution
     * @return array $row2 associative array with all the records
     */
    public function fetch_assoc_all($result)
    {
        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return the record number
     * @param mixed $result resource to be used on mysql and a string to count the records for postgresql
     * @return integer number of records
     */
    public function num_rows($result)
    {
        if (is_string($result)) {
            return $this->rows($result);
        } else {
            return $result->fetchColumn();
        }
    }

    /**
     * Affected rows
     * @return integer number of affected rows
     */
    public function rowCount($result)
    {
        return $result->rowCount();
    }

    /**
     * Get the table information
     *
     * @param  string $tableName
     * @return mixed  $tableInfo
     */
    public function getTableInfo($tableName)
    {
        $row = null;

        // Find primary key and keep in the session for future use
        if (DB_CONFIG_TYPE == 'mysql') {
            $this->setQuery("SHOW KEYS FROM $tableName WHERE Key_name = 'PRIMARY'");
            $result = $this->execute();
            $row = $this->fetch_assoc($result);
        } elseif (DB_CONFIG_TYPE == 'pgsql') {
            $query = "SELECT * FROM information_schema.table_constraints tc
                JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name)
                JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
                AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
                WHERE constraint_type = 'PRIMARY KEY' and tc.table_name = '$tableName'";
            $this->setQuery($query);
            $result = $this->execute();
            $row = $this->fetch_assoc($result);
        }

        return $row;
    }

    /**
     * Get the table information
     *
     * @param  string $tableName
     * @return mixed  $tableInfo
     */
    public function getColumns($tableName)
    {
        $data = [];

        // Find primary key and keep in the session for future use
        if ($this->database_type == 'mysql') {
            $this->setQuery("DESCRIBE $tableName");
            $result = $this->execute();
        } else if ($this->database_type == 'pgsql') {
            $query = "select * from INFORMATION_SCHEMA.COLUMNS where table_name ='$tableName';";
            $this->setQuery($query);
            $result = $this->execute();
        }

        while ($row = $this->fetch_assoc($result)) {
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get the primary key from the defined table
     *
     * @param  string $tableName
     * @return string $primaryKey
     */
    public function getPrimaryKey($tableName)
    {
        // Get the table info
        $column_name = null;
        if ($row = $this->getTableInfo($tableName)) {
            $column_name = isset($row['Column_name']) ? $row['Column_name'] : $row['column_name'];
        }

        return $column_name;
    }

    /**
     * Create automatic models in case table match name
     * @param string $name
     * @param object $value
     */
    public function __get($name)
    {
        if (! isset($this->{$name})) {
            if ($this->getTableInfo($name)) {
                $this->{$name} = new Model($this, $name);
            } else {
                $this->{$name} = false;
            }
        }

        return $this->{$name};
    }
}
