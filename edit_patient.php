<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get patient ID from form
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    
    // Check if ID is valid
    if (!$patient_id) {
        $_SESSION['failure'] = "Geçersiz hasta ID'si.";
        header('Location: patients.php');
        exit();
    }
    
    // Sanitize user input
    $data = filter_input_array(INPUT_POST, [
        'name' => FILTER_SANITIZE_STRING,
        'type' => FILTER_SANITIZE_STRING,
        'breed' => FILTER_SANITIZE_STRING,
        'age' => FILTER_VALIDATE_INT,
        'gender' => FILTER_SANITIZE_STRING,
        'owner_name' => FILTER_SANITIZE_STRING,
        'owner_phone' => FILTER_SANITIZE_STRING,
        'owner_email' => FILTER_SANITIZE_EMAIL,
        'owner_address' => FILTER_SANITIZE_STRING,
        'notes' => FILTER_SANITIZE_STRING
    ]);
    
    // Get DB instance
    $db = getDbInstance();
    
    // Check required fields
    $required = ['name', 'type', 'owner_name', 'owner_phone'];
    $error = false;
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $error = true;
            $_SESSION['failure'] = "Lütfen tüm zorunlu alanları doldurun.";
            break;
        }
    }
    
    // Update patient in database
    if (!$error) {
        $patient = [
            'name' => $data['name'],
            'type' => $data['type'],
            'breed' => $data['breed'] ?: null,
            'age' => $data['age'] ?: null,
            'gender' => $data['gender'] ?: null,
            'owner_name' => $data['owner_name'],
            'owner_phone' => $data['owner_phone'],
            'owner_email' => $data['owner_email'] ?: null,
            'owner_address' => $data['owner_address'] ?: null,
            'notes' => $data['notes'] ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $db->where('id', $patient_id);
        $status = $db->update('patients', $patient);
        
        if ($status) {
            $_SESSION['success'] = "Hasta bilgileri başarıyla güncellendi.";
        } else {
            $_SESSION['failure'] = "Hasta güncellenirken bir sorun oluştu: " . $db->getLastError();
        }
    }
    
    // Redirect to patients page
    header('Location: patients.php');
    exit();
}

// Redirect to patients page if accessed directly
header('Location: patients.php');
exit();
?>