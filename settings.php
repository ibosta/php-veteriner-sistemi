<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Admin değilse ve giriş yapmış kullanıcıysa devam et, diğer durumda giriş sayfasına yönlendir
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== TRUE) {
    header('Location: login.php');
    exit();
}

// Users tablosu için ayarlar sayfası (admin değilse)
if ($_SESSION['user_type'] !== 'admin') {
    // Get DB instance
    $db = getDbInstance();
    
    // Kullanıcı bilgilerini al
    $db->where('id', $_SESSION['user_id']);
    $user = $db->getOne('users');
    
    if (!$user) {
        $_SESSION['failure'] = "Kullanıcı bilgileri alınamadı!";
        header('Location: index.php');
        exit();
    }
    
    // Form işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Şifre değiştirme işlemi
        if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            $errors = array();
            
            // Mevcut şifreyi kontrol et
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = "Mevcut şifre yanlış!";
            }
            
            // Yeni şifre kontrolü
            if (strlen($new_password) < 6) {
                $errors[] = "Yeni şifre en az 6 karakter olmalıdır!";
            }
            
            // Şifre eşleşme kontrolü
            if ($new_password !== $confirm_password) {
                $errors[] = "Yeni şifreler eşleşmiyor!";
            }
            
            if (empty($errors)) {
                // Şifreyi güncelle
                $data = array(
                    'password' => password_hash($new_password, PASSWORD_DEFAULT)
                );
                
                $db->where('id', $user['id']);
                if ($db->update('users', $data)) {
                    $_SESSION['success'] = "Şifreniz başarıyla değiştirildi!";
                } else {
                    $_SESSION['failure'] = "Şifre değiştirme işlemi başarısız oldu!";
                }
                
                // Sayfayı yenile
                header('Location: settings.php');
                exit();
            } else {
                $_SESSION['failure'] = implode('<br>', $errors);
            }
        }
        
        // Kullanıcı bilgilerini güncelleme
        if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
            
            $errors = array();
            
            // Email geçerlilik kontrolü
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Geçerli bir e-posta adresi girin!";
            }
            
            // Email benzersizlik kontrolü (kendi emaili hariç)
            $db->where('email', $email);
            $db->where('id', $user['id'], '!=');
            $email_exists = $db->getOne('users');
            
            if ($email_exists) {
                $errors[] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!";
            }
            
            if (empty($errors)) {
                // Kullanıcı bilgilerini güncelle
                $data = array(
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'updated_at' => date('Y-m-d H:i:s')
                );
                
                $db->where('id', $user['id']);
                if ($db->update('users', $data)) {
                    $_SESSION['success'] = "Profiliniz başarıyla güncellendi!";
                    $_SESSION['user_full_name'] = $name; // Oturum bilgisini güncelle
                } else {
                    $_SESSION['failure'] = "Profil güncelleme işlemi başarısız oldu!";
                }
                
                // Sayfayı yenile
                header('Location: settings.php');
                exit();
            } else {
                $_SESSION['failure'] = implode('<br>', $errors);
            }
        }
    }
    
    include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Hesap Ayarları</h1>
        </div>
    </div>

    <?php include_once 'includes/flash_messages.php'; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user"></i> Profil Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="" id="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>Ad Soyad</label>
                            <input type="text" name="name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>E-posta</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Telefon</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Kullanıcı Adı</label>
                            <input type="text" class="form-control" disabled readonly
                                   value="<?php echo htmlspecialchars($user['user_name']); ?>">
                            <small class="text-muted">Kullanıcı adı değiştirilemez.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Bilgileri Güncelle
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-lock"></i> Şifre Değiştir</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="" id="password-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Mevcut Şifre</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre</label>
                            <input type="password" name="new_password" class="form-control" required 
                                   pattern=".{6,}" title="En az 6 karakter olmalıdır">
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-key"></i> Şifre Değiştir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Hesap Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($user['user_name']); ?></p>
                            <p><strong>Rol:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
                            <p><strong>Kayıt Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($user['updated_at'])); ?></p>
                            <?php if (!empty($user['last_login'])): ?>
                            <p><strong>Son Giriş:</strong> <?php echo date('d.m.Y H:i', strtotime($user['last_login'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form kontrolleri için JavaScript -->
<script>
$(document).ready(function() {
    // Şifre değiştirme formu doğrulama
    $('#password-form').on('submit', function(e) {
        var newPass = $('input[name="new_password"]').val();
        var confirmPass = $('input[name="confirm_password"]').val();
        
        if (newPass !== confirmPass) {
            alert('Yeni şifreler eşleşmiyor!');
            e.preventDefault();
            return false;
        }
        
        if (newPass.length < 6) {
            alert('Şifre en az 6 karakter olmalıdır!');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Email kontrolü
    $('#profile-form').on('submit', function(e) {
        var email = $('input[name="email"]').val();
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!emailPattern.test(email)) {
            alert('Lütfen geçerli bir e-posta adresi girin!');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php 
    include_once 'includes/footer.php';
} else {
    // Yönlendirme: Admin hesabı için farklı ayarlar sayfası
    header('Location: admin_settings.php');
    exit();
}
?>