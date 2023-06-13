<?php

namespace bossanova\Model;

use bossanova\Database\Database;
use bossanova\Model\ModelException;
use bossanova\Redis\Redis;

class Model extends \stdClass
{
    // Database instance
    public $database = null;

    // Table configuration
    public $config = null;

    /**
     * Return the model instance in a object format
     *
     * @param  object $db instance from the database
     * @param  string $table table name
     * @return void
     */
    public function __construct(Database &$instance = null, $tableName = null)
    {
        try {
            if (isset($instance)) {
                $this->database = $instance;
            } else {
                $this->database = Database::getInstance();
            }

            if (! $this->database) {
                throw new \Exception('');
            }
            // Set table configuration
            if (! $this->config) {
                $this->setConfig($tableName);
            }

            // Make it a object
            $this->config = (object) $this->config;

            return $this;
        } catch (\Exception $e) {
            \bossanova\Error\Error::handler('There is no database connection available.', $e);
        }
    }

    /**
     * Return the record as an array
     *
     * @param  integer $id
     * @return object
     */
    public function getById($id)
    {
        // Get empty record
        $data = $this->getMeta();

        // Load record
        if ((int)$id > 0) {
            $result = $this->database->table($this->config->tableName)
                ->argument(1, $this->config->primaryKey, $id)
                ->select()
                ->execute();

            if ($row = $this->database->fetch_assoc($result)) {
                $data = $row;
            }
        }

        return $data;
    }

    /**
     * Return a empty record
     *
     * @param  integer $id
     * @return object
     */
    public function getMeta()
    {
        $data = array();

        $result = $this->database->table($this->config->tableName)
            ->limit(0)
            ->select()
            ->execute();

        for ($i = 0; $i < $result->columnCount(); $i++) {
            $col = $result->getColumnMeta($i);
            $data[$col['name']] = '';
        }

        return $data;
    }

    /**
     * Return an array based on the table
     *
     * @return array
     */
    public function getEmpty()
    {
        return $this->getMeta();
    }

    /**
     * Create the class properties based on the table
     *
     * @return object|string[]
     */
    public function createFromMeta()
    {
        $data = $this->getMeta();

        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }
    }

    /**
     * Return the record in a object format
     *
     * @param  integer
     * @return object
     */
    public function get($id = null)
    {
        // Create empty record
        $this->createFromMeta();

        // Load record data
        if ((int)$id > 0) {
            // Get data from the table
            $result = $this->database->table($this->config->tableName)
                ->argument(1, $this->config->primaryKey, $id)
                ->select()
                ->execute();

            if ($data = $this->database->fetch_assoc($result)) {
                // Update object from data
                $this->config->recordId = $id;
                foreach ($data as $k => $v) {
                    $this->{$k} = $v;
                }
            }
        }

        return $this;
    }

    /**
     * Update or insert the data on the database
     *
     * @return integer last inserted id, sequence or record id
     */
    public function save()
    {
        $column = array();

        // Accepted types
        $acceptedTypes = [
            'boolean',
            'integer',
            'double',
            'string',
            'null'
        ];

        // Binding column types
        foreach ($this as $k => $v) {
            if (in_array(gettype($v), $acceptedTypes)) {
                $column[$k] = $this->database->Bind($v);
            }
        }

        // Check the operation type, insert or update
        if (! $this->config->recordId) {
            // Insert a new record
            $this->database->table($this->config->tableName)
                ->column($column)
                ->insert()
                ->execute();

            // Return id
            $this->config->recordId = $this->database->insert_id($this->config->sequence);
        } else {
            // Update existing record
            $this->database->table($this->config->tableName)
                ->column($column)
                ->argument(1, $this->config->primaryKey, $this->config->recordId)
                ->update()
                ->execute();
        }

        return $this->config->recordId;
    }

    /**
     * Update or insert the data on the database
     *
     * @return integer last inserted id, sequence or record id
     */
    public function flush()
    {
        $this->save();

        // Clear record reference
        $this->config->recordId = 0;
    }

    /**
     * Set the data
     *
     * @param  integer
     * @return object
     */
    public function column($row)
    {
        // Set data
        $this->config->column = $this->database->bind($row);

        // Return the object
        return $this;
    }

    /**
     * Select record
     *
     * @param  integer
     * @return array
     */
    public function select($id)
    {
        // Get data from the table
        $result = $this->database->table($this->config->tableName)
            ->argument(1, $this->config->primaryKey, $id)
            ->select()
            ->execute();

        return $row = $this->database->fetch_assoc($result);
    }

    /**
     * Update record
     *
     * @param  integer
     * @return void
     */
    public function update($id)
    {
        // Get data from the table
        $this->database->table($this->config->tableName)
            ->column($this->config->column)
            ->argument(1, $this->config->primaryKey, $id)
            ->update()
            ->execute();

        return $this->hasSuccess();
    }

    /**
     * Insert a new record
     *
     * @return integer
     */
    public function insert()
    {
        $pk = $this->config->primaryKey;

        if (isset($this->config->column[$pk]) && ! $this->config->column[$pk]) {
            unset($this->config->column[$pk]);
        }

        // Get data from the table
        $this->database->table($this->config->tableName)
            ->column($this->config->column)
            ->insert()
            ->execute();

        if ($this->database->error) {
            $id = false;
            $this->setError($this->database->error);
        } else {
            $id = $this->database->insert_id($this->config->sequence);
        }

        // Return the id
        return $id;
    }

    public function getLastId($seq = null)
    {
        if (! $seq && $this->config->sequence) {
            $seq = $this->config->sequence;
        }

        return $this->database->insert_id($seq);
    }

    public function getNextId($seq = null)
    {
        if (! $seq && $this->config->sequence) {
            $seq = $this->config->sequence;
        }

        return $this->database->nextId($seq);
    }

    /**
     * Delete the record
     *
     * @param  integer
     * @return object
     */
    public function delete($id)
    {
        // Get data from the table
        $this->database->table($this->config->tableName)
            ->argument(1, $this->config->primaryKey, $id)
            ->delete()
            ->execute();

        return $this->hasSuccess();
    }

    /**
     * Successfully executed
     *
     * @return boolean
     */
    public function hasSuccess()
    {
        if ($this->database->error) {
            $this->setError($this->database->error);
        }

        return (! $this->database->error) ? true : false;
    }

    /**
     * Select, filter, order and limit data
     *
     * @param  integer
     * @return array
     */
    public function listAll(array $where = null, $columns = null, $orderBy = null, $limit = null, $offset = null)
    {
        // Get data from the table
        $this->database->table($this->config->tableName);

        //replace current "select *" by columns array..
        if (isset($columns)) {
            $this->database->column($columns);
        }

        // Make where clauses easier by just passing an array with desired filter.
        if ($where && count($where) > 0) {
            $aux = 1;
            foreach ($where as $filter) {
                $this->database->argument($aux, $filter, "", "");
                $aux++;
            }
        }

        // Order data, if desired..
        if ($orderBy) {
            $this->database->order($orderBy);
        }

        // Limit data, if desired..
        if ($limit) {
            $this->database->limit($limit);
        }

        // Apply offset for limited data, if desired..
        if ($limit && $offset) {
            $this->database->offset($offset);
        }

        // Execute query
        $result = $this->database->select()->execute();

        // Return all records
        $row = $this->database->fetch_assoc_all($result);

        return $row;
    }

    /**
     * Return the primary key from the table
     *
     * @return string table primary key
     */
    public function getPrimaryKey()
    {
        // Return the string name
        return $this->config->primaryKey;
    }

    /**
     * Set the global error
     *
     * @return string table primary key
     */
    public function setError($error)
    {
        $this->database->error = $error;
    }

    /**
     * Get the global error
     *
     * @return string table primary key
     */
    public function getError()
    {
        return $this->database->error;
    }

    /**
     * Cache
     *
     * @return string table primary key
     */
    public function cache($k, $v)
    {
        return false;
    }

    /**
     * Return the main information from a given table
     *
     * @param  string
     * @return array
     */
    protected function getTableInfo($tableName)
    {
        if ($row = $this->database->getTableInfo($tableName)) {
            $column_name = isset($row['Column_name']) ? $row['Column_name'] : $row['column_name'];
            $row['primaryKey'] = $column_name;
            $row['sequence'] = str_replace(array("nextval","regclass","(",")","::","'"), "", $column_name);
        }
        return $row;
    }

    /**
     * Update or insert the data on the database
     *
     * @return integer last inserted id, sequence or record id
     */
    protected function clear()
    {
        // Create a new record
        $this->config->recordId = 0;
    }

    /**
     * Set model configuration
     *
     * @return integer last inserted id, sequence or record id
     */
    private function setConfig($tableName = null)
    {
        try {
            // Table name
            $tableName = ($tableName) ? $tableName : strtolower(str_replace('models\\', '', get_class($this)));

            // Looing for the table information
            if ($info = $this->getTableInfo($tableName)) {
                $this->config = (object) [
                    'tableName' => $tableName,
                    'primaryKey' => $info['primaryKey'],
                    'sequence' => $info['sequence'],
                    'recordId' => 0
                ];
            } else {
                throw new ModelException("^^[Table could not be found.]^^");
            }
        } catch (ModelException $e) {
            echo $e;
        }
    }

    public function guid()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
}
