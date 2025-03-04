<?php
session_start();

// Kullanıcı oturum kontrolü
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Veritabanı bağlantısı
$db = getDbInstance();

// Oturum açmış kullanıcının bilgilerini al
// Kullanıcı hem users hem de admin_accounts tablosunda kontrol edilecek
$user_id = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? '';

if (empty($user_id) || empty($user_name)) {
    $_SESSION['failure'] = "Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.";
    header('Location: login.php');
    exit();
}

// Admin mi yoksa normal kullanıcı mı olduğunu kontrol et
$is_admin_account = false;
if (isset($_SESSION['admin_type'])) {
    $is_admin_account = true;
}

// Hasta ID'sini URL'den al
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

// İlaçları getir
$db->join("stock s", "m.id = s.medication_id", "LEFT");
$db->orderBy("m.name", "ASC");
$medications = $db->get('medications m', null, 'm.id, m.name, m.unit, s.quantity, s.id as stock_id');

// Hastaları getir
if (!$patient_id) {
    $patients = $db->get('patients');
} else {
    // Hasta seçilmişse bilgilerini getir
    $db->where('id', $patient_id);
    $selected_patient = $db->getOne('patients');
    
    if (!$selected_patient) {
        $patient_id = null;
    }
}

// Form kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $diagnosis = filter_var($_POST['diagnosis'] ?? '', FILTER_UNSAFE_RAW);
    $diagnosis_details = filter_var($_POST['diagnosis_details'] ?? '', FILTER_UNSAFE_RAW);
    $notes = filter_var($_POST['notes'] ?? '', FILTER_UNSAFE_RAW);
    
    $errors = array();
    
    if (!$patient_id) {
        $errors[] = "Hasta seçimi gereklidir.";
    }
    
    if (empty($diagnosis)) {
        $errors[] = "Tanı bilgisi gereklidir.";
    }
    
    $medication_ids = $_POST['medication_id'] ?? array();
    $dosages = $_POST['dosage'] ?? array();
    $daily_usages = $_POST['daily_usage'] ?? array();
    $usage_periods = $_POST['usage_period'] ?? array();
    $medication_notes = $_POST['medication_notes'] ?? array();
    
    if (empty($medication_ids)) {
        $errors[] = "En az bir ilaç eklenmelidir.";
    }
    
    foreach ($medication_ids as $key => $medication_id) {
        if (empty($dosages[$key])) {
            $errors[] = "Tüm ilaçlar için doz bilgisi gereklidir.";
            break;
        }
        if (empty($daily_usages[$key])) {
            $errors[] = "Tüm ilaçlar için günlük kullanım bilgisi gereklidir.";
            break;
        }
        if (empty($usage_periods[$key])) {
            $errors[] = "Tüm ilaçlar için kullanım süresi gereklidir.";
            break;
        }
    }
    
    if (empty($errors)) {
        try {
            $db->startTransaction();
            
            // Geçerli sistem tarihi
            $current_datetime = date('Y-m-d H:i:s');
            
            // Kullanıcı türüne göre reçete kayıt verileri oluştur
            // Admin hesabı kullanıyorsa user_id olarak 1 (admin kullanıcısı) kullan
            $effective_user_id = $is_admin_account ? 1 : $user_id;
            
            // Reçete kaydet
            $prescription_data = array(
                'patient_id' => $patient_id,
                'user_id' => $effective_user_id,
                'diagnosis' => $diagnosis,
                'diagnosis_details' => $diagnosis_details ?: null,
                'notes' => $notes ?: null,
                'created_at' => $current_datetime
            );
            
            $prescription_id = $db->insert('prescriptions', $prescription_data);
            
            if (!$prescription_id) {
                throw new Exception("Reçete kaydedilirken bir hata oluştu.");
            }
            
            // İlaçları kaydet
            foreach ($medication_ids as $key => $medication_id) {
                if (empty($medication_id)) continue;
                
                // İlaç stoğunu kontrol et
                $db->where('medication_id', $medication_id);
                $stock = $db->getOne('stock');

                if (!$stock) {
                    throw new Exception("İlaç için stok kaydı bulunamadı.");
                }
                
                // Stok kontrolü
                $required_quantity = floatval($dosages[$key]);
                
                if ($stock['quantity'] < $required_quantity) {
                    $db->where('id', $medication_id);
                    $medication = $db->getOne('medications', 'name');
                    throw new Exception("'{$medication['name']}' ilacı için stok yetersiz. Mevcut: {$stock['quantity']}, Gerekli: {$required_quantity}");
                }
                
                // Stoktan düş
                $new_quantity = $stock['quantity'] - $required_quantity;
                $update_data = array(
                    'quantity' => $new_quantity, 
                    'updated_at' => $current_datetime
                );
                
                $db->where('id', $stock['id']);
                if (!$db->update('stock', $update_data)) {
                    throw new Exception("Stok güncellenirken bir hata oluştu.");
                }
                
                // Stok hareketi kaydet
                $stock_history_data = array(
                    'medication_id' => $medication_id,
                    'type' => 'subtract',
                    'quantity_change' => $required_quantity,
                    'reference_type' => 'prescription',
                    'reference_id' => $prescription_id,
                    'user_id' => $effective_user_id,
                    'notes' => "Reçete: Hasta ID #{$patient_id}, Tanı: {$diagnosis}, Kullanıcı: " . $user_name,
                    'created_at' => $current_datetime
                );
                
                if (!$db->insert('stock_history', $stock_history_data)) {
                    throw new Exception("Stok hareketi kaydedilemedi.");
                }
                
                // Reçete kalemleri
                $item_data = array(
                    'prescription_id' => $prescription_id,
                    'medication_id' => $medication_id,
                    'dosage' => $dosages[$key],
                    'daily_usage' => $daily_usages[$key],
                    'usage_period' => $usage_periods[$key],
                    'notes' => isset($medication_notes[$key]) ? $medication_notes[$key] : null
                );
                
                if (!$db->insert('prescription_items', $item_data)) {
                    throw new Exception("Reçete ilaçları kaydedilemedi.");
                }
            }
            
            $db->commit();
            $_SESSION['success'] = "Yeni reçete başarıyla kaydedildi.";
            header('Location: prescription_details.php?id=' . $prescription_id . '&refresh=1');
            exit();
            
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['failure'] = "Reçete kaydedilirken bir hata oluştu. Lütfen tekrar deneyin.";
        }
    }
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Yeni Reçete Oluştur</h1>
        </div>
    </div>
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <form method="post" id="prescription_form" class="form" action="">
        <div class="row">
            <!-- Hasta ve Tanı Bilgileri -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Hasta ve Tanı Bilgileri</h3>
                    </div>
                    <div class="panel-body">
                        <!-- Hasta Seçimi -->
                        <div class="form-group">
                            <label for="patient_id">Hasta *</label>
                            
                            <?php if ($patient_id && isset($selected_patient)): ?>
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <p class="form-control-static">
                                    <?php echo htmlspecialchars($selected_patient['name']); ?> 
                                    (<?php echo htmlspecialchars($selected_patient['owner_name']); ?>)
                                    <a href="add_prescription.php" class="btn btn-sm btn-default">Değiştir</a>
                                </p>
                            <?php else: ?>
                                <select name="patient_id" id="patient_id" class="form-control" required>
                                    <option value="">-- Hasta Seçiniz --</option>
                                    <?php if (isset($patients) && is_array($patients)): ?>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo htmlspecialchars($patient['name']); ?> 
                                                (<?php echo htmlspecialchars($patient['owner_name']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Tanı -->
                        <div class="form-group">
                            <label for="diagnosis">Tanı *</label>
                            <input type="text" name="diagnosis" id="diagnosis" class="form-control" required maxlength="255">
                        </div>
                        
                        <!-- Tanı Detayları -->
                        <div class="form-group">
                            <label for="diagnosis_details">Tanı Detayları</label>
                            <textarea name="diagnosis_details" id="diagnosis_details" class="form-control" rows="4"></textarea>
                        </div>
                        
                        <!-- Notlar -->
                        <div class="form-group">
                            <label for="notes">Notlar</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- İlaç Bilgileri -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Reçete Bilgileri</h3>
                    </div>
                    <div class="panel-body">
                        <div class="alert alert-info">
                            <p>Tarih: <?php echo date('d.m.Y'); ?></p>
                            <p>Oluşturan: <?php echo htmlspecialchars($user_name); ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label>İlaçlar *</label>
                            <div id="medications_container">
                                <div class="medication-item well">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>İlaç Adı *</label>
                                                <select name="medication_id[]" class="form-control medication-select" required>
                                                    <option value="">-- İlaç Seçiniz --</option>
                                                    <?php if (isset($medications) && is_array($medications)): ?>
                                                        <?php foreach ($medications as $medication): ?>
                                                            <option value="<?php echo $medication['id']; ?>" 
                                                                    data-unit="<?php echo htmlspecialchars($medication['unit']); ?>"
                                                                    data-stock="<?php echo $medication['quantity'] ?? 0; ?>">
                                                                <?php echo htmlspecialchars($medication['name']); ?> 
                                                                (Stok: <?php echo ($medication['quantity'] ?? 0) . ' ' . $medication['unit']; ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="form-group">
                                                <label>Doz *</label>
                                                <div class="input-group">
                                                    <input type="number" name="dosage[]" class="form-control" required 
                                                           step="0.01" min="0.01">
                                                    <span class="input-group-addon medication-unit">birim</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <div class="form-group">
                                                <label>Günlük Kullanım *</label>
                                                <input type="text" name="daily_usage[]" class="form-control" 
                                                       placeholder="Örn: Günde 3x1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Kullanım Süresi *</label>
                                                <input type="text" name="usage_period[]" class="form-control" 
                                                       placeholder="Örn: 7 gün" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Notlar</label>
                                                <input type="text" name="medication_notes[]" class="form-control" 
                                                       placeholder="Kullanım notu">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                        <button type="button" class="btn btn-danger btn-sm remove-medication" style="display:none;">
    <i class="fa fa-trash"></i> İlacı Kaldır
</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" id="add_medication" class="btn btn-success">
                                <i class="fa fa-plus"></i> Yeni İlaç Ekle
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="form-group text-center">
                    <a href="prescriptions.php" class="btn btn-default">İptal</a>
                    <button type="submit" class="btn btn-primary">Reçeteyi Kaydet</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Select2 için CSS ve JS bağlantıları -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Sayfa yüklendiğinde select2'yi başlat
    try {
        $('.medication-select').select2({
            placeholder: "-- İlaç Seçiniz --",
            width: '100%'
        });
    } catch (e) {
        console.error("Select2 initialization failed:", e);
    }
    
    // Handle adding new medications
    $('#add_medication').click(function() {
        // İlk ilaç öğesini kopyala
        var firstItem = $('.medication-item').first();
        var newItem = firstItem.clone();
        
        // Form elemanlarını temizle
        newItem.find('input').val('');
        newItem.find('select').val('');
        newItem.find('.medication-unit').text('birim');
        newItem.find('.stock-info').remove();
        newItem.find('.stock-warning').remove();
        
        // Kaldır butonunu göster
        newItem.find('.remove-medication').show();
        
        // Yeni öğeyi container'a ekle
        $('#medications_container').append(newItem);
        
        // Select2'yi destroy edip yeniden initialize et
        try {
            newItem.find('.medication-select').select2({
                placeholder: "-- İlaç Seçiniz --",
                width: '100%'
            });
        } catch (e) {
            console.error("Select2 initialization failed for new item:", e);
        }
    });

    // Handle removing medications
    $(document).on('click', '.remove-medication', function() {
        if ($('.medication-item').length > 1) {
            $(this).closest('.medication-item').remove();
        } else {
            alert('En az bir ilaç seçimi olmalıdır.');
        }
    });

    // Handle changing medication unit and show stock
    $(document).on('change', '.medication-select', function() {
        var selectedOption = $(this).find(':selected');
        var unit = selectedOption.data('unit') || 'birim';
        var stock = selectedOption.data('stock');
        var medicationItem = $(this).closest('.medication-item');
        
        // Birimi güncelle
        medicationItem.find('.medication-unit').text(unit);
        
        // Stok bilgisi göster
        medicationItem.find('.stock-info').remove();
        if (selectedOption.val()) {
            var stockInfoHtml = '<div class="stock-info alert alert-info">Mevcut Stok: <strong>' + 
                              stock + ' ' + unit + '</strong></div>';
            medicationItem.find('.form-group:first').append(stockInfoHtml);
            
            // Doz input'unu güncelle
            var dosageInput = medicationItem.find('input[name="dosage[]"]');
            dosageInput.attr('max', stock);
            dosageInput.attr('data-stock', stock);
            
            if (parseFloat(dosageInput.val()) > stock) {
                dosageInput.val('');
            }
        }
    });

    // Doz girişinde stok kontrolü
    $(document).on('input', 'input[name="dosage[]"]', function() {
        var dosage = parseFloat($(this).val());
        var medicationItem = $(this).closest('.medication-item');
        var selectedOption = medicationItem.find('.medication-select option:selected');
        var stock = parseFloat(selectedOption.data('stock'));
        var unit = selectedOption.data('unit') || 'birim';
        
        var warningElement = medicationItem.find('.stock-warning');
        if (warningElement.length === 0) {
            warningElement = $('<div class="stock-warning text-danger"></div>');
            $(this).parent().after(warningElement);
        }
        
        if (!isNaN(dosage) && !isNaN(stock)) {
            if (dosage > stock) {
                warningElement.html('<i class="fa fa-warning"></i> Yetersiz stok! ' +
                                  'Mevcut: ' + stock + ' ' + unit);
                warningElement.show();
                $(this).addClass('is-invalid');
                $('#prescription_form').data('invalid-stock', true);
            } else {
                warningElement.hide();
                $(this).removeClass('is-invalid');
                $('#prescription_form').data('invalid-stock', false);
            }
        } else {
            warningElement.hide();
            $(this).removeClass('is-invalid');
            $('#prescription_form').data('invalid-stock', false);
        }
    });

    // Form validation
    $('#prescription_form').submit(function(e) {
        var hasError = false;
        var errorMessage = '';
        
        // Stok kontrolü
        if ($(this).data('invalid-stock')) {
            hasError = true;
            errorMessage += '- Stok yetersizliği olan ilaçlar var.\n';
        }
        
        // İlaç seçimi kontrolü
        $('.medication-select').each(function() {
            if (!$(this).val()) {
                hasError = true;
                errorMessage += '- Tüm ilaçlar seçilmelidir.\n';
                return false;
            }
        });
        
        // Doz kontrolü
        $('input[name="dosage[]"]').each(function() {
            if (!$(this).val() || parseFloat($(this).val()) <= 0) {
                hasError = true;
                errorMessage += '- Tüm ilaçlar için geçerli doz girilmelidir.\n';
                return false;
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Lütfen aşağıdaki hataları düzeltiniz:\n' + errorMessage);
            return false;
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
                                                