<?php

namespace ipl\Stdlib\Contracts;

interface ValidatorInterface
{
    /**
     * Whether the given value is valid
     *
     * @param mixed $value
     * @return bool
     */
    public function isValid($value);

    /**
     * @return array
     */
    public function getMessages();
}
