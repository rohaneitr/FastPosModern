<?php
cache()->put('test_date', now(), now()->addMinutes(120));
$val = cache()->get('test_date');
echo "Class: " . get_class($val) . "\n";
