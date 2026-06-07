<?php
$log = file_get_contents('storage/logs/laravel.log');
preg_match_all('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] local\.ERROR: (.*?) in \/var\/www\/html\//s', $log, $matches);
if (!empty($matches[1])) { echo "ERROR: " . end($matches[1]); }
