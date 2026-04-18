<?php

namespace Jdclzn\PayrollEngine\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;

final class AttributeReader
{
    /**
     * Reads the first non-null value from one or more candidate keys.
     *
     * Keys may be passed as a single string or as a prioritized list. Each key
     * may use dot notation to traverse nested arrays or objects. When no value
     * is found, the provided default is returned.
     *
     * @param  array<int, string>|string  $keys
     */
    public function get(mixed $source, array|string $keys, mixed $default = null): mixed
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $result = $this->getByPath($source, $key);

            if ($result !== null) {
                return $result;
            }
        }

        return $default;
    }

    /**
     * Resolves a dot-notated path against the supplied source value.
     *
     * Each path segment is read one level at a time using {@see readSegment()}.
     * If any segment cannot be resolved, the whole path is treated as missing
     * and null is returned.
     */
    private function getByPath(mixed $source, string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $source;

        foreach ($segments as $segment) {
            $value = $this->readSegment($value, $segment);

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    /**
     * Reads a single segment from an array, ArrayAccess object, or plain object.
     *
     * Resolution order is:
     * 1. Arrayable to array conversion
     * 2. native array key lookup
     * 3. ArrayAccess lookup
     * 4. object `getAttribute()` lookup
     * 5. public/dynamic property lookup
     * 6. conventional getter lookup such as `getFirstName()`
     *
     * A null return value indicates that the segment could not be resolved.
     */
    private function readSegment(mixed $source, string $segment): mixed
    {
        if ($source instanceof Arrayable) {
            $source = $source->toArray();
        }

        if (is_array($source)) {
            return array_key_exists($segment, $source) ? $source[$segment] : null;
        }

        if ($source instanceof ArrayAccess) {
            return $source->offsetExists($segment) ? $source[$segment] : null;
        }

        if (! is_object($source)) {
            return null;
        }

        if (method_exists($source, 'getAttribute')) {
            $value = $source->getAttribute($segment);

            if ($value !== null) {
                return $value;
            }
        }

        if (isset($source->{$segment}) || property_exists($source, $segment)) {
            return $source->{$segment};
        }

        $method = 'get'.str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment)));

        if (method_exists($source, $method)) {
            return $source->{$method}();
        }

        return null;
    }
}
