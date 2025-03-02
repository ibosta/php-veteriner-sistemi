<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Oturum değişkenleri
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'user_name';
}

// Current date and time for logging
$current_date = date('Y-m-d H:i:s');

// DB instance
$db = getDbInstance();

// İlaç ID'si
$medication_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ID geçerli mi kontrol et
if (!$medication_id) {
    $_SESSION['failure'] = "Geçersiz ilaç ID'si.";
    header('Location: medications.php');
    exit();
}

// İlaç bilgilerini al
$db->where('id', $medication_id);
$medication = $db->getOne('medications');

// İlaç mevcut mu kontrol et
if (!$medication) {
    $_SESSION['failure'] = "İlaç bulunamadı.";
    header('Location: medications.php');
    exit();
}

// İlaç reçetelerde kullanılıyor mu kontrol et
$db->where('medication_id', $medication_id);
$usage_count = $db->getValue('prescription_items', 'count(*)');

if ($usage_count > 0) {
    $_SESSION['failure'] = "Bu ilaç " . $usage_count . " adet reçetede kullanıldığı için silinemez. Önce ilacın kullanıldığı reçeteleri güncellemeniz gerekmektedir.";
    header('Location: medications.php');
    exit();
}

// İlacı sil
$db->where('id', $medication_id);
if ($db->delete('medications')) {
    $_SESSION['success'] = "İlaç başarıyla silindi.";
} else {
    $_SESSION['failure'] = "İlaç silinirken bir hata oluştu: " . $db->getLastError();
}

header('Location: medications.php');
exit();
?>