<?php

namespace Icinga\Module\Ipl {

    use Icinga\Application\Hook\ApplicationStateHook;

    class ApplicationState extends ApplicationStateHook
    {
        public function collectMessages()
        {
            $this->addError(
                'ipl.master',
                time(),
                'Please install a Release version of the IPL module, not the GIT master'
            );
        }
    }

    $this->provideHook('ApplicationState', '\\Icinga\\Module\\Ipl\\ApplicationState');
}
