#!/bin/sh

# exit on error
set -e

# working directory
cd "$(dirname "$0")"

# execute scripts
java -jar wdtk-import.jar "$@"
php -f properties-import.php
