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

// Raporlama parametreleri
$start_date = filter_input(INPUT_GET, 'start_date');
$end_date = filter_input(INPUT_GET, 'end_date');
$medication_id = filter_input(INPUT_GET, 'medication_id', FILTER_VALIDATE_INT);
$action_type = filter_input(INPUT_GET, 'action_type');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$records_per_page = 15;

// İlaç seçeneklerini yükle
$db_med = getDbInstance();
$db_med->orderBy("name", "ASC");
$medications = $db_med->get('medications');

// Stok durumu özeti
$summary = array(
    'total_medications' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0
);

// Toplam ilaç sayısı
$db_total = getDbInstance();
$summary['total_medications'] = $db_total->getValue("medications", "count(*)");

// Tükenen ilaçları bul
$db_out = getDbInstance();
$db_out->join("stock s", "medications.id = s.medication_id", "LEFT");
$db_out->where("s.quantity", 0);
$summary['out_of_stock'] = $db_out->getValue("medications", "count(*)");

// Kritik seviyedeki ilaçları bul
$db_low = getDbInstance();
$db_low->join("stock s", "medications.id = s.medication_id", "LEFT");
$db_low->where("s.quantity < medications.min_stock AND s.quantity > 0");
$summary['low_stock'] = $db_low->getValue("medications", "count(*)");

// Stock history verilerini çek
$db_history = getDbInstance();

// Join işlemi
$db_history->join("medications m", "m.id = stock_history.medication_id", "LEFT");

// Filtre koşullarını ekle
if ($start_date) {
    $db_history->where('stock_history.created_at', $start_date . ' 00:00:00', '>=');
}

if ($end_date) {
    $db_history->where('stock_history.created_at', $end_date . ' 23:59:59', '<=');
}

if ($medication_id) {
    $db_history->where('stock_history.medication_id', $medication_id);
}

if ($action_type) {
    $db_history->where('stock_history.type', $action_type);
}

// Sıralama
$db_history->orderBy("stock_history.created_at", "DESC");

// Sayfalama için toplam kayıt sayısı
$total_count = $db_history->getValue("stock_history", "count(*)");
$total_pages = ceil($total_count / $records_per_page);

// Stock history verilerini çek
$db_history->pageLimit = $records_per_page;
$stock_history = $db_history->arraybuilder()->paginate("stock_history", $page);

// Kritik seviyedeki ilaçları getir
$db_critical = getDbInstance();
$db_critical->join("stock s", "medications.id = s.medication_id", "LEFT");
$db_critical->where("s.quantity < medications.min_stock");
$db_critical->orderBy("s.quantity", "ASC");
$critical_medications = $db_critical->get("medications", 5);

// İlaç bilgilerini lookup array'e dönüştür
$med_lookup = array();
foreach ($medications as $med) {
    $med_lookup[$med['id']] = $med;
}

include_once('includes/header.php');
?>

<!-- Üst özet kartları -->
<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Stok Raporu</h1>
        </div>
    </div>

    <!-- Stok Özeti -->
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-medkit fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $summary['total_medications']; ?></div>
                            <div>Toplam İlaç</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-yellow">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-warning fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $summary['low_stock']; ?></div>
                            <div>Kritik Seviye</div>
                        </div>
                    </div>
                </div>
                <a href="stock_list.php?filter_stock=low">
                    <div class="panel-footer">
                        <span class="pull-left">Detaylar</span>
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
                            <i class="fa fa-times-circle fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $summary['out_of_stock']; ?></div>
                            <div>Tükenen</div>
                        </div>
                    </div>
                </div>
                <a href="stock_list.php?filter_stock=out">
                    <div class="panel-footer">
                        <span class="pull-left">Detaylar</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Yazdırma düğmesi paneli -->
        <div class="col-lg-3 col-md-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="fa fa-print fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><i class="fa fa-file-pdf-o"></i></div>
                            <div>Raporu Yazdır</div>
                        </div>
                    </div>
                </div>
                <?php
                // Yazdırma sayfasına geçiş için URL parametreleri
                $print_url = 'stock_history_print.php?';
                if ($start_date) $print_url .= 'date_start=' . urlencode($start_date) . '&';
                if ($end_date) $print_url .= 'date_end=' . urlencode($end_date) . '&';
                if ($medication_id) $print_url .= 'medication_id=' . urlencode($medication_id) . '&';
                if ($action_type) $print_url .= 'type=' . urlencode($action_type) . '&';
                $print_url .= 'format=pdf';
                ?>
                <a href="<?php echo $print_url; ?>" target="_blank">
                    <div class="panel-footer">
                        <span class="pull-left">PDF olarak yazdır</span>
                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>
                        <div class="clearfix"></div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Filtreleme Formu -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Stok Hareketleri Raporu</h3>
        </div>
        <div class="panel-body">
            <form class="form-inline" method="get">
                <div class="form-group">
                    <label for="start_date">Başlangıç:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Bitiş:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="form-group">
                    <label for="medication_id">İlaç:</label>
                    <select id="medication_id" name="medication_id" class="form-control">
                        <option value="">Tüm İlaçlar</option>
                        <?php foreach ($medications as $medication): ?>
                        <option value="<?php echo $medication['id']; ?>" <?php echo ($medication_id == $medication['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($medication['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="action_type">İşlem:</label>
                    <select id="action_type" name="action_type" class="form-control">
                        <option value="">Tüm İşlemler</option>
                        <option value="add" <?php echo ($action_type == 'add') ? 'selected' : ''; ?>>Stok Girişi</option>
                        <option value="subtract" <?php echo ($action_type == 'subtract') ? 'selected' : ''; ?>>Stok Çıkışı</option>
                        <option value="adjust" <?php echo ($action_type == 'adjust') ? 'selected' : ''; ?>>Stok Düzeltme</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filtrele</button>
                <a href="stock_report.php" class="btn btn-default">Sıfırla</a>
                
                <!-- Yazdırma düğmeleri ekle -->
                <div class="pull-right">
                    <?php
                    // Yazdırma sayfasına geçiş için URL parametreleri
                    $pdf_url = 'stock_history_print.php?';
                    $excel_url = 'stock_history_print.php?';
                    
                    if ($start_date) {
                        $pdf_url .= 'date_start=' . urlencode($start_date) . '&';
                        $excel_url .= 'date_start=' . urlencode($start_date) . '&';
                    }
                    if ($end_date) {
                        $pdf_url .= 'date_end=' . urlencode($end_date) . '&';
                        $excel_url .= 'date_end=' . urlencode($end_date) . '&';
                    }
                    if ($medication_id) {
                        $pdf_url .= 'medication_id=' . urlencode($medication_id) . '&';
                        $excel_url .= 'medication_id=' . urlencode($medication_id) . '&';
                    }
                    if ($action_type) {
                        $pdf_url .= 'type=' . urlencode($action_type) . '&';
                        $excel_url .= 'type=' . urlencode($action_type) . '&';
                    }
                    
                    $pdf_url .= 'format=pdf';
                    $excel_url .= 'format=excel';
                    ?>
                    <a href="<?php echo $pdf_url; ?>" target="_blank" class="btn btn-danger">
                        <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                    <a href="<?php echo $excel_url; ?>" target="_blank" class="btn btn-success">
                        <i class="fa fa-file-excel-o"></i> Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stok Hareketleri Listesi -->
    <div class="panel panel-default">
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>İlaç</th>
                            <th>İşlem</th>
                            <th>Miktar</th>
                            <th>Birim</th>
                            <th>Referans</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_history as $history): 
                            $medication = isset($med_lookup[$history['medication_id']]) ? $med_lookup[$history['medication_id']] : null;
                        ?>
                        <tr>
                            <td><?php echo date('d.m.Y H:i', strtotime($history['created_at'])); ?></td>
                            <td><?php echo $medication ? htmlspecialchars($medication['name']) : 'Bilinmiyor'; ?></td>
                            <td>
                                <?php if ($history['type'] == 'add'): ?>
                                    <span class="label label-success">Giriş</span>
                                <?php elseif ($history['type'] == 'subtract'): ?>
                                    <span class="label label-danger">Çıkış</span>
                                <?php else: ?>
                                    <span class="label label-info">Düzeltme</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $history['quantity_change']; ?></td>
                            <td><?php echo $medication ? htmlspecialchars($medication['unit']) : '-'; ?></td>
                            <td>
                                <?php 
                                if ($history['reference_type'] == 'prescription') {
                                    echo '<a href="prescription_details.php?id=' . $history['reference_id'] . '">Reçete #' . $history['reference_id'] . '</a>';
                                } elseif ($history['reference_type'] == 'adjustment') {
                                    echo 'Manuel Düzeltme #' . $history['reference_id'];
                                } else {
                                    echo $history['reference_type'] . ' #' . $history['reference_id'];
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($history['notes']); ?></td>
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
                                    (isset($_GET['start_date']) ? '&start_date='.$start_date : '') . 
                                    (isset($_GET['end_date']) ? '&end_date='.$end_date : '') .
                                    (isset($_GET['medication_id']) ? '&medication_id='.$medication_id : '') .
                                    (isset($_GET['action_type']) ? '&action_type='.$action_type : ''); ?>" 
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
                                    (isset($_GET['start_date']) ? '&start_date='.$start_date : '') . 
                                    (isset($_GET['end_date']) ? '&end_date='.$end_date : '') .
                                    (isset($_GET['medication_id']) ? '&medication_id='.$medication_id : '') .
                                    (isset($_GET['action_type']) ? '&action_type='.$action_type : ''); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <li>
                            <a href="<?php echo '?page='.($page+1) . 
                                    (isset($_GET['start_date']) ? '&start_date='.$start_date : '') . 
                                    (isset($_GET['end_date']) ? '&end_date='.$end_date : '') .
                                    (isset($_GET['medication_id']) ? '&medication_id='.$medication_id : '') .
                                    (isset($_GET['action_type']) ? '&action_type='.$action_type : ''); ?>" 
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
                    <p>Toplam <strong><?php echo $total_count; ?></strong> stok hareketi gösteriliyor.</p>
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

<script>
$(document).ready(function(){
    // Başlangıç tarihi değişirse, bitiş tarihi en az başlangıç tarihi kadar olmalı
    $('#start_date').change(function(){
        var startDate = $(this).val();
        $('#end_date').attr('min', startDate);
        
        if($('#end_date').val() < startDate) {
            $('#end_date').val(startDate);
        }
    });
    
    // Bitiş tarihi değişirse, başlangıç tarihi en fazla bitiş tarihi kadar olmalı
    $('#end_date').change(function(){
        var endDate = $(this).val();
        $('#start_date').attr('max', endDate);
        
        if($('#start_date').val() > endDate) {
            $('#start_date').val(endDate);
        }
    });
});
</script>

<?php include_once('includes/footer.php'); ?>