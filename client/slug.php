<?php

require_once __DIR__ . '/../server/SLMD.class.php';

$query = trim($argv[1]);

echo SLMD::getWritingSlug($query);
