<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Delete patient
if (isset($_GET['del_id']) && $_GET['del_id'] !== '') {
    $del_id = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($del_id) {
        $db->where('id', $del_id);
        $status = $db->delete('patients');
        if ($status) {
            $_SESSION['success'] = "Hasta başarıyla silindi!";
        } else {
            $_SESSION['failure'] = "Hasta silinemedi. Lütfen tekrar deneyin!";
        }
        header('Location: patients.php');
        exit;
    }
}

// Search filters
$search_str = filter_input(INPUT_GET, 'search_str');
$filter_col = filter_input(INPUT_GET, 'filter_col');
$order_by = filter_input(INPUT_GET, 'order_by');
$order_type = filter_input(INPUT_GET, 'order_type');

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page ? $page : 1;
$records_per_page = 10;
$starting_limit = ($page - 1) * $records_per_page;

// Apply search filters
if ($search_str) {
    $db->where('name', '%' . $search_str . '%', 'LIKE');
    $db->orWhere('owner_name', '%' . $search_str . '%', 'LIKE');
    $db->orWhere('type', '%' . $search_str . '%', 'LIKE');
}

// Set order 
if ($order_by && $order_type) {
    $db->orderBy($order_by, $order_type);
} else {
    $db->orderBy('id', 'DESC');
}

// Get total records count
$total_records = $db->getValue("patients", "count(*)");

// Get patient records
$patients = $db->withTotalCount()->arraybuilder()->get('patients', array($starting_limit, $records_per_page));
$total_count = $db->totalCount;

// Set pagination vars
$total_pages = ceil($total_records / $records_per_page);
$pagination_url = 'patients.php';

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-6">
            <h1 class="page-header">Hasta Yönetimi</h1>
        </div>
        <div class="col-lg-6">
            <div class="page-action-links text-right">
                <button class="btn btn-success" data-toggle="modal" data-target="#addPatientModal">
                    <i class="fa fa-plus"></i> Yeni Hasta Ekle
                </button>
            </div>
        </div>
    </div>
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <!-- Filters -->
    <div class="well text-center filter-form">
        <form class="form form-inline" action="">
            <label for="search_str">Ara</label>
            <input type="text" class="form-control" id="search_str" name="search_str" placeholder="Hasta adı, sahip adı veya tür" value="<?php echo $search_str; ?>">
            <label for="filter_col">Sıralama</label>
            <select class="form-control" id="filter_col" name="filter_col">
                <option value="name" <?php if ($filter_col == "name") echo "selected"; ?>>Hasta Adı</option>
                <option value="owner_name" <?php if ($filter_col == "owner_name") echo "selected"; ?>>Sahip Adı</option>
                <option value="type" <?php if ($filter_col == "type") echo "selected"; ?>>Tür</option>
                <option value="created_at" <?php if ($filter_col == "created_at") echo "selected"; ?>>Kayıt Tarihi</option>
            </select>
            <select class="form-control" id="order_by" name="order_type">
                <option value="ASC" <?php if ($order_type == "ASC") echo "selected"; ?>>Artan</option>
                <option value="DESC" <?php if ($order_type == "DESC") echo "selected"; ?>>Azalan</option>
            </select>
            <input type="submit" value="Uygula" class="btn btn-primary">
            <a href="patients.php" class="btn btn-warning">Sıfırla</a>
        </form>
    </div>
    
    <!-- Patients Table -->
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="20%">Hasta Adı</th>
                <th width="15%">Tür / Irk</th>
                <th width="10%">Yaş</th>
                <th width="20%">Sahip</th>
                <th width="15%">İletişim</th>
                <th width="15%">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td><?php echo $patient['id']; ?></td>
                    <td><?php echo htmlspecialchars($patient['name']); ?></td>
                    <td><?php echo htmlspecialchars($patient['type']); ?> 
                        <?php if (!empty($patient['breed'])): ?>
                            <br><small><?php echo htmlspecialchars($patient['breed']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if (!empty($patient['age'])) {
                            $years = floor($patient['age'] / 12);
                            $months = $patient['age'] % 12;
                            
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
                    <td><?php echo htmlspecialchars($patient['owner_name']); ?></td>
                    <td><?php echo htmlspecialchars($patient['owner_phone']); ?></td>
                    <td>
                        <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-xs"><i class="fa fa-eye"></i> Detay</a>
                        <a href="add_prescription.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-xs"><i class="fa fa-file-text"></i> Reçete Yaz</a>
                        <a href="#" class="btn btn-warning btn-xs edit-patient" data-id="<?php echo $patient['id']; ?>" data-toggle="modal" data-target="#editPatientModal">
                            <i class="fa fa-pencil"></i> Düzenle
                        </a>
                        <a href="patients.php?del_id=<?php echo $patient['id']; ?>" class="btn btn-danger btn-xs delete-patient" onclick="return confirm('Bu hastayı silmek istediğinizden emin misiniz?')">
                            <i class="fa fa-trash-o"></i> Sil
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php include_once 'includes/pagination.php'; ?>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Yeni Hasta Ekle</h4>
            </div>
            <form method="post" action="add_patient.php" class="form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Hasta Bilgileri</h4>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Hasta Adı *</label>
                                <input type="text" name="name" id="name" class="form-control" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type">Tür *</label>
                                <select name="type" id="type" class="form-control" required>
                                    <option value="">Seçiniz...</option>
                                    <option value="Kedi">Kedi</option>
                                    <option value="Köpek">Köpek</option>
                                    <option value="Kuş">Kuş</option>
                                    <option value="Kemirgen">Kemirgen</option>
                                    <option value="Balık">Balık</option>
                                    <option value="Sürüngen">Sürüngen</option>
                                    <option value="Diğer">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="breed">Irk</label>
                                <input type="text" name="breed" id="breed" class="form-control" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="age">Yaş (ay)</label>
                                <input type="number" name="age" id="age" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="gender">Cinsiyet</label>
                                <select name="gender" id="gender" class="form-control">
                                    <option value="">Seçiniz...</option>
                                    <option value="erkek">Erkek</option>
                                    <option value="dişi">Dişi</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <h4>Sahip Bilgileri</h4>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="owner_name">Sahibinin Adı *</label>
                                <input type="text" name="owner_name" id="owner_name" class="form-control" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="owner_phone">Sahibinin Telefonu *</label>
                                <input type="text" name="owner_phone" id="owner_phone" class="form-control" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="owner_email">Sahibinin E-Postası</label>
                                <input type="email" name="owner_email" id="owner_email" class="form-control" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="owner_address">Sahibinin Adresi</label>
                                <textarea name="owner_address" id="owner_address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="notes">Notlar</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Hasta Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Patient Modal (Skeleton - will be populated with AJAX) -->
<div class="modal fade" id="editPatientModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Hasta Bilgilerini Düzenle</h4>
            </div>
            <form method="post" action="edit_patient.php" class="form">
                <div class="modal-body">
                    <div id="editPatientContent">
                        <!-- Content will be loaded via AJAX -->
                        <div class="text-center">
                            <i class="fa fa-spinner fa-spin fa-3x"></i><br>
                            Yükleniyor...
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom JavaScript -->
<script>
$(document).ready(function() {
    // When the edit button is clicked, load patient data
    $('.edit-patient').click(function() {
        var patientId = $(this).data('id');
        $('#editPatientContent').load('get_patient.php?id=' + patientId);
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>