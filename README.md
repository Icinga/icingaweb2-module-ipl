# DEPRECATED

The currently maintained version of the IPL project can be found [here](https://github.com/Icinga/ipl).

💡 Some modules (e.g. the Director) still depend on this module. This module is still available for usage, so if you're on Icinga Web 2 v2.8.2 or lower please install v0.5.0.  
💡 However, if you're on Icinga Web 2 v2.9.0 or higher, this module is **not** required anymore. Unless you're running the Director in version 1.8.0 or lower, v1.8.1 also doesn't require this module.

## Icinga Web 2 - IPL

This module ships the new Icinga PHP library. Please download the latest
release and install it like any other module.

> **HINT**: Do NOT install the GIT master, it will not work! Checking out a
> branch like `stable/0.5.0` or a tag like `v0.5.0` is fine.

Sample Tarball installation
---------------------------

```sh
MODULE_NAME=ipl
MODULE_VERSION=v0.5.0
MODULES_PATH="/usr/share/icingaweb2/modules"
MODULE_PATH="${MODULES_PATH}/${MODULE_NAME}"
RELEASES="https://github.com/Icinga/icingaweb2-module-${MODULE_NAME}/archive"
mkdir "$MODULE_PATH" \
&& wget -q $RELEASES/${MODULE_VERSION}.tar.gz -O - \
   | tar xfz - -C "$MODULE_PATH" --strip-components 1
icingacli module enable "${MODULE_NAME}"
```

Sample GIT installation
-----------------------

```sh
MODULE_NAME=ipl
MODULE_VERSION=v0.5.0
REPO="https://github.com/Icinga/icingaweb2-module-${MODULE_NAME}"
MODULES_PATH="/usr/share/icingaweb2/modules"
git clone ${REPO} "${MODULES_PATH}/${MODULE_NAME}" --branch "${MODULE_VERSION}"
icingacli module enable "${MODULE_NAME}"
```

Developer Documentation
-----------------------

### Add a new dependency

    composer require author/library:version

### Create a new release

    ./bin/make-release.sh <version>

e.g.

    ./bin/make-release.sh 0.6.0
