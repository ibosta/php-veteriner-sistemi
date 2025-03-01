<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Get prescription ID from URL
$prescription_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if ID is valid
if (!$prescription_id) {
    $_SESSION['failure'] = "Geçersiz reçete ID'si.";
    header('Location: prescriptions.php');
    exit();
}

// Get prescription data
$db->where('id', $prescription_id);
$prescription = $db->getOne('prescriptions');

// Check if prescription exists
if (!$prescription) {
    $_SESSION['failure'] = "Reçete bulunamadı.";
    header('Location: prescriptions.php');
    exit();
}

// Get patient data
$db->where('id', $prescription['patient_id']);
$patient = $db->getOne('patients');

// Get prescription items
$db->where('prescription_id', $prescription_id);
$items = $db->get('prescription_items');

// Get all medications for dropdown
$medications = $db->get('medications');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $diagnosis = filter_input(INPUT_POST, 'diagnosis', FILTER_SANITIZE_STRING);
    $diagnosis_details = filter_input(INPUT_POST, 'diagnosis_details', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    // Validate required fields
    $errors = array();
    
    if (empty($diagnosis)) {
        $errors[] = "Tanı bilgisi gereklidir.";
    }
    
    // Get medication data from form
    $medication_ids = isset($_POST['medication_id']) ? $_POST['medication_id'] : array();
    $dosages = isset($_POST['dosage']) ? $_POST['dosage'] : array();
    $daily_usages = isset($_POST['daily_usage']) ? $_POST['daily_usage'] : array();
    $usage_periods = isset($_POST['usage_period']) ? $_POST['usage_period'] : array();
    $medication_notes = isset($_POST['medication_notes']) ? $_POST['medication_notes'] : array();
    $item_ids = isset($_POST['item_id']) ? $_POST['item_id'] : array();
    
    // At least one medication is required
    if (empty($medication_ids) || count($medication_ids) < 1) {
        $errors[] = "En az bir ilaç eklenmelidir.";
    }
    
    // Check medication fields
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
    
    // If no errors, update prescription
    if (empty($errors)) {
        try {
            // Start transaction
            $db->startTransaction();
            
            // Update prescription
            $prescription_data = array(
                'diagnosis' => $diagnosis,
                'diagnosis_details' => $diagnosis_details ?: null,
                'notes' => $notes ?: null,
                'updated_at' => date('Y-m-d H:i:s')
            );
            
            $db->where('id', $prescription_id);
            $status = $db->update('prescriptions', $prescription_data);
            
            if (!$status) {
                throw new Exception("Reçete güncellenemedi.");
            }
            
            // Delete old items not in the current form
            $keep_items = array();
            foreach ($item_ids as $item_id) {
                if (!empty($item_id)) {
                    $keep_items[] = $item_id;
                }
            }
            
            if (!empty($keep_items)) {
                $db->where('prescription_id', $prescription_id);
                $db->where('id', $keep_items, 'NOT IN');
                $db->delete('prescription_items');
            } else {
                // If all items are new, delete all old items
                $db->where('prescription_id', $prescription_id);
                $db->delete('prescription_items');
            }
            
            // Update or insert prescription items
            foreach ($medication_ids as $key => $medication_id) {
                if (!empty($medication_id)) {
                    $item_data = array(
                        'prescription_id' => $prescription_id,
                        'medication_id' => $medication_id,
                        'dosage' => $dosages[$key],
                        'daily_usage' => $daily_usages[$key],
                        'usage_period' => $usage_periods[$key],
                        'notes' => isset($medication_notes[$key]) ? $medication_notes[$key] : null
                    );
                    
                    if (!empty($item_ids[$key])) {
                        // Update existing item
                        $db->where('id', $item_ids[$key]);
                        $db->update('prescription_items', $item_data);
                    } else {
                        // Insert new item
                        $db->insert('prescription_items', $item_data);
                    }
                }
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success'] = "Reçete başarıyla güncellendi.";
            header('Location: prescription_details.php?id=' . $prescription_id);
            exit();
            
        } catch (Exception $e) {
            // Roll back transaction on error
            $db->rollback();
            $_SESSION['failure'] = "Hata: " . $e->getMessage();
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
            <h1 class="page-header">Reçete Düzenle #<?php echo $prescription_id; ?></h1>
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
                        <!-- Patient Information (Read-only) -->
                        <div class="form-group">
                            <label>Hasta</label>
                            <p class="form-control-static">
                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['name']); ?>
                                </a>
                                (<?php echo htmlspecialchars($patient['owner_name']); ?>)
                            </p>
                        </div>
                        
                        <!-- Diagnosis -->
                        <div class="form-group">
                            <label for="diagnosis">Tanı *</label>
                            <input type="text" name="diagnosis" id="diagnosis" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars($prescription['diagnosis']); ?>">
                        </div>
                        
                        <!-- Diagnosis Details -->
                        <div class="form-group">
                            <label for="diagnosis_details">Tanı Detayları</label>
                            <textarea name="diagnosis_details" id="diagnosis_details" class="form-control" rows="4"><?php echo htmlspecialchars($prescription['diagnosis_details'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group">
                            <label for="notes">Notlar</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($prescription['notes'] ?? ''); ?></textarea>
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
                            <p>Oluşturulma: <?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?></p>
                            <p>Son Güncelleme: <?php echo ($prescription['updated_at']) ? date('d.m.Y H:i', strtotime($prescription['updated_at'])) : 'Güncelleme yapılmamış'; ?></p>
                        </div>
                        
                        <div class="form-group">
                            <label>İlaçlar *</label>
                            <div id="medications_container">
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item): ?>
                                        <div class="medication-item well">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label>İlaç Adı *</label>
                                                        <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                                                        <select name="medication_id[]" class="form-control medication-select" required>
                                                            <option value="">-- İlaç Seçiniz --</option>
                                                            <?php foreach ($medications as $medication): ?>
                                                                <option value="<?php echo $medication['id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>" <?php if ($medication['id'] == $item['medication_id']) echo 'selected'; ?>>
                                                                    <?php echo htmlspecialchars($medication['name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group">
                                                        <label>Doz *</label>
                                                        <div class="input-group">
                                                            <input type="text" name="dosage[]" class="form-control" required value="<?php echo htmlspecialchars($item['dosage']); ?>">
                                                            <?php 
                                                            $db->where('id', $item['medication_id']);
                                                            $med = $db->getOne('medications');
                                                            $unit = $med ? $med['unit'] : 'birim';
                                                            ?>
                                                            <span class="input-group-addon medication-unit"><?php echo htmlspecialchars($unit); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-7">
                                                    <div class="form-group">
                                                        <label>Günlük Kullanım *</label>
                                                        <input type="text" name="daily_usage[]" class="form-control" placeholder="Örn: Günde 3x1" required value="<?php echo htmlspecialchars($item['daily_usage']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Kullanım Süresi *</label>
                                                        <input type="text" name="usage_period[]" class="form-control" placeholder="Örn: 7 gün" required value="<?php echo htmlspecialchars($item['usage_period']); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Notlar</label>
                                                        <input type="text" name="medication_notes[]" class="form-control" placeholder="Kullanım notu" value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="button" class="btn btn-danger btn-sm remove-medication">
                                                        <i class="fa fa-trash"></i> İlacı Kaldır
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="medication-item well">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label>İlaç Adı *</label>
                                                    <input type="hidden" name="item_id[]" value="">
                                                    <select name="medication_id[]" class="form-control medication-select" required>
                                                        <option value="">-- İlaç Seçiniz --</option>
                                                        <?php foreach ($medications as $medication): ?>
                                                            <option value="<?php echo $medication['id']; ?>" data-unit="<?php echo htmlspecialchars($medication['unit']); ?>">
                                                                <?php echo htmlspecialchars($medication['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-5">
                                                <div class="form-group">
                                                    <label>Doz *</label>
                                                    <div class="input-group">
                                                        <input type="text" name="dosage[]" class="form-control" required>
                                                        <span class="input-group-addon medication-unit">birim</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="form-group">
                                                    <label>Günlük Kullanım *</label>
                                                    <input type="text" name="daily_usage[]" class="form-control" placeholder="Örn: Günde 3x1" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Kullanım Süresi *</label>
                                                    <input type="text" name="usage_period[]" class="form-control" placeholder="Örn: 7 gün" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Notlar</label>
                                                    <input type="text" name="medication_notes[]" class="form-control" placeholder="Kullanım notu">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <button type="button" class="btn btn-danger btn-sm remove-medication" style="display:none;">
                                                    <i class="fa fa-trash"></i> İlacı Kaldır
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
                    <a href="prescription_details.php?id=<?php echo $prescription_id; ?>" class="btn btn-default">İptal</a>
                    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                </div>
                <div class="form-group text-muted text-center">
                    <small>Son düzenleme: <?php echo date('d.m.Y H:i'); ?> - Düzenleyen: <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Handle adding new medications
    $('#add_medication').click(function() {
        var newItem = $('.medication-item').first().clone();
        newItem.find('input').val('');
        newItem.find('select').val('');
        newItem.find('.medication-unit').text('birim');
        newItem.find('.remove-medication').show();
        $('#medications_container').append(newItem);
    });
    
    // Handle removing medications
    $(document).on('click', '.remove-medication', function() {
        $(this).closest('.medication-item').remove();
    });
    
    // Handle changing medication unit
    $(document).on('change', '.medication-select', function() {
        var unit = $(this).find(':selected').data('unit');
        $(this).closest('.medication-item').find('.medication-unit').text(unit || 'birim');
    });
    
    // Confirm before form submission
    $('#prescription_form').submit(function() {
        if(confirm('Reçete değişikliklerini kaydetmek istediğinizden emin misiniz?')) {
            return true;
        }
        return false;
    });
    
    // Show current date and time
    const currentDate = "<?php echo date('d.m.Y H:i:s', strtotime('2025-02-28 23:39:18')); ?>";
    const currentUser = "<?php echo htmlspecialchars('ibosta'); ?>";
    console.log("Düzenleme zamanı: " + currentDate + " - Kullanıcı: " + currentUser);
});
</script>

<?php include_once 'includes/footer.php'; ?>