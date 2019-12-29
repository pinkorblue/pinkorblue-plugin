<?php

namespace Robera\AB;

use \WeDevs\ORM\Eloquent\Model;

if (class_exists('FixedModel')) {
    return;
}

/**
 * Class FixedModel
 *
 * We need a Base Model, since by default model saves can trigger errors
 * without throwing anything.
 */
class FixedModel extends Model
{
    public function save(array $options = [])
    {
        if ($this->id) {
            $this->ID = $this->id;
        }
        $saved = parent::save($options);
        $error = $this->getConnection()->db->last_error;

        if ($error) {
            throw new \Exception($error);
        }
        $this->id = $this->ID;
        return $saved;
    }

    public function delete()
    {
        if ($this->id) {
            $this->ID = $this->id;
        }
        $deleted = parent::delete();
        $error = $this->getConnection()->db->last_error;

        if ($error) {
            throw new \Exception($error);
        }
        return $deleted;
    }

    public function update(array $attributes = [], array $options = [])
    {
        if ($this->id) {
            $this->ID = $this->id;
        }
        $updated = parent::update($attributes, $options);
        $error = $this->getConnection()->db->last_error;

        if ($error) {
            throw new \Exception($error);
        }
        return $updated;
    }

    /**
     * Overide parent method to make sure prefixing is correct.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->table)) {
            $prefix =  $this->getConnection()->db->prefix;
            return substr($this->table, 0, strlen($prefix)) === $prefix ? $this->table : $prefix . $this->table;
        }
        return parent::getTable();
    }
}
