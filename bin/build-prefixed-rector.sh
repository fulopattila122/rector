#!/usr/bin/env bash

# print each statement before run (https://stackoverflow.com/a/9966150/1348344)
set -x

# cleanup build
rm -rf build/
mkdir build

# prefix current code to /build directory (see "scoper.inc.php" for settings)
vendor/bin/php-scoper add-prefix --no-interaction

# prefix namespace in *.yml files
# but not in /config, since there is only Rector\ services and "class names"
(find build/ -path build/config -prune -o -type f -name '*.yml' | xargs perl -pi -e 's/((?:\\{1,2}\w+|\w+\\{1,2})(?:\w+\\{0,2})+)/RectorPrefixed\\\1/g')

# un-prefix Rector files, so it's public API, in configs etc.
# e.g.
# -use RectorPrefixed\Rector\...
# +use Rector\...

# "sed" command format help:
# s/<old-code>/<new-code>/g
# s/RectorPrefixed\\Rector / Rector/g
# "RectorPrefixed\Rector" => "Rector"
(find build/ -type f | xargs sed -i 's/RectorPrefixed\\Rector/Rector/g')
(find build/ -type f | xargs sed -i 's/RectorPrefixed\\\\Rector/Rector/g')

# unprefix container dump - see https://github.com/symfony/symfony/blob/226e2f3949c5843b67826aca4839c2c6b95743cf/src/Symfony/Component/DependencyInjection/Dumper/PhpDumper.php#L897
(find build/ -type f | xargs sed -i 's/use Symfony/use RectorPrefixed\\\\Symfony/g')

# for cases like: https://github.com/rectorphp/rector-prefixed/blob/6b690e46e54830a944618d3a2bf50a7c2bd13939/src/Bridge/Symfony/NodeAnalyzer/ControllerMethodAnalyzer.php#L16
# "'" ref https://stackoverflow.com/a/24509931/1348344
# "prune" ref https://stackoverflow.com/a/4210072/1348344
(find build/ -path build/vendor -prune -o -type f | xargs sed -i "s/'RectorPrefixed\\/'/g")
(find build/ -path build/vendor -prune -o -type f | xargs sed -i "s/'RectorPrefixed\\\\/'/g")

# Symfony Bridge => keep Symfony classes

# \App\\Kernel => App\Kernel
sed -i 's/\\App\\\\Kernel/App\\Kernel/g' build/src/Bridge/Symfony/DefaultAnalyzedSymfonyApplicationContainer.php
# RectorPrefixed\Symfony\Component\HttpKernel\Kernel => Symfony\Component\HttpKernel\Kernel
(find build/src/Bridge/Symfony/ -type f | xargs sed -i 's/RectorPrefixed\\Symfony\\Component/Symfony\\Component/g')

cp composer.json build/composer.json

# rebuild composer dump so the new prefixed namespaces are autoloaded
# the new "RectorPrefixed\" is taken into account thanks to /vendor/composer/installed.json file,
composer dump-autoload -d build --no-dev

# make bin executable
chmod +x build/bin/rector

# clear kernel cache to make use of this new one,
# #todo? maybe prefix this cache as well?
(find build/ -type f | xargs sed -i 's/_rector_cache/_prefixed_rector_cache/g')
rm -rf /tmp/_prefixed_rector_cache

# build composer.json
bin/build-prefixed-rector-composer-json.php

# build bin/rector
bin/build-prefixed-rector-bin.php

# run it to test it
build/bin/rector
