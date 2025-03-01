<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Kullanıcı bilgileri
$vet_info = array(
    'name' => 'İbrahim Taşkıran',
    'title' => 'Veteriner Hekim',
    'registration_no' => 'VHK-2025-1234',
    'email' => 'ibosta@kku.edu.tr',
    'phone' => '+90 000 000 000 0',
    'department' => 'Veteriner Fakültesi',
    'institution' => 'Kırıkkale Üniversitesi',
    'specialization' => 'Küçük Hayvan Klinikleri',
    'graduation' => 'Kırıkkale Üniversitesi Veteriner Fakültesi, 2024',
    'about' => 'Küçük hayvan hastalıkları ve cerrahi üzerine uzmanlığı bulunan, özellikle kedi ve köpek hastalıkları konusunda deneyimli veteriner hekim.',
    'last_login' => '2025-03-01 02:01:17',
    'user_name' => 'ibosta'
);

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Hekim Profili</h1>
        </div>
    </div>

    <?php include_once 'includes/flash_messages.php'; ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-user-md"></i> Hekim Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <div class="text-center">
                        <img src="assets/images/vet-profile.png" class="img-circle" alt="Profil Fotoğrafı" style="width: 150px; height: 150px; margin-bottom: 15px;">
                    </div>
                    <table class="table">
                        <tr>
                            <th>Ad Soyad:</th>
                            <td><?php echo htmlspecialchars($vet_info['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Ünvan:</th>
                            <td><?php echo htmlspecialchars($vet_info['title']); ?></td>
                        </tr>
                        <tr>
                            <th>Sicil No:</th>
                            <td><?php echo htmlspecialchars($vet_info['registration_no']); ?></td>
                        </tr>
                        <tr>
                            <th>Kullanıcı Adı:</th>
                            <td><?php echo htmlspecialchars($vet_info['user_name']); ?></td>
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
                                    <td><?php echo htmlspecialchars($vet_info['institution']); ?></td>
                                </tr>
                                <tr>
                                    <th>Bölüm:</th>
                                    <td><?php echo htmlspecialchars($vet_info['department']); ?></td>
                                </tr>
                                <tr>
                                    <th>Uzmanlık:</th>
                                    <td><?php echo htmlspecialchars($vet_info['specialization']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h4><i class="fa fa-envelope"></i> İletişim Bilgileri</h4>
                            <table class="table">
                                <tr>
                                    <th>E-posta:</th>
                                    <td><?php echo htmlspecialchars($vet_info['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Telefon:</th>
                                    <td><?php echo htmlspecialchars($vet_info['phone']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h4><i class="fa fa-graduation-cap"></i> Eğitim ve Deneyim</h4>
                            <p><strong>Mezuniyet: </strong><?php echo htmlspecialchars($vet_info['graduation']); ?></p>
                            <p><strong>Hakkında: </strong><?php echo htmlspecialchars($vet_info['about']); ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h4><i class="fa fa-clock-o"></i> Sistem Bilgileri</h4>
                            <p><strong>Son Giriş: </strong><?php echo date('d.m.Y H:i', strtotime($vet_info['last_login'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-lock"></i> Güvenlik</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12">
                            <a href="settings.php" class="btn btn-primary">
                                <i class="fa fa-key"></i> Şifre Değiştir
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>