Icinga Web 2 - IPL
==================

This module ships the new Icinga PHP library. Please download the latest
release and install it like any other module.

```sh
RELEASES="https://github.com/Icinga/icingaweb2-module-ipl/archive" \
&& MODULES_PATH="/usr/share/icingaweb2/modules" \
&& MODULE_VERSION=0.1.0 \
&& mkdir "$MODULES_PATH" \
&& wget -q $RELEASES/v${MODULE_VERSION}.tar.gz -O - \
   | tar xfz - -C "$MODULES_PATH" --strip-components 1
icingacli module enable ipl
```


Developer Documentation
-----------------------

### Add a new dependency

    composer require author/library:version

### Create a new release

    ./bin/make-release.sh <version>

e.g.

    ./bin/make-release.sh 0.1.0
