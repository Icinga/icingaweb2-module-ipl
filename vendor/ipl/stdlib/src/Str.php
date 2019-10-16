<?php

namespace ipl\Stdlib;

/**
 * Collection of string manipulation functions
 */
class Str
{
    /**
     * Convert the given string to camel case
     *
     * The given string may be delimited by the following characters: '_' (underscore), '-' (dash), ' ' (space).
     *
     * @param string $subject
     *
     * @return string
     */
    public static function camel($subject)
    {
        $normalized = str_replace(['-', '_'], ' ', $subject);

        return lcfirst(str_replace(' ', '', ucwords(strtolower($normalized))));
    }
}
