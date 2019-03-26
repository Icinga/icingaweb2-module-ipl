<?php

namespace ipl\Sql\Adapter;

use ipl\Sql\Connection;

class Pgsql extends BaseAdapter
{
    public function setClientTimezone(Connection $db)
    {
        $db->prepexec('SET TIME ZONE INTERVAL ? HOUR TO MINUTE', [$this->getTimezoneOffset()]);

        return $this;
    }
}
