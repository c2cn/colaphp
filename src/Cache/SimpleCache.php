<?php

namespace Cola\Cache;

/**
 * PSR-16 SimpleCache
 */
class SimpleCache
{
    public static function factory($adapter, $config = [])
    {
        if (is_array($adapter)) {
            $config = isset($adapter['config']) ? $adapter['config'] : [];
            $adapter = $adapter['adapter'];
        }

        return new $adapter($config);
    }
}