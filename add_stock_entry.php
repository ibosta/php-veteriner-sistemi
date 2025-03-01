<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Oturum değişkenleri
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if (!isset($_SESSION['user_name'])) {
    $_SESSION['user_name'] = 'DR. İbrahim Taşkıran';
}

// Current date and time for logging
$current_date = date('Y-m-d H:i:s');

// DB instance
$db = getDbInstance();

// İlaçları al (dropdown listesi için)
$db->join("stock s", "m.id = s.medication_id", "LEFT");
$db->orderBy("m.name", "ASC");
$medications = $db->get('medications m', null, 'm.id, m.name, m.unit, s.quantity, s.id as stock_id, s.reorder_level');

// Tedarikçileri al (dropdown için)
$suppliers = $db->orderBy('name', 'ASC')->get('suppliers');
if (!$suppliers) {
    $suppliers = []; // Eğer tedarikçi tablosu yoksa boş array kullan
}

// Stok tablosu var mı kontrol et
$tableExists = false;
$tables = $db->rawQuery('SHOW TABLES LIKE "stock"');
if (count($tables) > 0) {
    $tableExists = true;
}

// Stok giriş tablosu var mı kontrol et
$entryTableExists = false;
$tables = $db->rawQuery('SHOW TABLES LIKE "stock_entries"');
if (count($tables) > 0) {
    $entryTableExists = true;
}

// İlk ziyaret mi yoksa form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $entry_date = filter_input(INPUT_POST, 'entry_date', FILTER_SANITIZE_STRING);
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $invoice_no = filter_input(INPUT_POST, 'invoice_no', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $medication_ids = $_POST['medication_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    $expiry_dates = $_POST['expiry_date'] ?? [];
    $batch_nos = $_POST['batch_no'] ?? [];
    
    // Giriş tarihi yoksa şimdiki tarihi kullan
    if (empty($entry_date)) {
        $entry_date = date('Y-m-d', strtotime($current_date));
    }
    
    // Hata kontrolü
    $errors = array();
    
    if (empty($medication_ids) || count($medication_ids) == 0) {
        $errors[] = "En az bir ilaç girmelisiniz.";
    }
    
    if (empty($entry_date)) {
        $errors[] = "Giriş tarihi gereklidir.";
    }
    
    // İlaçları kontrol et
    $valid_medications = 0;
    foreach ($medication_ids as $key => $med_id) {
        if (!empty($med_id) && !empty($quantities[$key]) && $quantities[$key] > 0) {
            $valid_medications++;
        }
    }
    
    if ($valid_medications == 0) {
        $errors[] = "En az bir geçerli ilaç girişi yapmalısınız.";
    }
    
    // Stok ve stok giriş tablolarını oluştur (yoksa)
    if (!$tableExists) {
        createStockTable($db);
        $tableExists = true;
    }
    
    if (!$entryTableExists) {
        createStockEntryTable($db);
        $entryTableExists = true;
        
        // Stock entry items tablosunu da oluştur
        createStockEntryItemsTable($db);
    }
    
    // Stok geçmişi tablosu var mı kontrol et
    $historyTableExists = false;
    $tables = $db->rawQuery('SHOW TABLES LIKE "stock_history"');
    if (count($tables) > 0) {
        $historyTableExists = true;
    } else {
        createStockHistoryTable($db);
        $historyTableExists = true;
    }
    
   // Hata yoksa stok girişini kaydet
if (empty($errors)) {
    // Ana stok giriş kaydı oluştur
    $entry_data = array(
        'entry_date' => $entry_date,
        'supplier_id' => $supplier_id ?: null,
        'invoice_no' => $invoice_no,
        'notes' => $notes,
        'created_at' => $current_date,
        'created_by' => $_SESSION['user_id']
    );
    
    $db->startTransaction();
    
    try {
        $entry_id = $db->insert('stock_entries', $entry_data);
        
        if (!$entry_id) {
            throw new Exception("Stok giriş kaydı oluşturulurken hata: " . $db->getLastError());
        }
        
        $success_count = 0;
        $total_items = 0;
        
        // Stok giriş kalemlerini kaydet
        foreach ($medication_ids as $key => $med_id) {
            if (empty($med_id) || empty($quantities[$key]) || $quantities[$key] <= 0) {
                continue; // Boş veya geçersiz kayıtları atla
            }
            
            $total_items++;
            
            // İlaç için stok kaydı var mı kontrol et
            $db->where('medication_id', $med_id);
            $existing_stock = $db->getOne('stock');
            
            // Yoksa yeni kayıt oluştur
            if (!$existing_stock) {
                // İlgili ilaç bilgilerini getir
                $medication = $db->where('id', $med_id)->getOne('medications');
                
                $stock_data = array(
                    'medication_id' => $med_id,
                    'quantity' => 0, // Önce sıfır olarak oluştur, sonra güncelleyeceğiz
                    'reorder_level' => 10, // Varsayılan kritik seviye
                    'created_at' => $current_date
                );
                
                $stock_id = $db->insert('stock', $stock_data);
                
                if (!$stock_id) {
                    throw new Exception("Stok kaydı oluşturulurken hata: " . $db->getLastError());
                }
                
                $db->where('id', $stock_id);
                $existing_stock = $db->getOne('stock');
            }
            
            // Stok giriş kalemini kaydet
            $item_data = array(
                'entry_id' => $entry_id,
                'medication_id' => $med_id,
                'quantity' => $quantities[$key],
                'unit_price' => $unit_prices[$key] ?: 0,
                'expiry_date' => !empty($expiry_dates[$key]) ? $expiry_dates[$key] : null,
                'batch_no' => $batch_nos[$key] ?: null,
                'created_at' => $current_date
            );
            
            $item_id = $db->insert('stock_entry_items', $item_data);
            
            if (!$item_id) {
                throw new Exception("Stok giriş kalemi kaydedilirken hata: " . $db->getLastError());
            }
            
            // Stok tablosundaki miktarı güncelle
            $new_quantity = $existing_stock['quantity'] + $quantities[$key];
            
            $db->where('id', $existing_stock['id']);
            $update_status = $db->update('stock', array(
                'quantity' => $new_quantity,
                'updated_at' => $current_date
            ));
            
            if (!$update_status) {
                throw new Exception("Stok tablosu güncellenirken hata: " . $db->getLastError());
            }
            
            // Medications tablosunu güncelle
            $db_med = getDbInstance();
            $db_med->where('id', $med_id);
            $current_med = $db_med->getOne('medications');
            
            if (!$current_med) {
                throw new Exception("İlaç bulunamadı: ID " . $med_id);
            }
            
            $new_med_quantity = $current_med['quantity'] + $quantities[$key];
            
            $db_med->where('id', $med_id);
            $med_update_status = $db_med->update('medications', array(
                'quantity' => $new_med_quantity,
                'updated_at' => $current_date
            ));
            
            if (!$med_update_status) {
                throw new Exception("İlaç stoğu güncellenirken hata: " . $db_med->getLastError());
            }
            
            // Stok geçmişine kaydet
            $history_data = array(
                'medication_id' => $med_id,
                'quantity_change' => $quantities[$key],
                'type' => 'add',
                'reference_type' => 'stock_entry',
                'reference_id' => $entry_id,
                'notes' => "Stok Girişi #$entry_id" . (!empty($invoice_no) ? " - Fatura: $invoice_no" : ""),
                'created_at' => $current_date,
                'created_by' => $_SESSION['user_id']
            );
            
            $history_id = $db->insert('stock_history', $history_data);
            
            if (!$history_id) {
                throw new Exception("Stok geçmişi kaydedilirken hata: " . $db->getLastError());
            }
            
            $success_count++;
        }
        
        if ($success_count > 0) {
            $db->commit();
            $_SESSION['success'] = "Stok girişi başarıyla kaydedildi. Toplam $success_count kalem işlendi.";
            header('Location: stock_list.php');
            exit();
        } else {
            throw new Exception("Stok girişi kaydedildi ancak hiçbir kalem işlenemedi.");
        }
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['failure'] = $e->getMessage();
    }
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
 * Stok giriş tablosunu oluşturma fonksiyonu
 */
function createStockEntryTable($db) {
    $query = "CREATE TABLE IF NOT EXISTS `stock_entries` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `entry_date` date NOT NULL,
        `supplier_id` int(11) DEFAULT NULL,
        `invoice_no` varchar(50) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` datetime NOT NULL,
        `created_by` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `supplier_id` (`supplier_id`),
        KEY `created_by` (`created_by`),
        KEY `entry_date` (`entry_date`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $db->rawQuery($query);
}

/**
 * Stok giriş kalemleri tablosunu oluşturma fonksiyonu
 */
function createStockEntryItemsTable($db) {
    $query = "CREATE TABLE IF NOT EXISTS `stock_entry_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `entry_id` int(11) NOT NULL,
        `medication_id` int(11) NOT NULL,
        `quantity` float NOT NULL,
        `unit_price` decimal(10,2) DEFAULT 0.00,
        `expiry_date` date DEFAULT NULL,
        `batch_no` varchar(50) DEFAULT NULL,
        `created_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `entry_id` (`entry_id`),
        KEY `medication_id` (`medication_id`),
        CONSTRAINT `stock_entry_items_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `stock_entries` (`id`) ON DELETE CASCADE,
        CONSTRAINT `stock_entry_items_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE
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
            <h1 class="page-header">Yeni Stok Girişi</h1>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <h4><i class="fa fa-info-circle"></i> Stok tablosu bulunamadı</h4>
        <p>Stok tablosu henüz oluşturulmamış. Form gönderildiğinde otomatik olarak oluşturulacaktır.</p>
    </div>
    <?php endif; ?>
    
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Stok Giriş Formu</h3>
        </div>
        <div class="panel-body">
            <form method="post" action="" id="stock-entry-form">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="entry_date">Giriş Tarihi *</label>
                            <input type="date" name="entry_date" id="entry_date" class="form-control" required value="<?php echo isset($_POST['entry_date']) ? htmlspecialchars($_POST['entry_date']) : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="supplier_id">Tedarikçi</label>
                            <select name="supplier_id" id="supplier_id" class="form-control">
                                <option value="">-- Tedarikçi Seçin --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id'                                <option value="">-- Tedarikçi Seçin --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>" <?php echo (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="invoice_no">Fatura / İrsaliye No</label>
                            <input type="text" name="invoice_no" id="invoice_no" class="form-control" value="<?php echo isset($_POST['invoice_no']) ? htmlspecialchars($_POST['invoice_no']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-success btn-block" id="add-item-row">
                                <i class="fa fa-plus"></i> Yeni İlaç Ekle
                            </button>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="stock-items-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">İlaç *</th>
                                <th style="width: 15%;">Miktar *</th>
                                <th style="width: 15%;">Birim Fiyat (₺)</th>
                                <th style="width: 15%;">Son Kullanma Tarihi</th>
                                <th style="width: 15%;">Parti No</th>
                                <th style="width: 10%;">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isset($_POST['medication_id'])): ?>
                                <?php foreach ($_POST['medication_id'] as $key => $med_id): ?>
                                    <tr class="item-row">
                                        <td>
                                            <select name="medication_id[]" class="form-control medication-select" required>
                                                <option value="">-- İlaç Seçin --</option>
                                                <?php foreach ($medications as $medication): ?>
                                                    <option value="<?php echo $medication['id']; ?>" 
                                                            data-unit="<?php echo htmlspecialchars($medication['unit']); ?>"
                                                            <?php echo ($med_id == $medication['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($medication['name']); ?>
                                                        (<?php echo htmlspecialchars($medication['unit']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" name="quantity[]" class="form-control" min="0.01" step="0.01" required value="<?php echo isset($_POST['quantity'][$key]) ? htmlspecialchars($_POST['quantity'][$key]) : ''; ?>">
                                                <span class="input-group-addon unit-display">Birim</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <span class="input-group-addon">₺</span>
                                                <input type="number" name="unit_price[]" class="form-control" min="0" step="0.01" value="<?php echo isset($_POST['unit_price'][$key]) ? htmlspecialchars($_POST['unit_price'][$key]) : '0'; ?>">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="date" name="expiry_date[]" class="form-control" value="<?php echo isset($_POST['expiry_date'][$key]) ? htmlspecialchars($_POST['expiry_date'][$key]) : ''; ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="batch_no[]" class="form-control" value="<?php echo isset($_POST['batch_no'][$key]) ? htmlspecialchars($_POST['batch_no'][$key]) : ''; ?>">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger remove-item-row">
                                                <i class="fa fa-trash"></i> Sil
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="item-row">
                                    <td>
                                        <select name="medication_id[]" class="form-control medication-select" required>
                                            <option value="">-- İlaç Seçin --</option>
                                            <?php foreach ($medications as $medication): ?>
                                                <option value="<?php echo $medication['id']; ?>" 
                                                        data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                                                    <?php echo htmlspecialchars($medication['name']); ?>
                                                    (<?php echo htmlspecialchars($medication['unit']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <input type="number" name="quantity[]" class="form-control" min="0.01" step="0.01" required>
                                            <span class="input-group-addon unit-display">Birim</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-addon">₺</span>
                                            <input type="number" name="unit_price[]" class="form-control" min="0" step="0.01" value="0">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="date" name="expiry_date[]" class="form-control">
                                    </td>
                                    <td>
                                        <input type="text" name="batch_no[]" class="form-control">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger remove-item-row">
                                            <i class="fa fa-trash"></i> Sil
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right">
                                    <div class="form-group">
                                        <label for="notes">Notlar</label>
                                        <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fa fa-save"></i> Stok Girişini Kaydet
                                    </button>
                                    <a href="stock_list.php" class="btn btn-default btn-lg">
                                        <i class="fa fa-arrow-left"></i> İptal
                                    </a>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bilgi Paneli -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">Stok Girişi Hakkında</h3>
        </div>
        <div class="panel-body">
            <p>Bu ekrandan sisteme toplu olarak stok girişi yapabilirsiniz. Tedarikçiden gelen ilaçları fatura veya irsaliye bazında kaydedebilirsiniz.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <h4><i class="fa fa-info-circle"></i> Kullanım Kılavuzu</h4>
                        <ul>
                            <li>Giriş tarihini, tedarikçiyi ve varsa fatura/irsaliye numarasını girin</li>
                            <li>Her satırda bir ilaç seçerek miktarını belirtin</li>
                            <li>"Yeni İlaç Ekle" düğmesine tıklayarak birden fazla ilaç ekleyebilirsiniz</li>
                            <li>İlaçların son kullanma tarihleri ve parti numaralarını girerek takip yapabilirsiniz</li>
                            <li>Birim fiyatlar opsiyoneldir, raporlama amaçlı kullanılır</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="alert alert-warning">
                        <h4><i class="fa fa-warning"></i> Dikkat Edilecekler</h4>
                        <ul>
                            <li>Lütfen ilaç birimlerine (tablet, kutu, şişe vb.) dikkat ederek doğru miktar girin</li>
                            <li>Stok girişi yapıldıktan sonra sistem otomatik olarak ilacın stok miktarını arttıracaktır</li>
                            <li>Her stok girişi stok geçmişi kayıtlarında tutulur ve izlenebilir</li>
                            <li>Son kullanma tarihleri yaklaşan ilaçlar için uyarı sistem tarafından otomatik gösterilecektir</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <p class="text-muted text-center">
                <small>Kayıt Tarihi: <?php echo date('d.m.Y H:i', strtotime($current_date)); ?> | Kullanıcı: <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
            </p>
        </div>
    </div>
</div>

<!-- Yeni satır şablonu (JavaScript için) -->
<table style="display: none;">
    <tr id="item-row-template" class="item-row">
        <td>
            <select name="medication_id[]" class="form-control medication-select" required>
                <option value="">-- İlaç Seçin --</option>
                <?php foreach ($medications as $medication): ?>
                    <option value="<?php echo $medication['id']; ?>" 
                            data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                        <?php echo htmlspecialchars($medication['name']); ?>
                        (<?php echo htmlspecialchars($medication['unit']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <div class="input-group">
                <input type="number" name="quantity[]" class="form-control" min="0.01" step="0.01" required>
                <span class="input-group-addon unit-display">Birim</span>
            </div>
        </td>
        <td>
            <div class="input-group">
                <span class="input-group-addon">₺</span>
                <input type="number" name="unit_price[]" class="form-control" min="0" step="0.01" value="0">
            </div>
        </td>
        <td>
            <input type="date" name="expiry_date[]" class="form-control">
        </td>
        <td>
            <input type="text" name="batch_no[]" class="form-control">
        </td>
        <td>
            <button type="button" class="btn btn-danger remove-item-row">
                <i class="fa fa-trash"></i> Sil
            </button>
        </td>
    </tr>
</table>

<script>
$(document).ready(function() {
    // İlaç seçildiğinde birim göster
    $(document).on('change', '.medication-select', function() {
        var selectedOption = $(this).find('option:selected');
        var unitText = selectedOption.data('unit') || 'Birim';
        $(this).closest('tr').find('.unit-display').text(unitText);
    });
    
    // Yeni satır ekle
    $('#add-item-row').click(function() {
        var newRow = $('#item-row-template').clone();
        newRow.removeAttr('id');
        $('#stock-items-table tbody').append(newRow);
        
        // Select2 varsa yeni eklenen satır için de uygula
        if ($.fn.select2) {
            newRow.find('.medication-select').select2({
                placeholder: "İlaç seçin",
                allowClear: true
            });
        }
    });
    
    // Satır sil
    $(document).on('click', '.remove-item-row', function() {
        var rowCount = $('.item-row').length;
        if (rowCount > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('En az bir ilaç satırı bulunmalıdır!');
        }
    });
    
    // Form doğrulama
    $('#stock-entry-form').submit(function(event) {
        var isValid = true;
        var hasValidItem = false;
        
        // En az bir geçerli ilaç seçilmiş mi kontrol et
        $('.item-row').each(function() {
            var medId = $(this).find('select[name="medication_id[]"]').val();
            var quantity = $(this).find('input[name="quantity[]"]').val();
            
            if (medId && quantity > 0) {
                hasValidItem = true;
            }
        });
        
        if (!hasValidItem) {
            alert('Lütfen en az bir geçerli ilaç girişi yapın!');
            event.preventDefault();
            return false;
        }
        
        // Giriş tarihi kontrolü
        var entryDate = $('#entry_date').val();
        if (!entryDate) {
            alert('Lütfen giriş tarihi girin!');
            $('#entry_date').focus();
            event.preventDefault();
            return false;
        }
        
        return isValid;
    });
    
    // Select2 varsa uygula
    if ($.fn.select2) {
        $('.medication-select').select2({
            placeholder: "İlaç seçin",
            allowClear: true
        });
    }
    
    // Sayfada bulunan select'ler için birim bilgisini güncelle
    $('.medication-select').each(function() {
        var selectedOption = $(this).find('option:selected');
        if (selectedOption.val()) {
            var unitText = selectedOption.data('unit') || 'Birim';
            $(this).closest('tr').find('.unit-display').text(unitText);
        }
    });
    
    // Birim fiyatlar için para biçiminde gösterme
    $('.unit-price').on('blur', function() {
        var value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    // Satırları sırayla numaralandırmak için
    function updateRowNumbers() {
        var rowNum = 1;
        $('.item-row').each(function() {
            rowNum++;
        });
    }
    
    // Toplam tutarı hesapla
    function calculateTotals() {
        var totalAmount = 0;
        var totalItems = 0;
        
        $('.item-row').each(function() {
            var quantity = parseFloat($(this).find('input[name="quantity[]"]').val()) || 0;
            var unitPrice = parseFloat($(this).find('input[name="unit_price[]"]').val()) || 0;
            
            if (quantity > 0) {
                totalItems += 1;
                totalAmount += quantity * unitPrice;
            }
        });
        
        $('#total-items').text(totalItems);
        $('#total-amount').text(totalAmount.toFixed(2) + ' ₺');
    }
    
    // Miktar veya birim fiyat değiştiğinde toplamları güncelle
    $(document).on('input', 'input[name="quantity[]"], input[name="unit_price[]"]', function() {
        calculateTotals();
    });
    
    // Sayfa yüklendiğinde toplamları hesapla
    calculateTotals();
    
    // Son kullanma tarihleri için minimum değer bugün olarak ayarla
    var today = new Date().toISOString().split('T')[0];
    $('input[type="date"][name="expiry_date[]"]').attr('min', today);
    
    // Büyük stok girişi için kullanıcıya sor
    $('#stock-entry-form').submit(function(e) {
        var totalItems = $('.item-row').length;
        var formData = $(this).serialize();
        
        // 5'den fazla ilaç girişi yapılıyorsa onay iste
        if (totalItems > 5) {
            if (!confirm('Toplam ' + totalItems + ' adet ilaç için stok girişi yapacaksınız. Devam etmek istiyor musunuz?')) {
                e.preventDefault();
                return false;
            }
        }
        
        // Yine de devam et
        return true;
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>