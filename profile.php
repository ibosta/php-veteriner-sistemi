<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== TRUE) {
    header('Location: login.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Kullanıcı türünü kontrol et (admin mi yoksa normal kullanıcı mı)
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini ilgili tablodan çek
$user_info = array();

if ($user_type === 'admin') {
    // Admin bilgilerini al
    $db->where('id', $user_id);
    $admin = $db->getOne('admin_accounts');
    
    if ($admin) {
        $user_info = array(
            'name' => isset($admin['name']) ? $admin['name'] : 'Belirtilmemiş',
            'title' => isset($admin['title']) ? $admin['title'] : 'Veteriner Hekim',
            'registration_no' => isset($admin['registration_no']) ? $admin['registration_no'] : 'Belirtilmemiş',
            'email' => isset($admin['email']) ? $admin['email'] : 'Belirtilmemiş',
            'phone' => isset($admin['phone']) ? $admin['phone'] : 'Belirtilmemiş',
            'department' => isset($admin['department']) ? $admin['department'] : 'Belirtilmemiş',
            'institution' => isset($admin['institution']) ? $admin['institution'] : 'Belirtilmemiş',
            'specialization' => isset($admin['specialization']) ? $admin['specialization'] : 'Belirtilmemiş',
            'graduation' => 'Bilgi girilmemiş', // Admin tablosunda varsayılan olarak bulunmayabilir
            'about' => isset($admin['about']) ? $admin['about'] : 'Bilgi girilmemiş',
            'last_login' => isset($admin['last_login']) ? $admin['last_login'] : date('Y-m-d H:i:s'),
            'user_name' => $admin['user_name'],
            'user_role' => $admin['admin_type'] === 'super' ? 'Süper Yönetici' : 'Yönetici',
            'created_at' => isset($admin['created_at']) ? $admin['created_at'] : date('Y-m-d H:i:s')
        );
    }
} else {
    // Normal kullanıcı bilgilerini al
    $db->where('id', $user_id);
    $user = $db->getOne('users');
    
    if ($user) {
        $user_info = array(
            'name' => isset($user['name']) ? $user['name'] : 'Belirtilmemiş',
            'title' => isset($user['title']) ? $user['title'] : 'Kullanıcı',
            'registration_no' => isset($user['registration_no']) ? $user['registration_no'] : 'Belirtilmemiş',
            'email' => isset($user['email']) ? $user['email'] : 'Belirtilmemiş',
            'phone' => isset($user['phone']) ? $user['phone'] : 'Belirtilmemiş',
            'department' => isset($user['department']) ? $user['department'] : 'Belirtilmemiş',
            'institution' => isset($user['institution']) ? $user['institution'] : 'Belirtilmemiş',
            'specialization' => isset($user['specialization']) ? $user['specialization'] : 'Belirtilmemiş',
            'graduation' => isset($user['graduation']) ? $user['graduation'] : 'Bilgi girilmemiş',
            'about' => isset($user['about']) ? $user['about'] : 'Bilgi girilmemiş',
            'last_login' => isset($user['last_login']) ? $user['last_login'] : date('Y-m-d H:i:s'),
            'user_name' => $user['user_name'],
            'user_role' => isset($user['role']) ? $user['role'] : 'Kullanıcı',
            'created_at' => isset($user['created_at']) ? $user['created_at'] : date('Y-m-d H:i:s')
        );
    }
}

// Kullanıcı bilgisi yoksa hata mesajı göster
if (empty($user_info)) {
    $_SESSION['failure'] = "Kullanıcı bilgileri alınamadı!";
    header('Location: index.php');
    exit();
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header"><?php echo $user_type === 'admin' ? 'Yönetici' : 'Kullanıcı'; ?> Profili</h1>
        </div>
    </div>

    <?php include_once 'includes/flash_messages.php'; ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user<?php echo $user_type === 'admin' ? '-md' : ''; ?>"></i> Kişisel Bilgiler</h3>
                </div>
                <div class="panel-body">
                    <div class="text-center">
                        <img src="assets/images/<?php echo $user_type === 'admin' ? 'vet' : 'vet'; ?>-profile.png" class="img-circle" alt="Profil Fotoğrafı" style="width: 150px; height: 150px; margin-bottom: 15px;">
                    </div>
                    <table class="table">
                        <tr>
                            <th>Ad Soyad:</th>
                            <td><?php echo htmlspecialchars($user_info['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Ünvan:</th>
                            <td><?php echo htmlspecialchars($user_info['title']); ?></td>
                        </tr>
                        <?php if ($user_type === 'admin' || !empty($user_info['registration_no'])): ?>
                        <tr>
                            <th>Sicil No:</th>
                            <td><?php echo htmlspecialchars($user_info['registration_no']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Kullanıcı Adı:</th>
                            <td><?php echo htmlspecialchars($user_info['user_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Rol:</th>
                            <td><?php echo htmlspecialchars($user_info['user_role']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> Detaylı Bilgiler</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><i class="fa fa-hospital-o"></i> Kurum Bilgileri</h4>
                            <table class="table">
                                <tr>
                                    <th>Kurum:</th>
                                    <td><?php echo htmlspecialchars($user_info['institution']); ?></td>
                                </tr>
                                <tr>
                                    <th>Bölüm:</th>
                                    <td><?php echo htmlspecialchars($user_info['department']); ?></td>
                                </tr>
                                <tr>
                                    <th>Uzmanlık:</th>
                                    <td><?php echo htmlspecialchars($user_info['specialization']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h4><i class="fa fa-envelope"></i> İletişim Bilgileri</h4>
                            <table class="table">
                                <tr>
                                    <th>E-posta:</th>
                                    <td><?php echo htmlspecialchars($user_info['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Telefon:</th>
                                    <td><?php echo htmlspecialchars($user_info['phone']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($user_info['graduation']) || !empty($user_info['about'])): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <h4><i class="fa fa-graduation-cap"></i> Eğitim ve Deneyim</h4>
                            <?php if (!empty($user_info['graduation'])): ?>
                            <p><strong>Mezuniyet: </strong><?php echo htmlspecialchars($user_info['graduation']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user_info['about'])): ?>
                            <p><strong>Hakkında: </strong><?php echo htmlspecialchars($user_info['about']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-12">
                            <h4><i class="fa fa-clock-o"></i> Sistem Bilgileri</h4>
                            <p><strong>Kayıt Tarihi: </strong><?php echo date('d.m.Y H:i', strtotime($user_info['created_at'])); ?></p>
                            <p><strong>Son Giriş: </strong><?php echo date('d.m.Y H:i', strtotime($user_info['last_login'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-cog"></i> Hesap İşlemleri</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12">
                            <?php if ($user_type === 'admin'): ?>
                            <a href="admin_settings.php" class="btn btn-primary">
                                <i class="fa fa-cogs"></i> Hesap Ayarları
                            </a>
                            <?php else: ?>
                            <a href="settings.php" class="btn btn-primary">
                                <i class="fa fa-cogs"></i> Hesap Ayarları
                            </a>
                            <?php endif; ?>
                            
                            <a href="logout.php" class="btn btn-danger">
                                <i class="fa fa-sign-out"></i> Çıkış Yap
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>