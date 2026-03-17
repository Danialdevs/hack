<?php

require 'rb.php';

// Local SQLite for development
$dbPath = __DIR__ . '/../hack.db';
R::setup('sqlite:' . $dbPath);
R::freeze(false);

