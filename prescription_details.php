<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get prescription ID from URL parameter
$prescription_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if ID is valid
if (!$prescription_id) {
    $_SESSION['failure'] = "Geçersiz reçete ID'si.";
    header('Location: prescriptions.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Get prescription data
$db->join("patients p", "pr.patient_id = p.id", "LEFT");
$db->join("users u", "pr.user_id = u.id", "LEFT");
$db->where('pr.id', $prescription_id);
$prescription = $db->getOne('prescriptions pr', 'pr.*, p.name as patient_name, p.type as patient_type, p.breed as patient_breed, p.age as patient_age, p.owner_name, p.owner_phone, u.name as doctor_name');

// Check if prescription exists
if (!$prescription) {
    $_SESSION['failure'] = "Reçete bulunamadı.";
    header('Location: prescriptions.php');
    exit();
}

// Get prescription items
$db->join("medications m", "pi.medication_id = m.id", "LEFT");
$db->where('pi.prescription_id', $prescription_id);
$items = $db->get('prescription_items pi', null, 'pi.*, m.name as medication_name, m.unit');

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-8">
            <h1 class="page-header">Reçete Detayları - #<?php echo $prescription_id; ?></h1>
        </div>
        <div class="col-lg-4">
            <div class="page-action-links text-right">
                <a href="prescriptions.php" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Reçetelere Dön
                </a>
                <a href="prescription_pdf.php?id=<?php echo $prescription_id; ?>" class="btn btn-info" target="_blank">
                    <i class="fa fa-file-pdf-o"></i> PDF İndir
                </a>
                <a href="edit_prescription.php?id=<?php echo $prescription_id; ?>" class="btn btn-warning">
                    <i class="fa fa-pencil"></i> Düzenle
                </a>
            </div>
        </div>
    </div>
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <div class="row">
        <!-- Reçete Bilgileri -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Reçete Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Reçete ID</th>
                            <td><?php echo $prescription_id; ?></td>
                        </tr>
                        <tr>
                            <th>Tarih</th>
                            <td><?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Veteriner</th>
                            <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Tanı</th>
                            <td><?php echo htmlspecialchars($prescription['diagnosis']); ?></td>
                        </tr>
                        <?php if (!empty($prescription['diagnosis_details'])): ?>
                        <tr>
                            <th>Tanı Detayları</th>
                            <td><?php echo nl2br(htmlspecialchars($prescription['diagnosis_details'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($prescription['notes'])): ?>
                        <tr>
                            <th>Notlar</th>
                            <td><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Hasta Bilgileri -->
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Hasta Bilgileri</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="30%">Hasta</th>
                            <td>
                                <a href="patient_details.php?id=<?php echo $prescription['patient_id']; ?>">
                                    <?php echo htmlspecialchars($prescription['patient_name']); ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th>Tür / Irk</th>
                            <td>
                                <?php echo htmlspecialchars($prescription['patient_type']); ?>
                                <?php if (!empty($prescription['patient_breed'])): ?>
                                    (<?php echo htmlspecialchars($prescription['patient_breed']); ?>)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Yaş</th>
                            <td>
                                <?php 
                                if (!empty($prescription['patient_age'])) {
                                    $years = floor($prescription['patient_age'] / 12);
                                    $months = $prescription['patient_age'] % 12;
                                    
                                    if ($years > 0) {
                                        echo $years . " yıl ";
                                    }
                                    if ($months > 0 || $years == 0) {
                                        echo $months . " ay";
                                    }
                                } else {
                                    echo "Belirtilmemiş";
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Sahip</th>
                            <td><?php echo htmlspecialchars($prescription['owner_name']); ?></td>
                        </tr>
                        <tr>
                            <th>İletişim</th>
                            <td><?php echo htmlspecialchars($prescription['owner_phone']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- İlaç Listesi -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Reçetelenen İlaçlar</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">İlaç</th>
                                <th width="15%">Doz</th>
                                <th width="20%">Günlük Kullanım</th>
                                <th width="15%">Kullanım Süresi</th>
                                <th width="25%">Notlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items)): ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($item['medication_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['dosage']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($item['daily_usage']); ?></td>
                                        <td><?php echo htmlspecialchars($item['usage_period']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($item['notes'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Bu reçetede ilaç bulunmamaktadır.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reçete Altbilgi -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="text-center">
                        <p>
                            <strong>Reçete Tarihi:</strong> <?php echo date('d.m.Y', strtotime($prescription['created_at'])); ?><br>
                            <strong>Düzenleyen Veteriner:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                        </p>
                        
                        <div class="well well-sm">
                            <p><em>Bu reçete <?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?> tarihinde oluşturulmuştur.</em></p>
                            <p><em>Son güncelleme: <?php echo ($prescription['updated_at']) ? date('d.m.Y H:i', strtotime($prescription['updated_at'])) : 'Güncelleme yapılmamış'; ?></em></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>