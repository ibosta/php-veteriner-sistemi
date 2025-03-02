<?php
session_start();
require_once './config/config.php';
require_once './helpers/helpers.php'; // randomString() fonksiyonu için

// Form gönderildiyse işlem yap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $passwd = filter_input(INPUT_POST, 'passwd', FILTER_SANITIZE_STRING);
    $remember = filter_input(INPUT_POST, 'remember', FILTER_VALIDATE_INT);
    
    // Basit validasyon
    if (empty($username) || empty($passwd)) {
        $_SESSION['login_failure'] = "Lütfen kullanıcı adı ve şifre giriniz!";
        header('Location: login.php');
        exit;
    }
    
    $db = getDbInstance();
    
    // 1. Önce admin_accounts tablosunda kontrol et
    $db->where('user_name', $username);
    $admin = $db->getOne('admin_accounts');
    
    // 2. Admin olarak giriş
    if ($admin && password_verify($passwd, $admin['password'])) {
        $_SESSION['user_logged_in'] = TRUE;
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['user_name'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_type'] = $admin['admin_type']; // admin veya super admin
        
        // Hatırla beni işlevi için token oluştur
        if ($remember === 1) {
            $series_id = randomString(16);
            $remember_token = randomString(20);
            $expiry_time = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // "Beni hatırla" için cookie ve veritabanı güncelleme
            $db->where('id', $admin['id']);
            $update_remember = array(
                'series_id' => $series_id,
                'remember_token' => password_hash($remember_token, PASSWORD_DEFAULT),
                'expires' => $expiry_time
            );
            $db->update('admin_accounts', $update_remember);
            
            setcookie('series_id', $series_id, time() + 86400 * 30, '/');
            setcookie('remember_token', $remember_token, time() + 86400 * 30, '/');
            setcookie('user_type', 'admin', time() + 86400 * 30, '/'); // Kullanıcı tipini hatırla
        }
        
        // Başarılı giriş, ana sayfaya yönlendir
        header('Location: index.php');
        exit;
    } 
    // 3. Users tablosunda kontrol et
    else {
        $db = getDbInstance();
        $db->where('user_name', $username);
        $user = $db->getOne('users');
        
        // 4. Normal kullanıcı olarak giriş
        if ($user && password_verify($passwd, $user['password'])) {
            $_SESSION['user_logged_in'] = TRUE;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['user_type'] = 'user';
            $_SESSION['user_role'] = $user['role']; // Kullanıcı rolü
            $_SESSION['user_full_name'] = $user['name'];
            
            // Hatırla beni işlevi için token oluştur (eğer users tablosunda gerekli alanlar varsa)
            if ($remember === 1) {
                // Users tablosunda series_id ve remember_token alanları var mı kontrol et
                $fields = $db->rawQuery("SHOW COLUMNS FROM users LIKE 'series_id'");
                $hasBothFields = count($fields) > 0;
                
                if ($hasBothFields) {
                    $series_id = randomString(16);
                    $remember_token = randomString(20);
                    
                    // "Beni hatırla" için cookie ve veritabanı güncelleme
                    $db->where('id', $user['id']);
                    $update_remember = array(
                        'series_id' => $series_id,
                        'remember_token' => password_hash($remember_token, PASSWORD_DEFAULT)
                    );
                    $db->update('users', $update_remember);
                    
                    setcookie('series_id', $series_id, time() + 86400 * 30, '/');
                    setcookie('remember_token', $remember_token, time() + 86400 * 30, '/');
                    setcookie('user_type', 'user', time() + 86400 * 30, '/'); // Kullanıcı tipini hatırla
                }
            }
            
            // Başarılı giriş, ana sayfaya yönlendir
            header('Location: index.php');
            exit;
        }
        // 5. Hiçbir tabloda bulunamadı veya şifre yanlış
        else {
            $_SESSION['login_failure'] = "Geçersiz kullanıcı adı veya şifre!";
            header('Location: login.php');
            exit;
        }
    }
} else {
    // POST metodu değilse login sayfasına yönlendir
    header('Location: login.php');
    exit;
}

// randomString fonksiyonu helpers.php'de tanımlı olmazsa
if (!function_exists('randomString')) {
    function randomString($n) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        
        for ($i = 0; $i < $n; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $str .= $characters[$index];
        }
        
        return $str;
    }
}
?>