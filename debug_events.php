<?php
require 'includes/db.php';
$fields = R::inspect('events');
echo "EVENTS FIELDS: " . implode(', ', array_keys($fields));
