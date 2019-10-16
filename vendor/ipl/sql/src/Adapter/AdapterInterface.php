<?php

namespace ipl\Sql\Adapter;

use ipl\Sql\Config;
use ipl\Sql\Connection;
use ipl\Sql\Contracts\QuoterInterface;

interface AdapterInterface extends QuoterInterface
{
    /**
     * Get the DSN string from the given connection configuration
     *
     * @param   Config  $config
     *
     * @return  string
     */
    public function getDsn(Config $config);

    /**
     * Get the PDO connect options based on the specified connection configuration
     *
     * @param   Config  $config
     *
     * @return  array
     */
    public function getOptions(Config $config);

    /**
     * Set the client time zone
     *
     * @param   Connection  $db
     *
     * @return  $this
     */
    public function setClientTimezone(Connection $db);
}
