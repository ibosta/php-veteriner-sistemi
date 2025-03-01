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

// Current date and time
$current_date = date('Y-m-d H:i:s');

// Get DB instance
$db = getDbInstance();

// Dashboard information
$numPatients = $db->getValue("patients", "count(*)");
$numPrescriptions = $db->getValue("prescriptions", "count(*)");
$numMedications = $db->getValue("medications", "count(*)");

// Kritik seviye stok sayısı
$db->where("quantity < reorder_level AND reorder_level > 0");
$lowStock = $db->getValue("stock", "count(*)");

include_once('includes/header.php');
?>
<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Veteriner Reçete Sistemi</h1>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-paw fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $numPatients; ?></div>
                            <div>Kayıtlı Hasta</div>
                        </div>
                    </div>
                </div>
                <a href="patients.php">
                    <div class="panel-footer">
                        <span class="pull-left">Detayları Görüntüle</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-green">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-file-text-o fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $numPrescriptions; ?></div>
                            <div>Toplam Reçete</div>
                        </div>
                    </div>
                </div>
                <a href="prescriptions.php">
                    <div class="panel-footer">
                        <span class="pull-left">Detayları Görüntüle</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-yellow">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-medkit fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $numMedications; ?></div>
                            <div>İlaç Çeşidi</div>
                        </div>
                    </div>
                </div>
                <a href="medications.php">
                    <div class="panel-footer">
                        <span class="pull-left">Detayları Görüntüle</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-red">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-exclamation-triangle fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $lowStock; ?></div>
                            <div>Azalan Stok</div>
                        </div>
                    </div>
                </div>
                <a href="stock_list.php?filter_stock=low">
                    <div class="panel-footer">
                        <span class="pull-left">Detayları Görüntüle</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-clock-o fa-fw"></i> Son Reçeteler
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Hasta</th>
                                    <th>Tanı</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $db->join("patients p", "pr.patient_id = p.id", "LEFT");
                                $db->orderBy("pr.created_at", "DESC");
                                $recent_prescriptions = $db->get("prescriptions pr", 5, "pr.*, p.name as patient_name");
                                
                                foreach ($recent_prescriptions as $prescription) {
                                ?>
                                <tr>
                                    <td><?php echo $prescription['id']; ?></td>
                                    <td><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['diagnosis']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?></td>
                                    <td>
                                        <a href="prescription_details.php?id=<?php echo $prescription['id']; ?>" class="btn btn-primary btn-xs">Görüntüle</a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right">
                        <a href="prescriptions.php">Tüm Reçeteleri Görüntüle <i class="fa fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-paw fa-fw"></i> Son Hasta Kayıtları
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Hasta Adı</th>
                                    <th>Türü</th>
                                    <th>Sahip</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $db = getDbInstance();
                                $db->orderBy("created_at", "DESC");
                                $recent_patients = $db->get("patients", 5);
                                
                                foreach ($recent_patients as $patient) {
                                ?>
                                <tr>
                                    <td><?php echo $patient['id']; ?></td>
                                    <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['type']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['owner_name']); ?></td>
                                    <td>
                                        <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-xs">Görüntüle</a>
                                        <a href="add_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-xs">Reçete Yaz</a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right">
                        <a href="patients.php">Tüm Hastaları Görüntüle <i class="fa fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="well">
                <p class="text-muted text-center">Sistem Güncel Zamanı: <?php echo date('d.m.Y H:i', strtotime($current_date)); ?> | Kullanıcı: <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>