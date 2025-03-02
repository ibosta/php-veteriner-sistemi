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

// İlaçları al (dropdown listesi için)
$medications = $db->get('medications', null, 'id, name, unit');

// Stok tablosu var mı kontrol et
$tableExists = false;
$tables = $db->rawQuery('SHOW TABLES LIKE "stock"');
if (count($tables) > 0) {
    $tableExists = true;
}

// İlk ziyaret mi yoksa form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tablo yoksa oluştur
    if (!$tableExists) {
        createStockTable($db);
        $tableExists = true;
    }
    
    // Form verilerini al
    $medication_id = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_FLOAT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Hata kontrolü
    $errors = array();
    
    if (!$medication_id) {
        $errors[] = "Lütfen bir ilaç seçin.";
    }
    
    if ($quantity === false || $quantity < 0) {
        $errors[] = "Geçerli bir miktar girin.";
    }
    
    if ($reorder_level === false || $reorder_level < 0) {
        $errors[] = "Geçerli bir kritik seviye girin.";
    }
    
    // İlaç stokta zaten var mı kontrol et
    $db->where('medication_id', $medication_id);
    $existing_stock = $db->getOne('stock');
    
    if ($existing_stock) {
        $errors[] = "Bu ilaç için zaten stok kaydı mevcut. Lütfen 'Stok Düzeltme' veya 'Stok Girişi' sayfasını kullanın.";
    }
    
   // Hata yoksa işleme devam et
if (empty($errors)) {
    $stock_data = array(
        'medication_id' => $medication_id,
        'quantity' => $quantity,
        'reorder_level' => $reorder_level,
        'created_at' => $current_date
    );
    
    // Veritabanı işlemini başlat (tüm işlemler ya tamamen başarılı olacak ya da tamamen iptal)
    $db->startTransaction();
    
    try {
        // 1. Stok tablosuna ekle
        $stock_id = $db->insert('stock', $stock_data);
        
        if (!$stock_id) {
            throw new Exception("Stok eklenirken bir hata oluştu: " . $db->getLastError());
        }
        
        // 2. Medications tablosunu da güncelle
        $db_med = getDbInstance();
        $db_med->where('id', $medication_id);
        $current_med = $db_med->getOne('medications');
        
        if (!$current_med) {
            throw new Exception("İlaç bulunamadı.");
        }
        
        $update_med_data = array(
            'quantity' => $quantity, // Stok tablosundaki miktar ile eşleştir
            'updated_at' => $current_date
        );
        
        $db_med->where('id', $medication_id);
        $update_result = $db_med->update('medications', $update_med_data);
        
        if (!$update_result) {
            throw new Exception("İlaç stoğu güncellenirken bir hata oluştu: " . $db_med->getLastError());
        }
        
        // 3. Stok geçmişi tablosu var mı kontrol et
        $tables = $db->rawQuery('SHOW TABLES LIKE "stock_history"');
        $historyTableExists = (count($tables) > 0);
        
        // Yoksa oluştur
        if (!$historyTableExists) {
            createStockHistoryTable($db);
        }
        
        // 4. Stok geçmişi kaydet
        $history_data = array(
            'medication_id' => $medication_id,
            'quantity_change' => $quantity,
            'type' => 'add',
            'notes' => $notes ?: 'İlk stok girişi',
            'created_at' => $current_date,
            'created_by' => $_SESSION['user_id']
        );
        
        $db->insert('stock_history', $history_data);
        
        // İşlemi tamamla
        $db->commit();
        
        $_SESSION['success'] = "Stok başarıyla eklendi.";
        header('Location: stock_list.php');
        exit();
        
    } catch (Exception $e) {
        // Hata durumunda işlemi geri al
        $db->rollback();
        $_SESSION['failure'] = $e->getMessage();
    }
}

// Stok tablosu yoksa uyarı mesajı
if (!$tableExists) {
    $_SESSION['info'] = "Stok tablosu veritabanında bulunamadı. Form gönderildiğinde otomatik olarak oluşturulacaktır.";
}

include_once 'includes/header.php';

/**
 * Stok tablosunu oluşturma fonksiyonu
 */
function createStockTable($db) {
    $query = "CREATE TABLE IF NOT EXISTS `stock` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `medication_id` int(11) NOT NULL,
        `quantity` float NOT NULL DEFAULT 0,
        `reorder_level` float NOT NULL DEFAULT 0,
        `created_at` datetime NOT NULL,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `medication_id` (`medication_id`),
        CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->rawQuery($query);
}

/**
 * Stok geçmişi tablosunu oluşturma fonksiyonu
 */
function createStockHistoryTable($db) {
    $query = "CREATE TABLE IF NOT EXISTS `stock_history` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `medication_id` int(11) NOT NULL,
        `quantity_change` float NOT NULL,
        `type` enum('add','subtract','adjust') NOT NULL,
        `reference_type` varchar(50) DEFAULT NULL,
        `reference_id` int(11) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `created_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `medication_id` (`medication_id`),
        CONSTRAINT `stock_history_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->rawQuery($query);
}
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Yeni Stok Ekle</h1>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Stok Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="" class="form" id="stock-form">
                        <div class="form-group">
                            <label for="medication_id">İlaç *</label>
                            <select name="medication_id" id="medication_id" class="form-control" required>
                                <option value="">-- İlaç Seçin --</option>
                                <?php foreach ($medications as $medication): ?>
                                    <option value="<?php echo $medication['id']; ?>" <?php echo (isset($_POST['medication_id']) && $_POST['medication_id'] == $medication['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($medication['name']); ?> (<?php echo htmlspecialchars($medication['unit']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block">Stok eklemek istediğiniz ilacı seçin</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Miktar *</label>
                            <div class="input-group">
                                <input type="number" name="quantity" id="quantity" class="form-control" required min="0" step="0.01" value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '0'; ?>">
                                <span class="input-group-addon" id="unit-display">Birim</span>
                            </div>
                            <p class="help-block">Stoğa eklenecek miktarı girin</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="reorder_level">Kritik Stok Seviyesi *</label>
                            <div class="input-group">
                                <input type="number" name="reorder_level" id="reorder_level" class="form-control" required min="0" step="0.01" value="<?php echo isset($_POST['reorder_level']) ? htmlspecialchars($_POST['reorder_level']) : '10'; ?>">
                                <span class="input-group-addon" id="reorder-unit-display">Birim</span>
                            </div>
                            <p class="help-block">Stok bu seviyenin altına düştüğünde uyarı verilir</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notlar</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <p class="help-block">Stok girişi ile ilgili ek bilgiler (opsiyonel)</p>
                        </div>
                        
                        <div class="form-group">
                            <a href="stock_list.php" class="btn btn-default">İptal</a>
                            <button type="submit" class="btn btn-primary">Stoğu Kaydet</button>
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
                    <p>Bu ekrandan yeni bir stok kaydı oluşturabilirsiniz. Her ilaç için yalnızca bir stok kaydı olabilir.</p>
                    <p><strong>Önemli:</strong> Eğer ilaç zaten stok sisteminde kayıtlıysa, bu sayfadan ekleme yapamazsınız. Bunun yerine 'Stok Düzeltme' veya 'Stok Girişi' sayfasını kullanmalısınız.</p>
                    <p>Mevcut stokları görüntülemek için <a href="stock_list.php">Stok Listesi</a> sayfasını ziyaret ediniz.</p>
                    
                    <hr>
                    <h4>Tanımlar</h4>
                    <dl>
                        <dt>Miktar</dt>
                        <dd>İlacın stokta bulunan toplam miktarını ifade eder.</dd>
                        
                        <dt>Kritik Stok Seviyesi</dt>
                        <dd>Stok bu seviyenin altına düştüğünde sistem uyarı verecektir. Bu sayede zamanında yeni sipariş vermeniz hatırlatılır.</dd>
                    </dl>
                    
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
    // İlaç seçildiğinde birim göster
    $('#medication_id').change(function() {
        var selectedOption = $('#medication_id option:selected');
        var unitText = selectedOption.text().match(/\((.*?)\)/);
        
        if (unitText && unitText[1]) {
            $('#unit-display').text(unitText[1]);
            $('#reorder-unit-display').text(unitText[1]);
        } else {
            $('#unit-display').text('Birim');
            $('#reorder-unit-display').text('Birim');
        }
    });
    
    // Sayfa yüklendiğinde seçili ilaç varsa birimi göster
    if ($('#medication_id').val()) {
        $('#medication_id').change();
    }
    
    // Form doğrulama
    $('#stock-form').submit(function(event) {
        var medicationId = $('#medication_id').val();
        var quantity = parseFloat($('#quantity').val());
        var reorderLevel = parseFloat($('#reorder_level').val());
        
        if (!medicationId) {
            alert('Lütfen bir ilaç seçin!');
            $('#medication_id').focus();
            event.preventDefault();
            return false;
        }
        
        if (isNaN(quantity) || quantity < 0) {
            alert('Lütfen geçerli bir miktar girin!');
            $('#quantity').focus();
            event.preventDefault();
            return false;
        }
        
        if (isNaN(reorderLevel) || reorderLevel < 0) {
            alert('Lütfen geçerli bir kritik seviye girin!');
            $('#reorder_level').focus();
            event.preventDefault();
            return false;
        }
        
        return true;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>