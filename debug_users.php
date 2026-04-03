<?php
require 'includes/db.php';

try {
    $users = R::inspect('users');
    echo "USERS TABLE FIELDS: " . implode(', ', array_keys($users));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
