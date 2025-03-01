<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Oturum değişkenleri
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'ibosta';
}

// Current date and time for logging
$current_date = date('Y-m-d H:i:s');

// DB instance
$db = getDbInstance();

// İlaçları al (dropdown listesi için)
$db->join("stock s", "m.id = s.medication_id", "LEFT");
$db->orderBy("m.name", "ASC");
$medications = $db->get('medications m', null, 'm.id, m.name, m.unit, s.quantity, s.id as stock_id');

// Stok tablosu var mı kontrol et
$tableExists = false;
$tables = $db->rawQuery('SHOW TABLES LIKE "stock"');
if (count($tables) > 0) {
    $tableExists = true;
}

// İlk ziyaret mi yoksa form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Stok olmayan ilaçlar için stok tablosunu oluştur
    if (!$tableExists) {
        createStockTable($db);
        $tableExists = true;
        
        // Tüm ilaçlar için boş stok kayıtları oluştur
        $db->rawQuery("INSERT INTO stock (medication_id, quantity, reorder_level, created_at) 
                      SELECT id, 0, 10, NOW() FROM medications 
                      WHERE id NOT IN (SELECT medication_id FROM stock)");
    }
    
    // Form verilerini al
    $medication_id = filter_input(INPUT_POST, 'medication_id', FILTER_VALIDATE_INT);
    $adjustment_type = filter_input(INPUT_POST, 'adjustment_type', FILTER_SANITIZE_STRING);
    $adjustment_quantity = filter_input(INPUT_POST, 'adjustment_quantity', FILTER_VALIDATE_FLOAT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Hata kontrolü
    $errors = array();
    
    if (!$medication_id) {
        $errors[] = "Lütfen bir ilaç seçin.";
    }
    
    if (!$adjustment_type || !in_array($adjustment_type, ['add', 'subtract', 'set'])) {
        $errors[] = "Geçersiz düzenleme tipi.";
    }
    
    if ($adjustment_quantity === false || $adjustment_quantity <= 0) {
        $errors[] = "Geçerli bir miktar girin (sıfırdan büyük olmalı).";
    }
    
    if (empty($reason)) {
        $errors[] = "Lütfen bir düzenleme nedeni seçin.";
    }
    
    // İlaç için stok kaydı bul veya oluştur
    $db->where('medication_id', $medication_id);
    $existing_stock = $db->getOne('stock');
    
    if (!$existing_stock) {
        // Stok kaydı yoksa oluştur
        $stock_data = array(
            'medication_id' => $medication_id,
            'quantity' => 0,
            'reorder_level' => 10,
            'created_at' => $current_date
        );
        
        $stock_id = $db->insert('stock', $stock_data);
        
        if (!$stock_id) {
            $errors[] = "Stok kaydı oluşturulurken bir hata oluştu.";
        }
        
        $db->where('medication_id', $medication_id);
        $existing_stock = $db->getOne('stock');
    }
    
    // Stok geçmişi tablosunu kontrol et ve oluştur
    $historyTableExists = false;
    $tables = $db->rawQuery('SHOW TABLES LIKE "stock_history"');
    if (count($tables) > 0) {
        $historyTableExists = true;
    } else {
        createStockHistoryTable($db);
        $historyTableExists = true;
    }
    
    // Hata yoksa stok düzenlemesi yap
    if (empty($errors)) {
        // Yeni stok miktarını hesapla
        $current_quantity = $existing_stock['quantity'];
        $new_quantity = $current_quantity;
        
        switch ($adjustment_type) {
            case 'add':
                $new_quantity = $current_quantity + $adjustment_quantity;
                break;
                
            case 'subtract':
                $new_quantity = max(0, $current_quantity - $adjustment_quantity);
                // Eğer yeterli stok yoksa
                if ($current_quantity < $adjustment_quantity) {
                    $adjustment_quantity = $current_quantity; // Gerçek azaltılan miktar
                }
                break;
                
            case 'set':
                $new_quantity = $adjustment_quantity;
                break;
        }
        
        // Stok tablosunu güncelle
        $data = array(
            'quantity' => $new_quantity,
            'updated_at' => $current_date
        );
        
        $db->where('id', $existing_stock['id']);
        $status = $db->update('stock', $data);
        
        if ($status) {
            // Stok geçmişine kaydet
            $type = ($adjustment_type == 'add') ? 'add' : (($adjustment_type == 'subtract') ? 'subtract' : 'adjust');
            
            if ($adjustment_type == 'set') {
                // 'set' için değişim miktarını hesapla
                $change = abs($new_quantity - $current_quantity);
                $type = ($new_quantity > $current_quantity) ? 'add' : 'subtract';
                
                // Miktar aynıysa, değişim sıfır olur
                if ($change == 0) {
                    $type = 'adjust';
                    $change = 0;
                }
                
                $adjustment_quantity = $change;
            }
            
            $history_data = array(
                'medication_id' => $medication_id,
                'quantity_change' => $adjustment_quantity,
                'type' => $type,
                'reference_type' => 'adjustment',
                'notes' => $reason . ($notes ? ': ' . $notes : ''),
                'created_at' => $current_date,
                'created_by' => $_SESSION['user_id']
            );
            
            $db->insert('stock_history', $history_data);
            
            $_SESSION['success'] = "Stok düzenlemesi başarıyla yapıldı. Yeni miktar: " . $new_quantity;
            header('Location: stock_list.php');
            exit();
        } else {
            $_SESSION['failure'] = "Stok düzenlenirken bir hata oluştu: " . $db->getLastError();
        }
    } else {
        $_SESSION['failure'] = implode('<br>', $errors);
    }
}

// Stok tablosu yoksa uyarı mesajı
if (!$tableExists) {
    $_SESSION['warning'] = "Stok tablosu veritabanında bulunamadı. Form gönderildiğinde otomatik olarak oluşturulacaktır.";
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
            <h1 class="page-header">Stok Düzeltme / Ayarlama</h1>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Stok Düzeltme Formu</h3>
                </div>
                <div class="panel-body">
                    <form method="post" action="" class="form" id="stock-adjustment-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="medication_id">İlaç *</label>
                                    <select name="medication_id" id="medication_id" class="form-control" required>
                                        <option value="">-- İlaç Seçin --</option>
                                        <?php foreach ($medications as $medication): ?>
                                            <option value="<?php echo $medication['id']; ?>" 
                                                    data-unit="<?php echo htmlspecialchars($medication['unit']); ?>"
                                                    data-stock="<?php echo $medication['quantity'] ?? 0; ?>"
                                                    data-stock-id="<?php echo $medication['stock_id'] ?? 0; ?>"
                                                    <?php echo (isset($_POST['medication_id']) && $_POST['medication_id'] == $medication['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($medication['name']); ?> 
                                                (Mevcut: <?php echo ($medication['quantity'] ?? 0) . ' ' . htmlspecialchars($medication['unit']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="help-block">Düzeltme yapmak istediğiniz ilacı seçin</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="adjustment_type">Düzeltme Türü *</label>
                                    <select name="adjustment_type" id="adjustment_type" class="form-control" required>
                                        <option value="">-- Seçin --</option>
                                        <option value="add" <?php echo (isset($_POST['adjustment_type']) && $_POST['adjustment_type'] == 'add') ? 'selected' : ''; ?>>
                                            Ekle (Stok Arttır)
                                        </option>
                                        <option value="subtract" <?php echo (isset($_POST['adjustment_type']) && $_POST['adjustment_type'] == 'subtract') ? 'selected' : ''; ?>>
                                            Çıkar (Stok Azalt)
                                        </option>
                                        <option value="set" <?php echo (isset($_POST['adjustment_type']) && $_POST['adjustment_type'] == 'set') ? 'selected' : ''; ?>>
                                            Belirle (Miktarı ayarla)
                                        </option>
                                    </select>
                                    <p class="help-block">Yapılacak düzeltmenin türünü seçin</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="adjustment_quantity">Miktar *</label>
                                    <div class="input-group">
                                        <input type="number" name="adjustment_quantity" id="adjustment_quantity" class="form-control" required min="0" step="0.01" value="<?php echo isset($_POST['adjustment_quantity']) ? htmlspecialchars($_POST['adjustment_quantity']) : ''; ?>">
                                        <span class="input-group-addon" id="unit-display">Birim</span>
                                    </div>
                                    <p class="help-block">Düzeltilecek miktarı girin</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="current_quantity">Mevcut Miktar</label>
                                    <div class="input-group">
                                        <input type="text" id="current_quantity" class="form-control" readonly value="0">
                                        <span class="input-group-addon" id="current-unit-display">Birim</span>
                                    </div>
                                    <p class="help-block">İlaç için mevcut stok miktarı</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="new_quantity">Düzeltme Sonrası Miktar</label>
                                    <div class="input-group">
                                        <input type="text" id="new_quantity" class="form-control" readonly value="0">
                                        <span class="input-group-addon" id="new-unit-display">Birim</span>
                                    </div>
                                    <p class="help-block">Düzeltme sonrası oluşacak miktar</p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reason">Düzeltme Nedeni *</label>
                                    <select name="reason" id="reason" class="form-control" required>
                                    <option value="">-- Neden Seçin --</option>
                                        <option value="Yeni Stok Girişi" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Yeni Stok Girişi') ? 'selected' : ''; ?>>
                                            Yeni Stok Girişi
                                        </option>
                                        <option value="Envanter Sayımı" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Envanter Sayımı') ? 'selected' : ''; ?>>
                                            Envanter Sayımı / Düzeltme
                                        </option>
                                        <option value="Hatalı Kayıt Düzeltme" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Hatalı Kayıt Düzeltme') ? 'selected' : ''; ?>>
                                            Hatalı Kayıt Düzeltme
                                        </option>
                                        <option value="Son Kullanma Tarihi Geçmiş" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Son Kullanma Tarihi Geçmiş') ? 'selected' : ''; ?>>
                                            Son Kullanma Tarihi Geçmiş
                                        </option>
                                        <option value="Zayi Olma" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Zayi Olma') ? 'selected' : ''; ?>>
                                            Zayi Olma / Bozulma
                                        </option>
                                        <option value="İade" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'İade') ? 'selected' : ''; ?>>
                                            İade
                                        </option>
                                        <option value="Diğer" <?php echo (isset($_POST['reason']) && $_POST['reason'] == 'Diğer') ? 'selected' : ''; ?>>
                                            Diğer
                                        </option>
                                    </select>
                                    <p class="help-block">Stok düzeltme nedenini seçin</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Ek Açıklama</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <p class="help-block">Stok düzeltmesi ile ilgili detaylı bilgiler (opsiyonel)</p>
                        </div>
                        
                        <div class="form-group">
                            <a href="stock_list.php" class="btn btn-default">İptal</a>
                            <button type="submit" class="btn btn-primary">Stok Düzeltmesini Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Bilgi</h3>
                </div>
                <div class="panel-body">
                    <p>Bu ekrandan seçtiğiniz ilacın stok bilgilerini düzeltebilirsiniz.</p>
                    
                    <div class="alert alert-info">
                        <h4><i class="fa fa-info-circle"></i> Düzeltme Türleri:</h4>
                        <ul>
                            <li><strong>Ekle:</strong> Mevcut stoğa belirtilen miktarı ekler</li>
                            <li><strong>Çıkar:</strong> Mevcut stoktan belirtilen miktarı azaltır</li>
                            <li><strong>Belirle:</strong> Stok miktarını girilen değere ayarlar</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h4><i class="fa fa-warning"></i> Uyarı</h4>
                        <p>Yapılan tüm stok düzeltmeleri sistem tarafından kayıt altına alınmaktadır. Lütfen doğru bilgileri giriniz.</p>
                    </div>
                    
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">Hızlı İşlem Örnekleri</h4>
                        </div>
                        <div class="panel-body">
                            <ul>
                                <li><strong>Yeni stok almak için:</strong> "Ekle" seçeneğini kullanın</li>
                                <li><strong>Kullanım veya fire için:</strong> "Çıkar" seçeneğini kullanın</li>
                                <li><strong>Sayım sonrası düzeltme:</strong> "Belirle" seçeneğini kullanın</li>
                            </ul>
                        </div>
                    </div>
                    
                    <p class="text-center">
                        <a href="stock_list.php" class="btn btn-default">
                            <i class="fa fa-list"></i> Stok Listesine Dön
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // İlaç seçildiğinde birim ve stok bilgilerini güncelle
    $('#medication_id').change(function() {
        var selectedOption = $('#medication_id option:selected');
        var unitText = selectedOption.data('unit');
        var stockAmount = parseFloat(selectedOption.data('stock')) || 0;
        
        // Birim göster
        $('#unit-display').text(unitText);
        $('#current-unit-display').text(unitText);
        $('#new-unit-display').text(unitText);
        
        // Mevcut stok miktarını göster
        $('#current_quantity').val(stockAmount);
        
        // Yeni stok miktarını hesapla
        calculateNewQuantity(stockAmount);
    });
    
    // Düzeltme tipi veya miktarı değiştiğinde yeni stok miktarını hesapla
    $('#adjustment_type, #adjustment_quantity').change(function() {
        var stockAmount = parseFloat($('#current_quantity').val()) || 0;
        calculateNewQuantity(stockAmount);
    });
    
    // Miktara da input event ekleyelim (kullanıcı yazdıkça güncellensin)
    $('#adjustment_quantity').on('input', function() {
        var stockAmount = parseFloat($('#current_quantity').val()) || 0;
        calculateNewQuantity(stockAmount);
    });
    
    // Sayfa yüklendiğinde seçili ilaç varsa bilgileri göster
    if ($('#medication_id').val()) {
        $('#medication_id').change();
    }
    
    // Yeni stok miktarını hesaplayan fonksiyon
    function calculateNewQuantity(currentStock) {
        var adjustmentType = $('#adjustment_type').val();
        var adjustmentQuantity = parseFloat($('#adjustment_quantity').val()) || 0;
        var newQuantity = currentStock;
        
        // Düzeltme tipine göre hesapla
        switch (adjustmentType) {
            case 'add':
                newQuantity = currentStock + adjustmentQuantity;
                break;
                
            case 'subtract':
                newQuantity = Math.max(0, currentStock - adjustmentQuantity);
                break;
                
            case 'set':
                newQuantity = adjustmentQuantity;
                break;
                
            default:
                // Düzeltme tipi seçilmemişse değişiklik yok
                break;
        }
        
        // Sonucu göster (iki ondalık basamakla formatla)
        $('#new_quantity').val(newQuantity.toFixed(2));
        
        // Düzeltme sonrası stok negatif olamaz, uyarı ver
        if (adjustmentType === 'subtract' && adjustmentQuantity > currentStock) {
            $('#new_quantity').closest('.form-group').addClass('has-warning');
            $('#new_quantity').after('<p class="help-block text-warning" id="stock-warning">Uyarı: Çıkarılmak istenen miktar mevcut stoktan fazla. Maksimum ' + currentStock.toFixed(2) + ' çıkarılabilir.</p>');
        } else {
            $('#new_quantity').closest('.form-group').removeClass('has-warning');
            $('#stock-warning').remove();
        }
    }
    
    // Form doğrulama
    $('#stock-adjustment-form').submit(function(event) {
        var medicationId = $('#medication_id').val();
        var adjustmentType = $('#adjustment_type').val();
        var adjustmentQuantity = parseFloat($('#adjustment_quantity').val());
        var reason = $('#reason').val();
        
        if (!medicationId) {
            alert('Lütfen bir ilaç seçin!');
            $('#medication_id').focus();
            event.preventDefault();
            return false;
        }
        
        if (!adjustmentType) {
            alert('Lütfen bir düzeltme türü seçin!');
            $('#adjustment_type').focus();
            event.preventDefault();
            return false;
        }
        
        if (isNaN(adjustmentQuantity) || adjustmentQuantity <= 0) {
            alert('Lütfen geçerli bir miktar girin!');
            $('#adjustment_quantity').focus();
            event.preventDefault();
            return false;
        }
        
        if (!reason) {
            alert('Lütfen bir düzeltme nedeni seçin!');
            $('#reason').focus();
            event.preventDefault();
            return false;
        }
        
        // Eğer miktar mevcut stoktan fazla çıkarılacaksa onay iste
        var currentStock = parseFloat($('#current_quantity').val()) || 0;
        if (adjustmentType === 'subtract' && adjustmentQuantity > currentStock) {
            if (!confirm('Çıkarılmak istenen miktar mevcut stoktan fazla! Stok tamamen sıfırlanacak. Devam etmek istiyor musunuz?')) {
                event.preventDefault();
                return false;
            }
        }
        
        return true;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>