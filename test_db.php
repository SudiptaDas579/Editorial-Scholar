<?php
require_once './config/db.php';

$db = getDB();
echo "✅ Connected to: " . DB_NAME;