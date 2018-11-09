Icinga Web 2 - IPL
==================

This module ships the new Icinga PHP library. Please download the latest
release and install it like any other module.

> **HINT**: Do NOT install the GIT master, it will not work! Checking out a
> branch like `stable/0.1.1` or a tag like `v0.1.1` is fine.

Sample Tarball installation
---------------------------

```sh
RELEASES="https://github.com/Icinga/icingaweb2-module-ipl/archive" \
&& MODULES_PATH="/usr/share/icingaweb2/modules" \
&& MODULE_VERSION=0.1.1 \
&& mkdir "$MODULES_PATH" \
&& wget -q $RELEASES/v${MODULE_VERSION}.tar.gz -O - \
   | tar xfz - -C "$MODULES_PATH" --strip-components 1
icingacli module enable ipl
```

Sample GIT installation
-----------------------

```sh
REPO="https://github.com/Icinga/icingaweb2-module-ipl" \
&& MODULES_PATH="/usr/share/icingaweb2/modules" \
&& MODULE_VERSION=0.1.1 \
&& mkdir -p "$MODULES_PATH" \
&& git clone ${REPO} "${MODULES_PATH}/ipl"
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
