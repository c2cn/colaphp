<?php

namespace Cola;

use Cola\Cache\SimpleCache;
use Cola\Validation\ValidationException;
use Cola\Validation\Validator;

abstract class Model
{
    /**
     * Db name
     *
     * @var string
     */
    protected $_db = 'db';

    /**
     * Table name, with prefix and main name
     *
     * @var string
     */
    protected $_table;

    /**
     * Primary key
     *
     * @var string
     */
    protected $_pk = 'id';

    /**
     * Cache config
     *
     * @var mixed, string for config key and array for config
     */
    protected $_cache = 'cache';

    /**
     * Cache expire time
     *
     * @var int
     */
    protected $_ttl = 86400;

    /**
     * Validator rules
     *
     * @var array
     */
    protected $_rules = [];

    protected $_uniqueColumns = [];

    public function __construct() {}

    /**
     * Load data
     *
     * @param int $id
     * @return array
     */
    public function load($id, $col = null)
    {
        is_null($col) && $col = $this->_pk;

        $sql = "select * from {$this->_table} where {$col} = ? limit 1";

        $result = $this->db->sql($sql, array($id));
        return empty($result) ? null : $result[0];
    }

    /**
     * Multi load data
     *
     * @param int $ids
     * @return array
     */
    public function loadMultiple($ids, $col = null)
    {
        is_null($col) && $col = $this->_pk;
        if (empty($ids)) {
            return [];
        }
        $bind = implode(',', array_fill(0, count($ids), '?'));
        $sql = "select * from {$this->_table} where {$col} in ({$bind})";

        if (!$raw = $this->db->sql($sql, $ids)) {
            return [];
        }

        $result = [];
        foreach ($raw as $row) {
            $result[$row[$col]] = $row;
        }
        return $result;
    }

    public function mload($ids, $col = null)
    {
        return $this->loadMultiple($ids, $col);
    }

    /**
     * Count result
     *
     * @param string $where
     * @return int
     */
    public function count($where)
    {
        return $this->db->count($this->_table, $where);
    }

    /**
     * Get SQL result
     *
     * @param string $sql
     * @param array $data
     * @return array
     */
    public function sql($sql, $data = [])
    {
        return $this->db->sql($sql, $data);
    }

    /**
     * Insert
     *
     * @param array $data
     * @return boolean
     */
    public function insert($data)
    {
        return $this->db->insert($this->_table, $data);
    }

    public function insertMultiple($rows)
    {
        return $this->db->insertMultiple($this->_table, $rows);
    }

    public function minsert($rows)
    {
        return $this->insertMultiple($rows);
    }

    public function upsert($data)
    {

        return $this->db->upsert($this->_table, $data);
    }

    public function upsertMultiple($rows)
    {
        return $this->db->upsertMultiple($rows);
    }

    public function mupsert($rows)
    {
        return $this->upsertMultiple($rows);
    }

    /**
     * Replace
     *
     * @param array $data
     * @return boolean
     */
    public function replace($data)
    {
        return $this->db->replace($this->_table, $data);
    }

    public function replaceMultiple($rows)
    {
        return $this->db->replaceMultiple($this->_table, $rows);
    }

    public function mreplace($rows)
    {
        return $this->replaceMultiple($rows);
    }

    /**
     * Update
     *
     * @param int $id
     * @param array $data
     * @return boolean
     */
    public function update($id, $data)
    {
        $where = ["{$this->_pk}=?", array($id)];

        return $this->db->update($this->_table, $data, $where);
    }

    public function updateMultiple($rows)
    {
        foreach ($rows as $id => $data) {
            if (!$this->update($id, $data)) {
                return false;
            }
        }

        return true;
    }

    public function mupdate($rows)
    {
        return $this->updateMultiple($rows);
    }

    /**
     * Delete
     *
     * @param $id
     * @param null $col
     * @return boolean
     */
    public function delete($id, $col = null)
    {
        is_null($col) && $col = $this->_pk;
        $sql = "delete from {$this->_table} where {$col} = ?";

        return $this->db->sql($sql, array($id));
    }

    public function del($id, $col = null)
    {
        return $this->delete($id, $col);
    }

    /**
     * Connect db from config
     *
     * @param string
     * @return Db\Mysql
     * @throws \Exception
     */
    public function db($name = null)
    {
        is_null($name) && ($name = $this->_db);

        if (is_array($name)) {
            return new Db\Mysql($name);
        }

        $id = "__db_{$name}";
        $app = App::getInstance();
        if (!$app->container->has($id)) {
            $config = $app->config->get($name);
            $db = new Db\Mysql($config);
            $app->container->set($id, $db);
        }

        return $app->container->get($id);
    }

    /**
     * Init cache
     *
     * @param mixed $name
     * @return Cache\SimpleCache
     * @throws \Exception
     */
    public function cache($name = null)
    {
        is_null($name) && ($name = $this->_cache);

        if (is_array($name)) {
            return Cola::factory($name['adapter'], $name['config']);
        }

        $id = "__cache_{$name}";
        $app = App::getInstance();
        if (!$app->container->has($id)) {
            $factory = $app->config->get($name);
            $cache = SimpleCache::factory($factory['adapter'], $factory['config']);
            $app->container->set($id, $cache);
        }

        return $app->container->get($id);
    }

    /**
     * Get function cache
     *
     * @param string $func
     * @param mixed $args
     * @param int $ttl
     * @param string $key
     * @return mixed
     */
    public function cached($func, $args = array(), $ttl = null, $key = null)
    {
        is_null($ttl) && ($ttl = $this->_ttl);

        if (!is_array($args)) {
            $args = array($args);
        }

        if (is_null($key)) {
            $key = get_class($this) . '-' . $func . '-' . sha1(serialize($args));
        }

        if (!$data = $this->cache->get($key)) {
            $data = json_encode(call_user_func_array(array($this, $func), $args));
            $this->cache->set($key, $data, $ttl);
        }

        return json_decode($data, true);
    }

    /**
     * Validate
     *
     * @param array $data
     * @param boolean $ignoreNotExists
     * @param array $rules
     * @return boolean
     */
    public function validate($data, $ignoreNotExists = false, $rules = null)
    {
        is_null($rules) && $rules = $this->_rules;
        if (empty($rules)) {
            return true;
        }

        $validator = new Validation\Validator($rules, $ignoreNotExists);
        $validator->check($data, $ignoreNotExists);

        foreach ($this->_uniqueColumns as $key => $msg)
        {
            if ((!isset($data[$key])) || is_null($data[$key])) continue;
            if (!$this->isUnique($key, $data[$key])) {
                throw new ValidationException([$key => Validator::getMessage($msg)]);
            }
        }

        return true;
    }

    public function isUnique($column, $val)
    {
        $sql = "select count(1) as cnt from {$this->_table} where {$column} = ?";
        $cnt = $this->db->col($sql, [$val]);
        return 0 === intval($cnt);
    }

    /**
     * Dynamic set vars
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value = null)
    {
        $this->$key = $value;
    }

    /**
     * Dynamic get vars
     *
     * @param string $key
     * @return SimpleCache|Db\Mysql
     * @throws \Exception
     */
    public function __get($key)
    {
        switch ($key) {
            case 'db' :
                $this->db = $this->db();
                return $this->db;

            case 'cache' :
                $this->cache = $this->cache();
                return $this->cache;

            case 'config':
                $this->config = Cola::getInstance()->config;
                return $this->config;

            default:
                throw new \Exception('Undefined property: ' . get_class($this). '::' . $key);
        }
    }
}
