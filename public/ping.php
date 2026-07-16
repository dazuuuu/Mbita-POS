<?php
// public/ping.php — instant response, no database (use to test Apache/PHP paths)
header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";
echo "time=" . date('c') . "\n";
