<?php

namespace Mikore\Support;

use ArrayAccess;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Mikore\HasMagicProperties;
use JsonSerializable;
use RuntimeException;

class Preference implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    protected $attributes = [];

    protected $preferenceName;

    protected $preferencePath;

    protected $changed = false;

    public static function load($preferenceName = 'preferences', $path = null)
    {
        return new static($preferenceName, [], $path);
    }

    public function __construct($preferenceName = 'preferences', $attributes = [], $path = null)
    {
        $this->preferenceName = $preferenceName;
        $this->preferencePath = $path ?: Container::getInstance()->databasePath('');
        $this->loadLocalAttributes();
        $this->fill($attributes);
    }

    protected function loadLocalAttributes()
    {
        $attributes = [];
        $filepath = $this->getPreferencePath();
        if (file_exists($filepath) && is_file($filepath)) {
            if (($contents = file_get_contents($filepath)) !== false) {
                $attributes = json_decode($contents, true);
            }
        }
        $this->attributes = $attributes;
    }

    public function reload()
    {
        $this->loadLocalAttributes();

        return $this;
    }

    public function fill(array $attributes)
    {
        if (!empty($attributes) && !$this->isAssoc($attributes)) {
            throw new RuntimeException("Accept only associative array");
        }
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function get(string $key, $defValue = null)
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $defValue;
    }

    public function set(string $key, $value)
    {
        $this->attributes[$key] = $value;
        $this->changed = true;

        return $this;
    }

    public function increment(string $key, $amount)
    {
        return $this->incrementOrDecrement($key, $amount, 'increment');
    }

    public function decrement(string $key, $amount)
    {
        return $this->incrementOrDecrement($key, $amount, 'decrement');
    }

    protected function incrementOrDecrement(string $key, $amount, string $method)
    {
        $amount = $method == 'increment' ? $amount : $amount * -1;

        if (!$this->contains($key)) {
            $this->set($key, $amount);
            return $amount;
        }

        $value = $this->attributes[$key] ?: 0;
        if (!(is_float($value) || is_int($value))) {
            return false;
        }

        $this->set($key, $value + $amount);

        return $this->attributes[$key];
    }

    public function boolean(string $key, bool $defValue = false): bool
    {
        return (bool)$this->get($key, $defValue);
    }

    public function contains(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function remove(string $key)
    {
        unset($this->attributes[$key]);
        $this->changed = true;
    }

    public function exists(string $key): bool
    {
        return $this->contains($key);
    }

    public function missing(string $key): bool
    {
        return !$this->exists($key);
    }

    public function has(string $key): bool
    {
        return $this->contains($key) && $this->attributes[$key] !== null;
    }

    public function only($attributes): array
    {
        $results = [];
        foreach (is_array($attributes) ? $attributes : func_get_args() as $attribute) {
            $results[$attribute] = $this->attributes[$attribute];
        }

        return $results;
    }

    public function save(): bool
    {
        if (file_put_contents($this->getPreferencePath(), $this->toJson())) {
            $this->changed = false;
            return true;
        }
        return false;
    }

    public function toArray(): array
    {
        return array_merge($this->attributes);
    }

    public function toJson($options = 0)
    {
        $json = \json_encode($this->toArray(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Error encoding preference [' . get_class($this) . '] to JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    private function isAssoc(array $array): bool
    {
        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    private function getPreferencePath()
    {
        return $this->preferencePath . '/' . $this->preferenceName . '.json';
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __set(string $name, $value)
    {
        $this->set($name, $value);
    }

    public function __isset(string $name)
    {
        return $this->contains($name);
    }

    public function __unset(string $name)
    {
        $this->remove($name);
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value)
    {
        if (!is_null($key)) {
            $this->set($key, $value);
        }
    }

    public function offsetUnset($key)
    {
        $this->remove($key);
    }
}
