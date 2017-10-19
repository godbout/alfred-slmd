<?php

namespace App;

require 'vendor/autoload.php';

echo str_slug($argv[1], '-');
