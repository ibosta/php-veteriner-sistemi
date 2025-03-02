<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Admin değilse erişimi engelle
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== TRUE || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Kullanıcı verileri getirme (AJAX isteği için)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_user_data') {
    if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı ID belirlenmedi!']);
        exit();
    }
    
    $user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    
    $db->where('id', $user_id);
    $user = $db->getOne('users');
    
    if ($user) {
        // Güvenlik için şifre bilgisini gönderme
        unset($user['password']);
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı!']);
    }
    exit();
}

// POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Yeni kullanıcı ekleme
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $user_name = filter_input(INPUT_POST, 'user_name', FILTER_SANITIZE_STRING);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
        
        $errors = array();
        
        // Kullanıcı adı benzersizlik kontrolü
        $db->where('user_name', $user_name);
        $user_exists = $db->getOne('users');
        
        if ($user_exists) {
            $errors[] = "Bu kullanıcı adı zaten kullanılıyor!";
        }
        
        // Email geçerlilik kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Geçerli bir e-posta adresi girin!";
        }
        
        // Email benzersizlik kontrolü
        $db->where('email', $email);
        $email_exists = $db->getOne('users');
        
        if ($email_exists) {
            $errors[] = "Bu e-posta adresi zaten kullanılıyor!";
        }
        
        // Şifre kontrolü
        if (strlen($password) < 6) {
            $errors[] = "Şifre en az 6 karakter olmalıdır!";
        }
        
        // Şifre eşleşme kontrolü
        if ($password !== $confirm_password) {
            $errors[] = "Şifreler eşleşmiyor!";
        }
        
        if (empty($errors)) {
            // Yeni kullanıcıyı ekle
            $data = array(
                'user_name' => $user_name,
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => $phone,
                'role' => $role,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $last_id = $db->insert('users', $data);
            
            if ($last_id) {
                $_SESSION['success'] = "Kullanıcı başarıyla eklendi!";
            } else {
                $_SESSION['failure'] = "Kullanıcı ekleme işlemi başarısız oldu!";
            }
        } else {
            $_SESSION['failure'] = implode('<br>', $errors);
        }
        
        header('Location: admin_settings.php#users');
        exit();
    }
    
    // Kullanıcı düzenleme
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
        
        $errors = array();
        
        // Kullanıcının mevcut olduğunu kontrol et
        $db->where('id', $user_id);
        $user = $db->getOne('users');
        
        if (!$user) {
            $_SESSION['failure'] = "Düzenlenecek kullanıcı bulunamadı!";
            header('Location: admin_settings.php#users');
            exit();
        }
        
        // Email geçerlilik kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Geçerli bir e-posta adresi girin!";
        }
        
        // Email benzersizlik kontrolü (kendi emaili hariç)
        $db->where('email', $email);
        $db->where('id', $user_id, '!=');
        $email_exists = $db->getOne('users');
        
        if ($email_exists) {
            $errors[] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!";
        }
        
        // Şifre kontrolü (eğer girilmişse)
        if (!empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = "Şifre en az 6 karakter olmalıdır!";
            }
            
            // Şifre eşleşme kontrolü
            if ($password !== $confirm_password) {
                $errors[] = "Şifreler eşleşmiyor!";
            }
        }
        
        if (empty($errors)) {
            // Kullanıcı verilerini güncelle
            $data = array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            // Şifre değiştirilmişse ekle
            if (!empty($password)) {
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $db->where('id', $user_id);
            if ($db->update('users', $data)) {
                $_SESSION['success'] = "Kullanıcı başarıyla güncellendi!";
            } else {
                $_SESSION['failure'] = "Kullanıcı güncelleme işlemi başarısız oldu!";
            }
        } else {
            $_SESSION['failure'] = implode('<br>', $errors);
        }
        
        header('Location: admin_settings.php#users');
        exit();
    }
    
    // Kullanıcı silme
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        // Kullanıcının mevcut olduğunu kontrol et
        $db->where('id', $user_id);
        $user = $db->getOne('users');
        
        if (!$user) {
            $_SESSION['failure'] = "Silinecek kullanıcı bulunamadı!";
            header('Location: admin_settings.php#users');
            exit();
        }
        
        $db->where('id', $user_id);
        if ($db->delete('users')) {
            $_SESSION['success'] = "Kullanıcı başarıyla silindi!";
        } else {
            $_SESSION['failure'] = "Kullanıcı silme işlemi başarısız oldu!";
        }
        
        header('Location: admin_settings.php#users');
        exit();
    }
}

// Geçersiz istek durumunda ana sayfaya yönlendir
header('Location: admin_settings.php');
exit();
?>