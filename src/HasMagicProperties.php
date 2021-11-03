<?php

namespace Mikore;

trait HasMagicProperties
{
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
}