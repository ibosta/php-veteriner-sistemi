<?php
session_start();
require_once './config/config.php';
require_once './helpers/helpers.php'; // randomString() fonksiyonu için

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === TRUE) {
    header('Location:index.php');
    exit;
}

// Kullanıcı daha önce "beni hatırla" seçeneğini seçmişse:
if (isset($_COOKIE['series_id']) && isset($_COOKIE['remember_token'])) {
    $series_id = filter_var($_COOKIE['series_id']);
    $remember_token = filter_var($_COOKIE['remember_token']);
    $db = getDbInstance();
    
    // cookie'de belirtilen kullanıcı tipine göre doğru tabloda ara
    if (isset($_COOKIE['user_type']) && $_COOKIE['user_type'] === 'user') {
        // users tablosunda kontrol et
        $db->where('series_id', $series_id);
        $row = $db->getOne('users');
        
        if ($row && password_verify($remember_token, $row['remember_token'])) {
            $_SESSION['user_logged_in'] = TRUE;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_type'] = 'user';
            $_SESSION['user_role'] = $row['role'];
            $_SESSION['user_name'] = $row['user_name'];
            $_SESSION['user_full_name'] = $row['name'];
            
            // Yeni token oluştur ve güncelle
            $new_token = randomString(20);
            $db->where('id', $row['id']);
            $db->update('users', ['remember_token' => password_hash($new_token, PASSWORD_DEFAULT)]);
            setcookie('remember_token', $new_token, time() + 86400 * 30, '/');
            
            header('Location:index.php');
            exit;
        }
    } else {
        // admin_accounts tablosunda kontrol et (varsayılan)
        $db->where('series_id', $series_id);
        $row = $db->getOne('admin_accounts');
        
        if ($row && password_verify($remember_token, $row['remember_token']) && 
            (!isset($row['expires']) || $row['expires'] > date('Y-m-d H:i:s'))) {
            
            $_SESSION['user_logged_in'] = TRUE;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['admin_type'] = $row['admin_type'];
            $_SESSION['user_name'] = $row['user_name'];
            
            // Yeni token oluştur ve güncelle
            $new_token = randomString(20);
            $expiry_time = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $db->where('id', $row['id']);
            $db->update('admin_accounts', [
                'remember_token' => password_hash($new_token, PASSWORD_DEFAULT),
                'expires' => $expiry_time
            ]);
            
            setcookie('remember_token', $new_token, time() + 86400 * 30, '/');
            
            header('Location:index.php');
            exit;
        }
    }
    
    // Eşleşme bulunamadı veya token geçersiz - çerezleri temizle
    setcookie('series_id', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('user_type', '', time() - 3600, '/');
}

include_once 'includes/header.php';
?>

<div id="page-" class="col-md-4 col-md-offset-4">
    <form class="form loginform" method="POST" action="authenticate.php">
        <div class="login-panel panel panel-default">
            <div class="panel-heading">Veteriner Reçete Sistemi</div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="control-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required="required">
                </div>
                <div class="form-group">
                    <label class="control-label">Şifre</label>
                    <input type="password" name="passwd" class="form-control" required="required">
                </div>
                <div class="checkbox">
                    <label>
                        <input name="remember" type="checkbox" value="1">Beni Hatırla
                    </label>
                </div>
                <?php
                if (isset($_SESSION['login_failure'])) {
                    echo '<div class="alert alert-danger alert-dismissable fade in">';
                    echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                    echo $_SESSION['login_failure'];
                    echo '</div>';
                    unset($_SESSION['login_failure']);
                }
                ?>
                <button type="submit" class="btn btn-success btn-block">Giriş Yap</button>
            </div>
            <div class="panel-footer">
    <div class="text-center">
        <p class="hackathon-info">
            Bu sistem Hayvan Sağlığı Teknolojileri Hackathonu Ön Yarışması için tasarlanmıştır.
        </p>
        <span class="text-info">
            <strong>Demo Giriş Bilgileri:</strong><br>
            <strong style="color: #337ab7;">Yönetici Girişi:</strong><br>
            Kullanıcı Adı: kkuhackathon25<br>
            Şifre: admin<br><br>
            <strong style="color: #5cb85c;">Veteriner Girişi:</strong><br>
            Kullanıcı Adı: test<br>
            Şifre: 123456
        </span>
        <hr style="margin: 10px 0;">
        <p><small>Son Güncelleme: <?php echo date('d.m.Y H:i', strtotime('2025-03-02 20:25:05')); ?></small></p>
    </div>
</div>
        </div>
    </form>
</div>

<!-- Login sayfası için özel stil -->
<style>
.loginform {
    margin-top: 50px;
}
.login-panel {
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.panel-heading {
    font-size: 18px;
    font-weight: bold;
    text-align: center;
    padding: 15px;
    background: #337ab7 !important;
    color: white !important;
    border-radius: 4px 4px 0 0 !important;
}
.panel-footer {
    background: #f8f9fa;
    border-radius: 0 0 4px 4px;
    padding: 15px;
}
.btn-success {
    margin-top: 10px;
}
.form-control {
    height: 40px;
}
.alert {
    margin-top: 10px;
    margin-bottom: 0;
}
.hackathon-info {
    font-size: 13px;
    color: #666;
    margin: 10px 0;
}
.text-info {
    display: block;
    margin: 10px 0;
    padding: 10px;
    background: #f0f9ff;
    border-radius: 4px;
}
hr {
    border-top: 1px solid #ddd;
}
</style>

<?php include_once 'includes/footer.php'; ?>