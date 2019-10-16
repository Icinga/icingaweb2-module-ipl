<?php

namespace ipl\Sql\Adapter;

use PDO;
use RuntimeException;
use ipl\Sql\Config;

class Mssql extends BaseAdapter
{
    protected $quoteCharacter = ['[', ']'];

    protected $escapeCharatcer = '[[]';

    public function getDsn(Config $config)
    {
        $drivers = array_intersect(['dblib', 'mssql', 'sybase', 'freetds'], PDO::getAvailableDrivers());

        if (empty($drivers)) {
            throw new RuntimeException('No PDO driver available for connecting to a Microsoft SQL Server');
        }

        $dsn = "{$drivers[0]}:host={$config->host}";

        if (! empty($config->port)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $seperator = ',';
            } else {
                $seperator = ':';
            }

            $dsn .= "{$seperator}{$config->port}";
        }

        $dsn .= ";dbname={$config->dbname}";

        if (! empty($config->charset)) {
            $dsn .= ";charset={$config->charset}";
        }

        return $dsn;
    }
}
