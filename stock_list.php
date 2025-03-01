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

// Sistem tarihi
$current_date = date('Y-m-d H:i:s');

// Veritabanı bağlantısı
$db = getDbInstance();

// Filtreleme parametreleri
$filter_stock = filter_input(INPUT_GET, 'filter_stock');
$search_str = filter_input(INPUT_GET, 'search_str');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$records_per_page = 15;

// Ana tablo medications olacak ve stock ile join yapılacak
$db->join("stock s", "medications.id = s.medication_id", "LEFT");

// Arama filtresi
if ($search_str) {
    $db->where('medications.name', '%' . $search_str . '%', 'LIKE');
}

// Stok durumu filtresi
if ($filter_stock == 'low') {
    $db->where("s.quantity < medications.min_stock AND s.quantity > 0");
} elseif ($filter_stock == 'out') {
    $db->where("s.quantity", 0);
    $db->orWhere("s.quantity", NULL);
}

// Toplam kayıt sayısı için yeni bir DB instance
$db2 = getDbInstance();
$db2->join("stock s", "medications.id = s.medication_id", "LEFT");
if ($search_str) {
    $db2->where('medications.name', '%' . $search_str . '%', 'LIKE');
}
if ($filter_stock == 'low') {
    $db2->where("s.quantity < medications.min_stock AND s.quantity > 0");
} elseif ($filter_stock == 'out') {
    $db2->where("s.quantity", 0);
    $db2->orWhere("s.quantity", NULL);
}
$total_count = $db2->getValue("medications", "count(*)");
$total_pages = ceil($total_count / $records_per_page);

// Ana sorgu
$db->pageLimit = $records_per_page;
$db->orderBy("medications.name", "ASC");
$medications = $db->arraybuilder()->paginate("medications", $page, [
    "medications.id",
    "medications.name",
    "medications.description",
    "medications.unit",
    "medications.min_stock",
    "s.quantity",
    "s.id as stock_id"
]);

include_once('includes/header.php');
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-6">
            <h1 class="page-header">Stok Listesi</h1>
        </div>
        <div class="col-lg-6" style="padding-top: 20px;">
            <div class="pull-right">
                <a href="add_medication.php" class="btn btn-success"><i class="fa fa-plus"></i> Yeni İlaç Ekle</a>
            </div>
        </div>
    </div>

    <!-- Filtreler -->
    <div class="well">
        <div class="row">
            <form class="form-horizontal" method="get" action="">
                <div class="col-sm-4">
                    <label for="search">İlaç Ara:</label>
                    <input type="text" name="search_str" id="search" class="form-control" 
                           placeholder="İlaç adı..." value="<?php echo htmlspecialchars($search_str ?? ''); ?>">
                </div>
                <div class="col-sm-4">
                    <label for="filter_stock">Stok Durumu:</label>
                    <select name="filter_stock" id="filter_stock" class="form-control">
                        <option value="">Tümü</option>
                        <option value="low" <?php echo ($filter_stock == 'low') ? 'selected' : ''; ?>>Kritik Seviye</option>
                        <option value="out" <?php echo ($filter_stock == 'out') ? 'selected' : ''; ?>>Tükenenler</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label>&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary">Filtrele</button>
                    <a href="stock_list.php" class="btn btn-default">Sıfırla</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stok Listesi -->
    <div class="panel panel-default">
        <div class="panel-heading">
            Stok Durumu
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>İlaç Adı</th>
                            <th>Açıklama</th>
                            <th width="100">Stok</th>
                            <th>Birim</th>
                            <th>Kritik Seviye</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medications as $medication): 
                            $quantity = $medication['quantity'] !== null ? $medication['quantity'] : 0;
                        ?>
                        <tr>
                            <td><?php echo $medication['id']; ?></td>
                            <td><?php echo htmlspecialchars($medication['name']); ?></td>
                            <td><?php echo htmlspecialchars($medication['description'] ?? ''); ?></td>
                            <td class="text-center">
                                <strong><?php echo $quantity; ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($medication['unit']); ?></td>
                            <td class="text-center"><?php echo $medication['min_stock']; ?></td>
                            <td class="text-center">
                                <?php if ($quantity == 0): ?>
                                    <span class="label label-danger">Tükendi</span>
                                <?php elseif ($quantity < $medication['min_stock']): ?>
                                    <span class="label label-warning">Kritik Seviye</span>
                                <?php else: ?>
                                    <span class="label label-success">Yeterli</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-xs">
                                    <a href="stock_adjustment.php?medication_id=<?php echo $medication['id']; ?>&current_quantity=<?php echo $quantity; ?>&unit=<?php echo urlencode($medication['unit']); ?>&name=<?php echo urlencode($medication['name']); ?>" 
                                       class="btn btn-default" title="Stok Düzelt">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="stock_history.php?medication_id=<?php echo $medication['id']; ?>" 
                                       class="btn btn-info" title="Geçmiş">
                                        <i class="fa fa-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sayfalama -->
            <?php if ($total_pages > 1): ?>
            <div class="text-center">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li>
                            <a href="<?php echo '?page='.($page-1) . 
                                    (isset($_GET['filter_stock']) ? '&filter_stock='.$filter_stock : '') .
                                    (isset($_GET['search_str']) ? '&search_str='.$search_str : ''); ?>" 
                               aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a href="<?php echo '?page='.$i . 
                                    (isset($_GET['filter_stock']) ? '&filter_stock='.$filter_stock : '') .
                                    (isset($_GET['search_str']) ? '&search_str='.$search_str : ''); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li>
                            <a href="<?php echo '?page='.($page+1) . 
                                    (isset($_GET['filter_stock']) ? '&filter_stock='.$filter_stock : '') .
                                    (isset($_GET['search_str']) ? '&search_str='.$search_str : ''); ?>" 
                               aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="panel-footer">
            <div class="row">
                <div class="col-md-6">
                    <p>Toplam <strong><?php echo $total_count; ?></strong> ilaç kaydı gösteriliyor.</p>
                </div>
                <div class="col-md-6 text-right">
                    <p class="text-muted">
                        <small>Son Güncelleme: <?php echo date('d.m.Y H:i', strtotime($current_date)); ?> | 
                              <?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('includes/footer.php'); ?>