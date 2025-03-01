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

// Veritabanı bağlantısı
$db = getDbInstance();

// Stok durumu filtreleme
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

if ($status == 'low') {
    $db->where("quantity < reorder_level AND reorder_level > 0");
} elseif ($status == 'out') {
    $db->where("quantity", 0);
} else {
    $db->where("quantity", 0, ">");
}

// Stok bilgilerini al
$stocks = $db->get("stock");

include_once('includes/header.php');
?>
<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Stok Listesi</h1>
        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-bar-chart-o fa-fw"></i> Stok Durumu
                    <div class="pull-right">
                        <div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                Filtrele <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu pull-right" role="menu">
                                <li><a href="stock_list.php?status=all">Tümü</a>
                                </li>
                                <li><a href="stock_list.php?status=low">Azalan Stok</a>
                                </li>
                                <li><a href="stock_list.php?status=out">Tükenen Stok</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- /.panel-heading -->
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>İlaç Adı</th>
                                    <th>Miktar</th>
                                    <th>Kritik Seviye</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
    <?php
    if ($stocks) {
        foreach ($stocks as $stock) {
            // İlaç bilgilerini medications tablosundan al
            $db->where('id', $stock['medication_id']);
            $medication = $db->getOne('medications');
            $medicine_name = $medication ? $medication['name'] : 'Bilinmeyen';
    ?>
    <tr>
        <td><?php echo $stock['id']; ?></td>
        <td><?php echo htmlspecialchars($medicine_name); ?></td>
        <td><?php echo $stock['quantity']; ?></td>
        <td><?php echo $stock['reorder_level']; ?></td>
        <td>
            <a href="edit_stock.php?id=<?php echo $stock['id']; ?>" class="btn btn-primary btn-xs">Düzenle</a>
        </td>
    </tr>
    <?php
        }
    } else {
        echo "<tr><td colspan='5'>Stok bilgisi bulunamadı</td></tr>";
    }
    ?>
</tbody>
                        </table>
                    </div>
                    <!-- /.table-responsive -->
                </div>
                <!-- /.panel-body -->
            </div>
            <!-- /.panel -->
        </div>
        <!-- /.col-lg-12 -->
    </div>
    <!-- /.row -->
</div>
<!-- /#page-wrapper -->

<?php include_once('includes/footer.php'); ?>