<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get current datetime and user
$current_date = '2025-03-01 14:42:33';
$current_user = 'ibosta';

// Get patient ID from URL parameter
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if ID is valid
if (!$patient_id) {
    $_SESSION['failure'] = "Geçersiz hasta ID'si.";
    header('Location: patients.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Get patient data
$db->where('id', $patient_id);
$patient = $db->getOne('patients');

// Check if patient exists
if (!$patient) {
    $_SESSION['failure'] = "Hasta bulunamadı.";
    header('Location: patients.php');
    exit();
}

// Get patient's prescriptions with doctor details
$db = getDbInstance();
$db->join("users u", "prescriptions.user_id = u.id", "LEFT");
$db->where('patient_id', $patient_id);
$db->orderBy('prescriptions.created_at', 'DESC');
$prescriptions = $db->get('prescriptions', null, [
    'prescriptions.*',
    'u.name as doctor_name'
]);


// prescription_items tablosundaki doğru sütunlar:
// id, prescription_id, medication_id, dosage, daily_usage, usage_period, notes

if (!empty($prescriptions)) {
    foreach ($prescriptions as &$prescription) {
        // Get medications for this prescription
        $db_items = getDbInstance();
        $db_items->join('medications m', 'pi.medication_id = m.id', 'LEFT');
        $db_items->where('pi.prescription_id', $prescription['id']);
        $items = $db_items->get('prescription_items pi', null, [
            'pi.dosage',           // Doz bilgisi
            'pi.daily_usage',      // Günlük kullanım
            'pi.usage_period',     // Kullanım süresi
            'pi.notes',            // Ek notlar
            'm.name as medication_name',
            'm.unit'
        ]);
        
        $prescription['items'] = $items;
        $prescription['medication_count'] = count($items);
    }
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-6">
            <h1 class="page-header"><?php echo htmlspecialchars($patient['name']); ?> - Hasta Detayları</h1>
        </div>
        <div class="col-lg-6">
            <div class="page-action-links text-right">
                <a href="patients.php" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Hasta Listesine Dön
                </a>
                <a href="#" class="btn btn-warning edit-patient" data-toggle="modal" data-target="#editPatientModal">
                    <i class="fa fa-pencil"></i> Düzenle
                </a>
                <a href="add_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                    <i class="fa fa-file-text"></i> Reçete Yaz
                </a>
            </div>
        </div>
    </div>
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <!-- Patient Details Panel -->
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Hasta Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">Hasta Adı</th>
                            <td><?php echo htmlspecialchars($patient['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Tür</th>
                            <td><?php echo htmlspecialchars($patient['type']); ?></td>
                        </tr>
                        <tr>
                            <th>Irk</th>
                            <td><?php echo htmlspecialchars($patient['breed'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Yaş (Ay)</th>
                            <td><?php echo htmlspecialchars($patient['age'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Cinsiyet</th>
                            <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Sahibinin Adı</th>
                            <td><?php echo htmlspecialchars($patient['owner_name'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Sahibinin Telefonu</th>
                            <td><?php echo htmlspecialchars($patient['owner_phone'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Sahibinin E-postası</th>
                            <td><?php echo htmlspecialchars($patient['owner_email'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Sahibinin Adresi</th>
                            <td><?php echo nl2br(htmlspecialchars($patient['owner_address'] ?? '-')); ?></td>
                        </tr>
                        <tr>
                            <th>Notlar</th>
                            <td><?php echo nl2br(htmlspecialchars($patient['notes'] ?? '-')); ?></td>
                        </tr>
                        <tr>
                            <th>Kayıt Tarihi</th>
                            <td><?php echo date('d.m.Y H:i', strtotime($patient['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Reçeteler</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($prescriptions)): ?>
                        <p class="text-center">Henüz reçete yazılmamış.</p>
                    <?php else: ?>
                        <div class="panel-group" id="accordion">
                            <?php foreach ($prescriptions as $index => $prescription): ?>
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo $index; ?>">
                                            <?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?> 
                                            - <?php echo count($prescription['items']); ?> İlaç
                                            <?php if ($prescription['doctor_name']): ?>
                                                <small class="pull-right">Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></small>
                                            <?php endif; ?>
                                        </a>
                                    </h4>
                                </div>
                                <div id="collapse<?php echo $index; ?>" class="panel-collapse collapse <?php echo $index === 0 ? 'in' : ''; ?>">
                                    <div class="panel-body">
                                        <h5><strong>Tanı:</strong></h5>
                                        <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                        
                                        <?php if (!empty($prescription['diagnosis_details'])): ?>
                                        <h5><strong>Tanı Detayları:</strong></h5>
                                        <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis_details'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($prescription['notes'])): ?>
                                        <h5><strong>Notlar:</strong></h5>
                                        <p><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <h5><strong>İlaçlar:</strong></h5>
                                        <?php if (!empty($prescription['items'])): ?>
                                            <table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>İlaç</th>
            <th>Doz</th>
            <th>Günlük Kullanım</th>
            <th>Kullanım Süresi</th>
            <th>Notlar</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($prescription['items'] as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item['medication_name']); ?></td>
            <td>
                <?php 
                echo htmlspecialchars($item['dosage'] ?? '0'); 
                echo ' ';
                echo htmlspecialchars($item['unit'] ?? '');
                ?>
            </td>
            <td><?php echo htmlspecialchars($item['daily_usage'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($item['usage_period'] ?? '') . ' gün'; ?></td>
            <td><?php echo nl2br(htmlspecialchars($item['notes'] ?? '')); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
                                        <?php else: ?>
                                        <p class="text-muted">İlaç bilgisi bulunamadı.</p>
                                        <?php endif; ?>
                                        
                                        <div class="text-right">
                                            <a href="prescription_pdf.php?id=<?php echo $prescription['id']; ?>" 
                                               class="btn btn-default btn-sm" target="_blank">
                                                <i class="fa fa-print"></i> Yazdır
                                            </a>
                                            <a href="prescription_details.php?id=<?php echo $prescription['id']; ?>" 
                                               class="btn btn-info btn-sm">
                                                <i class="fa fa-eye"></i> Detay
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div class="modal fade" id="editPatientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Hasta Bilgilerini Düzenle</h4>
            </div>
            <div class="modal-body">
                <form id="edit_patient_form" method="post" action="edit_patient.php">
                    <input type="hidden" name="id" value="<?php echo $patient['id']; ?>">
                    
                    <div class="form-group">
                        <label for="name">Hasta Adı *</label>
                        <input type="text" name="name" id="name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="type">Tür *</label>
                        <select name="type" id="type" class="form-control" required>
                            <option value="Kedi" <?php echo ($patient['type'] == 'Kedi') ? 'selected' : ''; ?>>Kedi</option>
                            <option value="Köpek" <?php echo ($patient['type'] == 'Köpek') ? 'selected' : ''; ?>>Köpek</option>
                            <option value="Kuş" <?php echo ($patient['type'] == 'Kuş') ? 'selected' : ''; ?>>Kuş</option>
                            <option value="Diğer" <?php echo ($patient['type'] == 'Diğer') ? 'selected' : ''; ?>>Diğer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="breed">Irk</label>
                        <input type="text" name="breed" id="breed" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['breed'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="age">Yaş (Ay)</label>
                        <input type="number" name="age" id="age" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['age'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="gender">Cinsiyet</label>
                        <select name="gender" id="gender" class="form-control">
                            <option value="erkek" <?php echo ($patient['gender'] == 'erkek') ? 'selected' : ''; ?>>Erkek</option>
                            <option value="dişi" <?php echo ($patient['gender'] == 'dişi') ? 'selected' : ''; ?>>Dişi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="owner_name">Sahibinin Adı</label>
                        <input type="text" name="owner_name" id="owner_name" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['owner_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="owner_phone">Sahibinin Telefonu</label>
                        <input type="tel" name="owner_phone" id="owner_phone" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['owner_phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="owner_email">Sahibinin E-postası</label>
                        <input type="email" name="owner_email" id="owner_email" class="form-control" 
                               value="<?php echo htmlspecialchars($patient['owner_email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="owner_address">Sahibinin Adresi</label>
                        <textarea name="owner_address" id="owner_address" class="form-control" rows="3"><?php echo htmlspecialchars($patient['owner_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notlar</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"><?php echo htmlspecialchars($patient['notes'] ?? ''); ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                <button type="submit" form="edit_patient_form" class="btn btn-primary">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#edit_patient_form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.status == 'success') {
                    location.reload();
                } else {
                    alert('Hata oluştu: ' + response.message);
                }
            },
            error: function() {
                alert('Bir hata oluştu!');
            }
        });
    });

    // Bootstrap Collapse olaylarını yönet
    $('.panel-collapse').on('show.bs.collapse', function () {
        $(this).siblings('.panel-heading').addClass('active');
    });

    $('.panel-collapse').on('hide.bs.collapse', function () {
        $(this).siblings('.panel-heading').removeClass('active');
    });
});
</script>

<style>
.panel-heading.active {
    background-color: #f5f5f5;
}
.panel-title a {
    display: block;
    text-decoration: none;
}
.table > tbody > tr > td {
    vertical-align: middle;
}
.panel-body h5 {
    margin-top: 15px;
    margin-bottom: 10px;
    font-weight: bold;
}
.table-striped > tbody > tr:nth-of-type(odd) {
    background-color: #f9f9f9;
}
.btn-sm {
    margin-left: 5px;
}
.page-action-links {
    margin-top: 30px;
}
</style>

<?php 
// Sistem tarihini son olarak güncelle
$current_date = '2025-03-01 14:44:36';

// Footer'ı dahil et
include_once 'includes/footer.php'; 
?>