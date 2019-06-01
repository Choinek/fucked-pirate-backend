#!/bin/bash

composer self-update
composer install

/usr/local/bin/php /app/index.php
