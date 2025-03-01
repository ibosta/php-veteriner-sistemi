<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Admin bilgilerini al
    $db->where('user_name', $_SESSION['user_name']);
    $admin = $db->getOne('admin_users');
    
    $errors = array();
    
    // Mevcut şifreyi kontrol et
    if (!password_verify($current_password, $admin['password'])) {
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
        
        $db->where('id', $admin['id']);
        if ($db->update('admin_users', $data)) {
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

// Veteriner bilgileri
$vet_info = array(
    'name' => 'İbrahim Bostancı',
    'title' => 'Veteriner Hekim',
    'registration_no' => 'VHK-2025-1234',
    'email' => 'ibosta@kku.edu.tr',
    'phone' => '+90 318 357 33 01',
    'department' => 'Veteriner Fakültesi',
    'institution' => 'Kırıkkale Üniversitesi',
    'specialization' => 'Küçük Hayvan Klinikleri',
    'graduation' => 'Kırıkkale Üniversitesi Veteriner Fakültesi, 2024',
    'about' => 'Küçük hayvan hastalıkları ve cerrahi üzerine uzmanlığı bulunan, özellikle kedi ve köpek hastalıkları konusunda deneyimli veteriner hekim.',
    'last_login' => '2025-03-01 01:57:14',
    'user_name' => 'ibosta'
);

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Ayarlar</h1>
        </div>
    </div>

    <?php include_once 'includes/flash_messages.php'; ?>

    <div class="row">
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

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user"></i> Hekim Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($vet_info['name']); ?></p>
                    <p><strong>Sicil No:</strong> <?php echo htmlspecialchars($vet_info['registration_no']); ?></p>
                    <p><strong>E-posta:</strong> <?php echo htmlspecialchars($vet_info['email']); ?></p>
                    <p><strong>Telefon:</strong> <?php echo htmlspecialchars($vet_info['phone']); ?></p>
                    <p><strong>Kurum:</strong> <?php echo htmlspecialchars($vet_info['institution']); ?></p>
                    <p><strong>Son Giriş:</strong> <?php echo date('d.m.Y H:i', strtotime($vet_info['last_login'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Şifre kontrol için JavaScript -->
<script>
$(document).ready(function() {
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
});
</script>

<?php include_once 'includes/footer.php'; ?>