<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Admin kontrolü
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== TRUE || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Admin bilgilerini al
$db->where('id', $_SESSION['user_id']);
$admin = $db->getOne('admin_accounts');

if (!$admin) {
    $_SESSION['failure'] = "Yönetici bilgileri alınamadı!";
    header('Location: index.php');
    exit();
}

// Eksik alanlar için varsayılan değerler atama
$admin['name'] = isset($admin['name']) ? $admin['name'] : '';
$admin['email'] = isset($admin['email']) ? $admin['email'] : '';
$admin['phone'] = isset($admin['phone']) ? $admin['phone'] : '';
$admin['title'] = isset($admin['title']) ? $admin['title'] : '';
$admin['registration_no'] = isset($admin['registration_no']) ? $admin['registration_no'] : '';
$admin['department'] = isset($admin['department']) ? $admin['department'] : '';
$admin['institution'] = isset($admin['institution']) ? $admin['institution'] : '';
$admin['specialization'] = isset($admin['specialization']) ? $admin['specialization'] : '';
$admin['created_at'] = isset($admin['created_at']) ? $admin['created_at'] : date('Y-m-d H:i:s');
$admin['updated_at'] = isset($admin['updated_at']) ? $admin['updated_at'] : $admin['created_at'];

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Şifre değiştirme işlemi
    if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $errors = array();
        
        // Mevcut şifreyi kontrol et
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = "Mevcut şifre yanlış!";
        }
        
        // Yeni şifre kontrolü
        if (strlen($new_password) < 8) { // Admin için daha güçlü şifre politikası
            $errors[] = "Yeni şifre en az 8 karakter olmalıdır!";
        }
        
        // Şifre karmaşıklık kontrolü
        if (!preg_match('/[A-Z]/', $new_password) || 
            !preg_match('/[a-z]/', $new_password) || 
            !preg_match('/[0-9]/', $new_password)) {
            $errors[] = "Şifre en az bir büyük harf, bir küçük harf ve bir rakam içermelidir!";
        }
        
        // Şifre eşleşme kontrolü
        if ($new_password !== $confirm_password) {
            $errors[] = "Yeni şifreler eşleşmiyor!";
        }
        
        if (empty($errors)) {
            // Şifreyi güncelle
            $data = array(
                'password' => password_hash($new_password, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $db->where('id', $_SESSION['user_id']);
            if ($db->update('admin_accounts', $data)) {
                $_SESSION['success'] = "Şifreniz başarıyla değiştirildi!";
            } else {
                $_SESSION['failure'] = "Şifre değiştirme işlemi başarısız oldu!";
            }
            
            // Sayfayı yenile
            header('Location: admin_settings.php');
            exit();
        } else {
            $_SESSION['failure'] = implode('<br>', $errors);
        }
    }
    
    // Yönetici bilgilerini güncelleme
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $registration_no = filter_input(INPUT_POST, 'registration_no', FILTER_SANITIZE_STRING);
        $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
        $institution = filter_input(INPUT_POST, 'institution', FILTER_SANITIZE_STRING);
        $specialization = filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING);
        
        $errors = array();
        
        // Email geçerlilik kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Geçerli bir e-posta adresi girin!";
        }
        
        // Email benzersizlik kontrolü (kendi emaili hariç)
        $db->where('email', $email);
        $db->where('id', $_SESSION['user_id'], '!=');
        $email_exists = $db->getOne('admin_accounts');
        
        if ($email_exists) {
            $errors[] = "Bu e-posta adresi başka bir yönetici tarafından kullanılıyor!";
        }
        
        if (empty($errors)) {
            // Yönetici bilgilerini güncelle
            $data = array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'title' => $title,
                'registration_no' => $registration_no,
                'department' => $department,
                'institution' => $institution,
                'specialization' => $specialization,
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $db->where('id', $_SESSION['user_id']);
            if ($db->update('admin_accounts', $data)) {
                $_SESSION['success'] = "Yönetici bilgileri başarıyla güncellendi!";
            } else {
                $_SESSION['failure'] = "Bilgi güncelleme işlemi başarısız oldu!";
            }
            
            // Sayfayı yenile
            header('Location: admin_settings.php');
            exit();
        } else {
            $_SESSION['failure'] = implode('<br>', $errors);
        }
    }
    
    // Sistem ayarları güncelleme
    if (isset($_POST['action']) && $_POST['action'] == 'update_system_settings') {
        // Sadece super admin sistem ayarlarını değiştirebilir
        if ($_SESSION['admin_type'] !== 'super') {
            $_SESSION['failure'] = "Bu işlem için yetkiniz bulunmamaktadır!";
            header('Location: admin_settings.php');
            exit();
        }
        
        $site_title = filter_input(INPUT_POST, 'site_title', FILTER_SANITIZE_STRING);
        $footer_text = filter_input(INPUT_POST, 'footer_text', FILTER_SANITIZE_STRING);
        $records_per_page = filter_input(INPUT_POST, 'records_per_page', FILTER_VALIDATE_INT);
        $session_timeout = filter_input(INPUT_POST, 'session_timeout', FILTER_VALIDATE_INT);
        
        $settings = array(
            'site_title' => $site_title,
            'footer_text' => $footer_text,
            'records_per_page' => $records_per_page,
            'session_timeout' => $session_timeout,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['user_id']
        );
        
        // Sistem ayarları tablosunu güncelle veya ekle
        $db->where('setting_key', 'system_settings');
        $existing = $db->getOne('settings');
        
        if ($existing) {
            $db->where('setting_key', 'system_settings');
            $result = $db->update('settings', ['setting_value' => json_encode($settings)]);
        } else {
            $result = $db->insert('settings', [
                'setting_key' => 'system_settings',
                'setting_value' => json_encode($settings),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        if ($result) {
            $_SESSION['success'] = "Sistem ayarları başarıyla güncellendi!";
        } else {
            $_SESSION['failure'] = "Sistem ayarları güncellenemedi!";
        }
        
        // Sayfayı yenile
        header('Location: admin_settings.php');
        exit();
    }
}

// Sistem ayarlarını getir (super admin için)
$system_settings = array(
    'site_title' => 'Veteriner Reçete Sistemi',
    'footer_text' => '© ' . date('Y') . ' İbrahim Taşkıran',
    'records_per_page' => 10,
    'session_timeout' => 30 // dakika
);

if ($_SESSION['admin_type'] === 'super') {
    $db->where('setting_key', 'system_settings');
    $settings_row = $db->getOne('settings');
    
    if ($settings_row) {
        $saved_settings = json_decode($settings_row['setting_value'], true);
        if (is_array($saved_settings)) {
            $system_settings = array_merge($system_settings, $saved_settings);
        }
    }
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Yönetici Ayarları</h1>
        </div>
    </div>

    <?php include_once 'includes/flash_messages.php'; ?>
    
    <ul class="nav nav-tabs" id="settingsTabs">
        <li class="active"><a data-toggle="tab" href="#profile">Profil Bilgileri</a></li>
        <li><a data-toggle="tab" href="#security">Güvenlik</a></li>
        <?php if ($_SESSION['admin_type'] === 'super'): ?>
        <li><a data-toggle="tab" href="#system">Sistem Ayarları</a></li>
        <?php endif; ?>
        <li><a data-toggle="tab" href="#users">Kullanıcı Yönetimi</a></li>
    </ul>
    
    <div class="tab-content">
        <!-- Profil Bilgileri Sekmesi -->
        <div id="profile" class="tab-pane fade in active">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-8">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-user"></i> Yönetici Profil Bilgileri</h3>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="" id="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Ad Soyad</label>
                                            <input type="text" name="name" class="form-control" required 
                                                   value="<?php echo htmlspecialchars($admin['name']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Unvan</label>
                                            <input type="text" name="title" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['title']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>E-posta</label>
                                            <input type="email" name="email" class="form-control" required
                                                   value="<?php echo htmlspecialchars($admin['email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Telefon</label>
                                            <input type="text" name="phone" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['phone']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Sicil / Kayıt No</label>
                                            <input type="text" name="registration_no" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['registration_no']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Kurum</label>
                                            <input type="text" name="institution" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['institution']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Bölüm</label>
                                            <input type="text" name="department" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['department']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Uzmanlık Alanı</label>
                                            <input type="text" name="specialization" class="form-control" 
                                                   value="<?php echo htmlspecialchars($admin['specialization']); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Kullanıcı Adı</label>
                                    <input type="text" class="form-control" readonly disabled
                                           value="<?php echo htmlspecialchars($admin['user_name']); ?>">
                                    <small class="text-muted">Kullanıcı adı değiştirilemez.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Bilgileri Güncelle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-info-circle"></i> Hesap Bilgileri</h3>
                        </div>
                        <div class="panel-body">
                            <div class="text-center">
                                <img src="assets/images/vet-profile.png" class="img-circle" style="width: 150px; height: 150px; margin-bottom: 15px;">
                            </div>
                            
                            <table class="table">
                                <tr>
                                    <th>Kullanıcı Adı:</th>
                                    <td><?php echo htmlspecialchars($admin['user_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Yönetici Tipi:</th>
                                    <td>
                                        <?php 
                                        if ($admin['admin_type'] === 'super') {
                                            echo 'Süper Yönetici';
                                        } else {
                                            echo 'Standart Yönetici';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Kayıt Tarihi:</th>
                                    <td><?php echo date('d.m.Y H:i', strtotime($admin['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Son Güncelleme:</th>
                                    <td><?php echo date('d.m.Y H:i', strtotime($admin['updated_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Son Giriş:</th>
                                    <td>
                                        <?php 
                                        echo isset($admin['last_login']) ? 
                                            date('d.m.Y H:i', strtotime($admin['last_login'])) : 
                                            'Bilgi Yok';
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Güvenlik Sekmesi -->
        <div id="security" class="tab-pane fade">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-key"></i> Şifre Değiştir</h3>
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
                                           pattern=".{8,}" title="En az 8 karakter olmalıdır">
                                    <small class="text-muted">
                                        En az 8 karakter, en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Yeni Şifre (Tekrar)</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-refresh"></i> Şifre Değiştir
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-shield"></i> Güvenlik Bilgileri</h3>
                        </div>
                        <div class="panel-body">
                            <p>
                                <i class="fa fa-info-circle"></i> 
                                <strong>Güçlü Şifre Özellikleri:</strong>
                            </p>
                            <ul>
                                <li>En az 8 karakter uzunluğunda olmalı</li>
                                <li>En az bir büyük harf içermeli</li>
                                <li>En az bir küçük harf içermeli</li>
                                <li>En az bir rakam içermeli</li>
                                <li>Tahmin edilmesi zor olmalı</li>
                                <li>Kişisel bilgiler içermemeli (doğum tarihi, isim vb.)</li>
                            </ul>
                            <hr>
                            <p>
                                <i class="fa fa-warning"></i> 
                                <strong>Güvenlik Uyarıları:</strong>
                            </p>
                            <ul>
                                <li>Şifrenizi hiç kimseyle paylaşmayın</li>
                                <li>Şüpheli bir durum fark ederseniz hemen şifrenizi değiştirin</li>
                                <li>Farklı platformlar için farklı şifreler kullanın</li>
                                <li>Ortak kullanılan bilgisayarlarda oturumunuzu kapatmayı unutmayın</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($_SESSION['admin_type'] === 'super'): ?>
        <!-- Sistem Ayarları Sekmesi (Sadece Süper Admin için) -->
        <div id="system" class="tab-pane fade">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-8">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-cogs"></i> Sistem Ayarları</h3>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="" id="system-form">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <div class="form-group">
                                    <label>Site Başlığı</label>
                                    <input type="text" name="site_title" class="form-control" required
                                           value="<?php echo htmlspecialchars($system_settings['site_title']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Alt Bilgi Metni</label>
                                    <input type="text" name="footer_text" class="form-control" required
                                           value="<?php echo htmlspecialchars($system_settings['footer_text']); ?>">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Sayfa Başına Kayıt Sayısı</label>
                                            <input type="number" name="records_per_page" class="form-control" min="5" max="100" required
                                                   value="<?php echo (int)$system_settings['records_per_page']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Oturum Zaman Aşımı (dakika)</label>
                                            <input type="number" name="session_timeout" class="form-control" min="5" max="180" required
                                                   value="<?php echo (int)$system_settings['session_timeout']; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Ayarları Kaydet
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h3 class="panel-title"><i class="fa fa-info-circle"></i> Sistem Bilgileri</h3>
                        </div>
                        <div class="panel-body">
                            <table class="table">
                                <tr>
                                    <th>PHP Sürümü:</th>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <th>Web Sunucu:</th>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                </tr>
                                <tr>
                                    <th>Veritabanı:</th>
                                    <td>MySQL</td>
                                </tr>
                                <tr>
                                    <th>Tarih/Saat:</th>
                                    <td><?php echo date('d.m.Y H:i:s'); ?></td>
                                </tr>
                                <tr>
                                    <th>Sunucu IP:</th>
                                    <td><?php echo $_SERVER['SERVER_ADDR']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Kullanıcı Yönetimi Sekmesi -->
        <div id="users" class="tab-pane fade">
            <div class="row" style="margin-top: 20px;">
                <div class="col-md-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div class="row">
                                <div class="col-md-6">
                                    <h3 class="panel-title"><i class="fa fa-users"></i> Kullanıcı Listesi</h3>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addUserModal">
                                        <i class="fa fa-plus"></i> Yeni Kullanıcı Ekle
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <!-- Kullanıcı Arama ve Filtreleme -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="input-group">
                                            <input type="text" id="searchUser" class="form-control" placeholder="Kullanıcı ara...">
                                            <span class="input-group-btn">
                                                <button class="btn btn-default" type="button">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-inline text-right">
                                        <div class="form-group">
                                            <label>Rol: </label>
                                            <select id="filterRole" class="form-control input-sm">
                                                <option value="">Tümü</option>
                                                <option value="user">Kullanıcı</option>
                                                <option value="editor">Editör</option>
                                                <option value="viewer">Görüntüleyici</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Kullanıcı Tablosu -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="userTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Kullanıcı Adı</th>
                                            <th>Ad Soyad</th>
                                            <th>E-posta</th>
                                            <th>Rol</th>
                                            <th>Son Giriş</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Get DB instance
                                        $users_db = getDbInstance();
                                        $users_db->orderBy('id', 'DESC');
                                        $users = $users_db->get('users', null, '*');
                                        
                                        if ($users) {
                                            foreach ($users as $user) {
                                                $status = isset($user['status']) && $user['status'] == 1 ? 
                                                    '<span class="label label-success">Aktif</span>' : 
                                                    '<span class="label label-danger">Pasif</span>';
                                                
                                                echo '<tr>';
                                                echo '<td>' . $user['id'] . '</td>';
                                                echo '<td>' . htmlspecialchars($user['user_name']) . '</td>';
                                                echo '<td>' . htmlspecialchars($user['name'] ?? 'Belirtilmemiş') . '</td>';
                                                echo '<td>' . htmlspecialchars($user['email'] ?? 'Belirtilmemiş') . '</td>';
                                                echo '<td>' . htmlspecialchars($user['role'] ?? 'Kullanıcı') . '</td>';
                                                echo '<td>' . (isset($user['last_login']) ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Giriş Yapılmamış') . '</td>';
                                                echo '<td>' . $status . '</td>';
                                                echo '<td>';
                                                echo '<button class="btn btn-info btn-xs" onclick="editUser(' . $user['id'] . ')"><i class="fa fa-edit"></i> Düzenle</button> ';
                                                echo '<button class="btn btn-danger btn-xs" onclick="confirmDeleteUser(' . $user['id'] . ', \'' . htmlspecialchars($user['user_name']) . '\')"><i class="fa fa-trash"></i> Sil</button>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="8" class="text-center">Herhangi bir kullanıcı bulunamadı!</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Sayfalama -->
                            <div class="text-center">
                                <ul class="pagination">
                                    <li><a href="#">&laquo;</a></li>
                                    <li class="active"><a href="#">1</a></li>
                                    <li><a href="#">2</a></li>
                                    <li><a href="#">3</a></li>
                                    <li><a href="#">&raquo;</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Kullanıcı Ekleme Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-user-plus"></i> Yeni Kullanıcı Ekle</h4>
            </div>
            <form method="post" action="process_user.php" id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-group">
                        <label>Kullanıcı Adı</label>
                        <input type="text" name="user_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Ad Soyad</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>E-posta</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Şifre</label>
                        <input type="password" name="password" class="form-control" required
                               pattern=".{6,}" title="En az 6 karakter olmalıdır">
                    </div>
                    
                    <div class="form-group">
                        <label>Şifre (Tekrar)</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" class="form-control">
                            <option value="user">Kullanıcı</option>
                            <option value="editor">Editör</option>
                            <option value="viewer">Görüntüleyici</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Durum</label>
                        <select name="status" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kullanıcı Düzenleme Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-user-edit"></i> Kullanıcı Düzenle</h4>
            </div>
            <form method="post" action="process_user.php" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-group">
                        <label>Kullanıcı Adı</label>
                        <input type="text" name="user_name" id="edit_user_name" class="form-control" readonly>
                        <small class="text-muted">Kullanıcı adı değiştirilemez.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Ad Soyad</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>E-posta</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Şifre</label>
                        <input type="password" name="password" class="form-control" 
                               pattern=".{6,}" title="En az 6 karakter olmalıdır">
                        <small class="text-muted">Şifreyi değiştirmek istemiyorsanız boş bırakın.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Şifre (Tekrar)</label>
                        <input type="password" name="confirm_password" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" id="edit_role" class="form-control">
                            <option value="user">Kullanıcı</option>
                            <option value="editor">Editör</option>
                            <option value="viewer">Görüntüleyici</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Durum</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kullanıcı Silme Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title text-danger"><i class="fa fa-trash"></i> Kullanıcı Sil</h4>
            </div>
            <div class="modal-body">
                <p>
                    <strong><span id="delete_user_name"></span></strong> kullanıcısını silmek istediğinize emin misiniz?
                </p>
                <p class="text-danger">
                    <i class="fa fa-exclamation-triangle"></i> 
                    Bu işlem geri alınamaz ve kullanıcıya ait tüm verileri kalıcı olarak siler!
                </p>
            </div>
            <div class="modal-footer">
                <form method="post" action="process_user.php" id="deleteUserForm">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Evet, Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Kullanıcı yönetimi için gerekli JavaScript -->
<script>
$(document).ready(function() {
    // Arama işlevi
    $("#searchUser").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#userTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Rol filtreleme
    $("#filterRole").change(function() {
        var value = $(this).val().toLowerCase();
        if (value === "") {
            $("#userTable tbody tr").show();
        } else {
            $("#userTable tbody tr").filter(function() {
                var role = $(this).find("td:eq(4)").text().toLowerCase();
                $(this).toggle(role === value);
            });
        }
    });
    
    // Yeni kullanıcı ekleme formu doğrulama
    $('#addUserForm').on('submit', function(e) {
        var password = $('input[name="password"]', this).val();
        var confirmPassword = $('input[name="confirm_password"]', this).val();
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Şifreler eşleşmiyor!');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Şifre en az 6 karakter olmalıdır!');
            return false;
        }
        
        return true;
    });
    
    // Kullanıcı düzenleme formu doğrulama
    $('#editUserForm').on('submit', function(e) {
        var password = $('input[name="password"]', this).val();
        var confirmPassword = $('input[name="confirm_password"]', this).val();
        
        // Şifre alanı boş ise (değiştirmek istemiyorsa) kontrol etme
        if (password.length > 0) {
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Şifreler eşleşmiyor!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Şifre en az 6 karakter olmalıdır!');
                return false;
            }
        }
        
        return true;
    });
});

// Kullanıcı düzenleme modali için veri çekme
function editUser(userId) {
    // AJAX ile kullanıcı verilerini getir
    $.ajax({
        url: 'process_user.php',
        type: 'GET',
        data: {
            action: 'get_user_data',
            user_id: userId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Modal alanlarını doldur
                $('#edit_user_id').val(response.user.id);
                $('#edit_user_name').val(response.user.user_name);
                $('#edit_name').val(response.user.name);
                $('#edit_email').val(response.user.email);
                $('#edit_phone').val(response.user.phone);
                $('#edit_role').val(response.user.role);
                $('#edit_status').val(response.user.status);
                
                // Modalı göster
                $('#editUserModal').modal('show');
            } else {
                alert('Kullanıcı bilgileri alınamadı: ' + response.message);
            }
        },
        error: function() {
            alert('Bir hata oluştu! Kullanıcı bilgileri alınamadı.');
        }
    });
}

// Kullanıcı silme işlemi onay
function confirmDeleteUser(userId, userName) {
    $('#delete_user_id').val(userId);
    $('#delete_user_name').text(userName);
    $('#deleteUserModal').modal('show');
}
</script>

<?php include_once 'includes/footer.php'; ?>