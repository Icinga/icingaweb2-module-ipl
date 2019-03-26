<?php

namespace ipl\Sql\Adapter;

use ipl\Sql\Connection;

class Mysql extends BaseAdapter
{
    protected $quoteCharacter = ['`', '`'];

    protected $escapeCharatcer = '``';

    public function setClientTimezone(Connection $db)
    {
        $db->prepexec('SET time_zone = ?', [$this->getTimezoneOffset()]);

        return $this;
    }
}
