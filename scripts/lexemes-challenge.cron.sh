#!/bin/sh

# exit on error
set -e

# working directory
cd "$(dirname "$0")"

# execute php script
php -f lexemes-challenge.cron.php
