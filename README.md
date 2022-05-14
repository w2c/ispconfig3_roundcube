![ISPConfig3 Logo](ispconfig-logo.png)

# ispconfig3_roundcube

[ISPConfig3](https://www.ispconfig.org/) Plugins for [Roundcube](https://roundcube.net/)

Documentation: https://github.com/w2c/ispconfig3_roundcube/wiki/

Note: if using Ubuntu 22.04 LTS (jammy) with PHP 8.0 (the default for 22.04 _and_ Roundcube 1.5.0+), the standard `php8.0-soap` extension is broken and is therefore _not_ installed, but it's _required_ for the ISPConfig3 Roundcube plugins to work.

You can use the famous Ondrej PHP PPA instead: `apt-add-repository ppa:ondrej/php` followed by the usual `apt-get update; apt-get upgrade`; if needed, you can always add `apt-get php8.0-soap` after everything is properly updated/upgraded.

On more recent versions of Ubuntu (experimental/beta at the time of writing and not supported by ISPConfig3), PHP 8.1 has been made the default, and Roundcube will be shipped with version 1.6.0 (currently in Beta), which fully supports PHP 8.1; `php8.1-soap` is _not_ broken on the default Ubuntu package list, so the step above is not required (it's still recommended, as Ondrej's PHP repository is the _de facto_ standard for PHP installations under Ubuntu, but the choice is yours).

[![BSD-3-Clause license](https://img.shields.io/badge/license-BSD--3--Clause-3DA639?logo=opensourceinitiative&logoColor=3DA639)](LICENSE.md)