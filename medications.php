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

// Pagination için sayfa numarası
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$page) {
    $page = 1;
}

// Sayfa başına ilaç sayısı
$items_per_page = 10;

// Toplam ilaç sayısını al
$total_count = $db->getValue("medications", "count(*)");

// Arama parametresi
$search_str = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

if ($search_str) {
    $db->where('name', '%' . $search_str . '%', 'LIKE');
}

// Sıralama için kolon ve yön
$order_by = filter_input(INPUT_GET, 'order_by', FILTER_SANITIZE_STRING);
$order_dir = filter_input(INPUT_GET, 'order_dir', FILTER_SANITIZE_STRING);

if ($order_by && $order_dir) {
    $db->orderBy($order_by, $order_dir);
} else {
    $db->orderBy('name', 'ASC');
}

// Sayfalama için OFFSET ve LIMIT hesapla
$db->pageLimit = $items_per_page;
$medications = $db->arraybuilder()->paginate("medications", $page);

// Toplam sayfa sayısı
$total_pages = ceil($total_count / $items_per_page);

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-6">
            <h1 class="page-header">İlaçlar</h1>
        </div>
        <div class="col-lg-6">
            <div class="page-action-links text-right">
                <a href="add_medication.php" class="btn btn-success">
                    <i class="glyphicon glyphicon-plus"></i> Yeni İlaç Ekle
                </a>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>

    <!-- Filters -->
    <div class="well text-center filter-form">
        <form class="form form-inline" action="">
            <label for="search">İlaç Ara:</label>
            <input type="text" class="form-control" id="search" name="search" placeholder="İlaç adı..." value="<?php echo htmlspecialchars($search_str ?? ''); ?>">
            <input type="submit" value="Ara" class="btn btn-primary">
            <?php if ($search_str): ?>
                <a href="medications.php" class="btn btn-default">Temizle</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Table -->
    <table class="table table-striped table-bordered table-hover" id="medications-table">
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="40%">
                    <a href="medications.php?order_by=name&order_dir=<?php echo ($order_by == 'name' && $order_dir == 'ASC') ? 'DESC' : 'ASC'; ?>">
                        İlaç Adı
                        <?php if ($order_by == 'name'): ?>
                            <i class="glyphicon glyphicon-chevron-<?php echo ($order_dir == 'ASC') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th width="15%">
                    <a href="medications.php?order_by=unit&order_dir=<?php echo ($order_by == 'unit' && $order_dir == 'ASC') ? 'DESC' : 'ASC'; ?>">
                        Birim
                        <?php if ($order_by == 'unit'): ?>
                            <i class="glyphicon glyphicon-chevron-<?php echo ($order_dir == 'ASC') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th width="25%">
                    <a href="medications.php?order_by=description&order_dir=<?php echo ($order_by == 'description' && $order_dir == 'ASC') ? 'DESC' : 'ASC'; ?>">
                        Açıklama
                        <?php if ($order_by == 'description'): ?>
                            <i class="glyphicon glyphicon-chevron-<?php echo ($order_dir == 'ASC') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th width="15%">İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($medications as $medication): ?>
                <tr>
                    <td><?php echo $medication['id']; ?></td>
                    <td><?php echo htmlspecialchars($medication['name']); ?></td>
                    <td><?php echo htmlspecialchars($medication['unit']); ?></td>
                    <td><?php echo !empty($medication['description']) ? htmlspecialchars($medication['description']) : '<span class="text-muted">-</span>'; ?></td>
                    <td>
                        <a href="edit_medication.php?id=<?php echo $medication['id']; ?>" class="btn btn-primary btn-xs">
                            <i class="glyphicon glyphicon-edit"></i> Düzenle
                        </a>
                        <a href="#" class="btn btn-danger btn-xs delete-medication" data-id="<?php echo $medication['id']; ?>">
                            <i class="glyphicon glyphicon-trash"></i> Sil
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <div class="text-center">
        <?php if ($total_pages > 1): ?>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li>
                        <a href="medications.php?page=<?php echo ($page-1); ?>&search=<?php echo urlencode($search_str ?? ''); ?>&order_by=<?php echo $order_by; ?>&order_dir=<?php echo $order_dir; ?>">
                            <i class="glyphicon glyphicon-chevron-left"></i> Önceki
                        </a>
                    </li>
                <?php else: ?>
                    <li class="disabled"><span><i class="glyphicon glyphicon-chevron-left"></i> Önceki</span></li>
                <?php endif; ?>
                
                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a href="medications.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_str ?? ''); ?>&order_by=<?php echo $order_by; ?>&order_dir=<?php echo $order_dir; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="medications.php?page=<?php echo ($page+1); ?>&search=<?php echo urlencode($search_str ?? ''); ?>&order_by=<?php echo $order_by; ?>&order_dir=<?php echo $order_dir; ?>">
                            Sonraki <i class="glyphicon glyphicon-chevron-right"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="disabled"><span>Sonraki <i class="glyphicon glyphicon-chevron-right"></i></span></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirm-delete-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">İlacı Sil</h4>
            </div>
            <div class="modal-body">
                <p>Bu ilacı silmek istediğinize emin misiniz?</p>
                <p class="text-danger"><strong>Uyarı:</strong> Bu işlem geri alınamaz. İlacın kullanıldığı reçeteler etkilenebilir.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">İptal</button>
                <a href="#" class="btn btn-danger" id="confirm-delete">Sil</a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Delete medication confirmation
    $('.delete-medication').click(function(e) {
        e.preventDefault();
        
        var medicationId = $(this).data('id');
        var deleteUrl = 'delete_medication.php?id=' + medicationId;
        
        $('#confirm-delete').attr('href', deleteUrl);
        $('#confirm-delete-modal').modal('show');
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>