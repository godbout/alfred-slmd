<?php

require_once __DIR__ . '/../server/SleeplessmindWriting.class.php';

$query = trim($argv[1]);

echo SleeplessmindWriting::getSlug($query);
