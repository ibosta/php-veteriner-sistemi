<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Oturum değişkenleri
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'name';
}

// Current date and time for logging
$current_date = date('Y-m-d H:i:s');

// DB instance
$db = getDbInstance();

// İlaç birim seçenekleri
$unit_options = array(
    'mg' => 'mg (Miligram)',
    'g' => 'g (Gram)',
    'ml' => 'ml (Mililitre)',
    'tablet' => 'Tablet',
    'kapsül' => 'Kapsül',
    'kaşık' => 'Kaşık',
    'damla' => 'Damla',
    'cc' => 'cc (Santimetreküp)',
    'ünite' => 'Ünite',
);

// Formu işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $unit = filter_input(INPUT_POST, 'unit', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    // Hata kontrolü
    $errors = array();
    
    if (empty($name)) {
        $errors[] = "İlaç adı boş olamaz.";
    }

    if (empty($unit)) {
        $errors[] = "Birim seçimi gereklidir.";
    }
    
    // İlaç adının benzersiz olup olmadığını kontrol et
    $db->where('name', $name);
    $existing_medication = $db->getOne('medications');
    if ($existing_medication) {
        $errors[] = "Bu isimde bir ilaç zaten kayıtlı.";
    }
    
    // Hata yoksa kaydet
    if (empty($errors)) {
        $data = array(
            'name' => $name,
            'unit' => $unit,
            'description' => $description ?: null,
            'created_at' => $current_date
            // created_by sütunu veritabanında olmadığı için kaldırıldı
        );
        
        $id = $db->insert('medications', $data);
        
        if ($id) {
            $_SESSION['success'] = "İlaç başarıyla eklendi.";
            header('Location: medications.php');
            exit();
        } else {
            $_SESSION['failure'] = "İlaç eklenirken bir hata oluştu: " . $db->getLastError();
        }
    } else {
        $_SESSION['failure'] = implode('<br>', $errors);
    }
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Yeni İlaç Ekle</h1>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">İlaç Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="" class="form" id="medication-form">
                        <div class="form-group">
                            <label for="name">İlaç Adı *</label>
                            <input type="text" name="name" id="name" class="form-control" required maxlength="255" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            <p class="help-block">İlaç veya etken madde adını girin</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="unit">Birim *</label>
                            <select name="unit" id="unit" class="form-control" required>
                                <option value="">-- Birim Seçin --</option>
                                <?php foreach ($unit_options as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo (isset($_POST['unit']) && $_POST['unit'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block">İlaç ölçü birimini seçin</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Açıklama</label>
                            <textarea name="description" id="description" class="form-control" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <p class="help-block">İlaç hakkında ek bilgiler (tercihe bağlı)</p>
                        </div>
                        
                        <div class="form-group">
                            <a href="medications.php" class="btn btn-default">İptal</a>
                            <button type="submit" class="btn btn-primary">İlacı Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Bilgi</h3>
                </div>
                <div class="panel-body">
                    <p>Bu ekrandan sisteme yeni ilaç/etken madde ekleyebilirsiniz. Eklenen ilaçlar reçete yazarken seçilebilecektir.</p>
                    <p><strong>Önemli:</strong> İlaç adı ve ölçü birimi doldurulması zorunlu alanlardır. Aynı isimle birden fazla ilaç kaydedemezsiniz.</p>
                    <p>Sistem kayıtlı ilaçları görüntülemek için <a href="medications.php">İlaçlar</a> sayfasını ziyaret ediniz.</p>
                    <hr>
                    <p class="text-muted">
                        <small>Tarih: <?php echo date('d.m.Y H:i', strtotime($current_date)); ?></small><br>
                        <small>Kullanıcı: <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form doğrulama
    $('#medication-form').submit(function(event) {
        var name = $('#name').val().trim();
        var unit = $('#unit').val();
        
        if (name === '') {
            alert('İlaç adı girilmelidir!');
            $('#name').focus();
            event.preventDefault();
            return false;
        }
        
        if (unit === '') {
            alert('Lütfen bir birim seçin!');
            $('#unit').focus();
            event.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>