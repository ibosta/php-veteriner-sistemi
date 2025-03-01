<?php
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Get medications with stock info
$db->join("medications m", "s.medication_id = m.id", "LEFT");
$stocks = $db->get('stock s', null, 's.id, s.medication_id, s.quantity, m.name, m.unit');

// Return as JSON
header('Content-Type: application/json');
echo json_encode($stocks);