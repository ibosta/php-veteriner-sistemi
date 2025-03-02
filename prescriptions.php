<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Delete prescription
if (isset($_GET['del_id']) && $_GET['del_id'] !== '') {
    $del_id = filter_input(INPUT_GET, 'del_id', FILTER_VALIDATE_INT);
    if ($del_id) {
        $db->where('id', $del_id);
        $status = $db->delete('prescriptions');
        if ($status) {
            $_SESSION['success'] = "Reçete başarıyla silindi!";
        } else {
            $_SESSION['failure'] = "Reçete silinemedi. Lütfen tekrar deneyin!";
        }
        header('Location: prescriptions.php');
        exit;
    }
}

// Search filters
$search_str = filter_input(INPUT_GET, 'search_str');
$filter_col = filter_input(INPUT_GET, 'filter_col');
$order_by = filter_input(INPUT_GET, 'order_by');
$order_type = filter_input(INPUT_GET, 'order_type');
$patient_id = filter_input(INPUT_GET, 'patient_id', FILTER_VALIDATE_INT);

// Pagination
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page ? $page : 1;
$records_per_page = 10;
$starting_limit = ($page - 1) * $records_per_page;

// Join tables for detailed information
$db->join("patients p", "pr.patient_id = p.id", "LEFT");
// Manuel olarak kullanıcı adını ayarlama
if ($_SESSION['user_type'] === 'admin') {
    $db2 = getDbInstance();
    $db2->where('id', $_SESSION['user_id']);
    $user_data = $db2->getOne('admin_accounts');
    $current_user = $user_data ? htmlspecialchars($user_data['name']) : 'DR. İbrahim Taşkıran';
} else {
    $db2 = getDbInstance();
    $db2->where('id', $_SESSION['user_id']);
    $user_data = $db2->getOne('users');
    $current_user = $user_data ? htmlspecialchars($user_data['name']) : 'DR. İbrahim Taşkıran';
}

// Apply search filters
if ($search_str) {
    $db->where('p.name', '%' . $search_str . '%', 'LIKE');
    $db->orWhere('pr.diagnosis', '%' . $search_str . '%', 'LIKE');
}

if ($patient_id) {
    $db->where('pr.patient_id', $patient_id);
}

// Set order 
if ($filter_col && $order_type) {
    if ($filter_col == 'patient_name') {
        $db->orderBy('p.name', $order_type);
    } elseif ($filter_col == 'doctor_name') {
        $db->orderBy('u.name', $order_type);
    } else {
        $db->orderBy('pr.' . $filter_col, $order_type);
    }
} else {
    $db->orderBy('pr.created_at', 'DESC');
}

// Get total records count
$db->pageLimit = $records_per_page;
$prescriptions = $db->arraybuilder()->paginate("prescriptions pr", $page, "pr.*, p.name as patient_name, '{$current_user}' as doctor_name");
$total_records = $db->totalCount;
$total_pages = $db->totalPages;

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-6">
            <h1 class="page-header">Reçete Yönetimi</h1>
        </div>
        <div class="col-lg-6">
            <div class="page-action-links text-right">
                <a href="add_prescription.php" class="btn btn-success">
                    <i class="fa fa-plus"></i> Yeni Reçete Oluştur
                </a>
            </div>
        </div>
    </div>
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <!-- Filters -->
    <div class="well text-center filter-form">
        <form class="form form-inline" action="">
            <label for="search_str">Ara</label>
            <input type="text" class="form-control" id="search_str" name="search_str" placeholder="Hasta adı veya tanı" value="<?php echo $search_str; ?>">
            <label for="filter_col">Sıralama</label>
            <select class="form-control" id="filter_col" name="filter_col">
                <option value="created_at" <?php if ($filter_col == "created_at") echo "selected"; ?>>Tarih</option>
                <option value="patient_name" <?php if ($filter_col == "patient_name") echo "selected"; ?>>Hasta Adı</option>
                <option value="diagnosis" <?php if ($filter_col == "diagnosis") echo "selected"; ?>>Tanı</option>
                <option value="doctor_name" <?php if ($filter_col == "doctor_name") echo "selected"; ?>>Veteriner</option>
            </select>
            <select class="form-control" id="order_by" name="order_type">
                <option value="ASC" <?php if ($order_type == "ASC") echo "selected"; ?>>Artan</option>
                <option value="DESC" <?php if ($order_type == "DESC") echo "selected"; ?>>Azalan</option>
            </select>
            <input type="submit" value="Uygula" class="btn btn-primary">
            <a href="prescriptions.php" class="btn btn-warning">Sıfırla</a>
        </form>
    </div>
    
    <!-- Prescriptions Table -->
    <table class="table table-striped table-bordered table-hover">
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="15%">Tarih</th>
                <th width="20%">Hasta</th>
                <th width="20%">Tanı</th>
                <th width="15%">Veteriner</th>
                <th width="25%">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($prescriptions as $prescription): ?>
                <tr>
                    <td><?php echo $prescription['id']; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($prescription['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($prescription['diagnosis']); ?></td>
                    <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                    <td>
                        <a href="prescription_details.php?id=<?php echo $prescription['id']; ?>" class="btn btn-primary btn-xs">
                            <i class="fa fa-eye"></i> Detay
                        </a>
                        <a href="prescription_pdf.php?id=<?php echo $prescription['id']; ?>" class="btn btn-info btn-xs" target="_blank">
                            <i class="fa fa-file-pdf-o"></i> PDF
                        </a>
                        <a href="edit_prescription.php?id=<?php echo $prescription['id']; ?>" class="btn btn-warning btn-xs">
                            <i class="fa fa-pencil"></i> Düzenle
                        </a>
                        <a href="prescriptions.php?del_id=<?php echo $prescription['id']; ?>" class="btn btn-danger btn-xs delete-prescription" onclick="return confirm('Bu reçeteyi silmek istediğinizden emin misiniz?')">
                            <i class="fa fa-trash-o"></i> Sil
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($prescriptions)): ?>
                <tr>
                    <td colspan="6" class="text-center">Kayıtlı reçete bulunamadı</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php
    // Set pagination variables
    $pagination_url = 'prescriptions.php';
    $total_pages = $total_pages;
    $total_records = $total_records;
    
    include_once 'includes/pagination.php';
    ?>
</div>

<?php include_once 'includes/footer.php'; ?>