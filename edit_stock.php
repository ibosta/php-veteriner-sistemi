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

// Stok ID'si
$stock_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ID geçerli mi kontrol et
if (!$stock_id) {
    $_SESSION['failure'] = "Geçersiz stok ID'si.";
    header('Location: stock_list.php');
    exit();
}

// Stok bilgilerini al
$db->join("medications m", "s.medication_id = m.id", "LEFT");
$db->where('s.id', $stock_id);
$stock = $db->getOne('stock s', 's.*, m.name as medication_name, m.unit as medication_unit');

// Stok mevcut mu kontrol et
if (!$stock) {
    $_SESSION['failure'] = "Stok kaydı bulunamadı.";
    header('Location: stock_list.php');
    exit();
}

// İlk ziyaret mi yoksa form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_FLOAT);
    $reorder_level = filter_input(INPUT_POST, 'reorder_level', FILTER_VALIDATE_FLOAT);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $old_quantity = filter_input(INPUT_POST, 'old_quantity', FILTER_VALIDATE_FLOAT);
    
    // Hata kontrolü
    $errors = array();
    
    if ($quantity === false || $quantity < 0) {
        $errors[] = "Geçerli bir miktar girin.";
    }
    
    if ($reorder_level === false || $reorder_level < 0) {
        $errors[] = "Geçerli bir kritik seviye girin.";
    }
    
    // Hata yoksa güncelle
    if (empty($errors)) {
        $data = array(
            'quantity' => $quantity,
            'reorder_level' => $reorder_level,
            'updated_at' => $current_date
        );
        
        $db->where('id', $stock_id);
        $status = $db->update('stock', $data);
        
        if ($status) {
            // Stok geçmişi tablosu var mı kontrol et
            $tables = $db->rawQuery('SHOW TABLES LIKE "stock_history"');
            $historyTableExists = (count($tables) > 0);
            
            // Yoksa oluştur
            if (!$historyTableExists) {
                createStockHistoryTable($db);
            }
            
            // Miktar değişikliği olduysa geçmişe kaydet
            if ($quantity != $old_quantity) {
                $change = $quantity - $old_quantity;
                $type = ($change > 0) ? 'add' : 'subtract';
                
                $history_data = array(
                    'medication_id' => $stock['medication_id'],
                    'quantity_change' => abs($change),
                    'type' => ($change != 0) ? $type : 'adjust',
                    'notes' => $notes ?: 'Stok düzenlemesi',
                    'created_at' => $current_date,
                    'created_by' => $_SESSION['user_id']
                );
                
                $db->insert('stock_history', $history_data);
            }
            
            $_SESSION['success'] = "Stok başarıyla güncellendi.";
            header('Location: stock_list.php');
            exit();
        } else {
            $_SESSION['failure'] = "Stok güncellenirken bir hata oluştu: " . $db->getLastError();
        }
    } else {
        $_SESSION['failure'] = implode('<br>', $errors);
    }
}

include_once 'includes/header.php';

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
            <h1 class="page-header">Stok Düzenle - <?php echo htmlspecialchars($stock['medication_name']); ?></h1>
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
                        <input type="hidden" name="old_quantity" value="<?php echo $stock['quantity']; ?>">
                        
                        <div class="form-group">
                            <label for="medication">İlaç</label>
                            <input type="text" id="medication" class="form-control" value="<?php echo htmlspecialchars($stock['medication_name']); ?>" readonly>
                            <p class="help-block">İlaç ismi değiştirilemez. Farklı bir ilaç için yeni stok kaydı oluşturun.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Miktar *</label>
                            <div class="input-group">
                                <input type="number" name="quantity" id="quantity" class="form-control" required min="0" step="0.01" value="<?php echo $stock['quantity']; ?>">
                                <span class="input-group-addon"><?php echo htmlspecialchars($stock['medication_unit']); ?></span>
                            </div>
                            <p class="help-block">Güncel stok miktarını girin</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="reorder_level">Kritik Stok Seviyesi *</label>
                            <div class="input-group">
                                <input type="number" name="reorder_level" id="reorder_level" class="form-control" required min="0" step="0.01" value="<?php echo $stock['reorder_level']; ?>">
                                <span class="input-group-addon"><?php echo htmlspecialchars($stock['medication_unit']); ?></span>
                            </div>
                            <p class="help-block">Stok bu seviyenin altına düştüğünde uyarı verilir</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notlar</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <p class="help-block">Stok değişikliği ile ilgili ek bilgiler (opsiyonel)</p>
                        </div>
                        
                        <div class="form-group">
                            <a href="stock_list.php" class="btn btn-default">İptal</a>
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
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
                    <p>Bu ekrandan seçili ilacın stok bilgilerini güncelleyebilirsiniz.</p>
                    <p><strong>Önemli:</strong> Stok miktarını değiştirdiğinizde, değişiklik stok geçmişine kaydedilecektir.</p>
                    
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> Büyük miktarlarda stok eklemesi yapmak istiyorsanız, "Stok Girişi" sayfasını kullanmanız önerilir.
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Stok Durumu:</strong>
                        <?php if ($stock['quantity'] <= 0): ?>
                            <span class="label label-danger">Tükendi</span>
                        <?php elseif ($stock['quantity'] <= $stock['reorder_level'] && $stock['reorder_level'] > 0): ?>
                            <span class="label label-warning">Kritik Seviye</span>
                        <?php else: ?>
                            <span class="label label-success">Yeterli</span>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    <div class="well well-sm">
                        <p class="text-muted">
                            <strong>Stok ID:</strong> <?php echo $stock_id; ?><br>
                            <strong>İlaç ID:</strong> <?php echo $stock['medication_id']; ?><br>
                            <strong>Oluşturulma:</strong> <?php echo date('d.m.Y H:i', strtotime($stock['created_at'])); ?><br>
                            <?php if (!empty($stock['updated_at'])): ?>
                                <strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($stock['updated_at'])); ?><br>
                            <?php endif; ?>
                            <strong>Şu An:</strong> <?php echo date('d.m.Y H:i', strtotime($current_date)); ?><br>
                            <strong>Kullanıcı:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </p>
                    </div>
                    
                    <div class="text-center">
                        <a href="stock_history.php?medication_id=<?php echo $stock['medication_id']; ?>" class="btn btn-info">
                            <i class="fa fa-history"></i> Stok Geçmişini Görüntüle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Form doğrulama
    $('#stock-form').submit(function(event) {
        var quantity = parseFloat($('#quantity').val());
        var reorderLevel = parseFloat($('#reorder_level').val());
        var oldQuantity = parseFloat($('input[name="old_quantity"]').val());
        
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
        
        // Miktar değişiyorsa onay al
        if (quantity !== oldQuantity) {
            if (!confirm('Stok miktarı değiştirilecek. Devam etmek istiyor musunuz?')) {
                event.preventDefault();
                return false;
            }
        }
        
        return true;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>