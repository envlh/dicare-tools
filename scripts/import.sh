#!/bin/sh

# exit on error
set -e

# working directory
cd "$(dirname "$0")"

# execute scripts

echo 'Parsing dump...'
java -jar wdtk-import.jar `readlink -f /public/dumps/public/wikidatawiki/entities/latest-all.json.gz`

echo 'Properties...'
php -f properties-import.php

echo 'Projects...'
php -f projects-import.php

echo 'Finished!'
