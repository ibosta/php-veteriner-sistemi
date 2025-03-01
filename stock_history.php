<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Current date and time (UTC)
$current_date = '2025-03-01 15:08:37';
$current_user = 'ibosta';

// DB instance
$db = getDbInstance();

// İlaç ID'si parametresi
$medication_id = filter_input(INPUT_GET, 'medication_id', FILTER_VALIDATE_INT);

// Filtreleme değişkenleri
$filter_date_start = filter_input(INPUT_GET, 'date_start', FILTER_SANITIZE_STRING);
$filter_date_end = filter_input(INPUT_GET, 'date_end', FILTER_SANITIZE_STRING);
$filter_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING);
$filter_reference = filter_input(INPUT_GET, 'reference', FILTER_SANITIZE_STRING);

// Varsayılan tarih aralığı (son 30 gün)
if (!$filter_date_start) {
    $filter_date_start = date('Y-m-d', strtotime('-30 days'));
}

if (!$filter_date_end) {
    $filter_date_end = date('Y-m-d');
}

// İlaç bilgilerini al
$medication = null;
if ($medication_id) {
    $db->where('id', $medication_id);
    $medication = $db->getOne('medications', 'id, name, unit, description');
    
    if (!$medication) {
        $_SESSION['failure'] = "İlaç bulunamadı!";
        header('Location: medications.php');
        exit;
    }
}

// İşlem türleri ve referanslar
$types = ['add' => 'Ekleme', 'subtract' => 'Çıkarma', 'adjust' => 'Düzeltme'];
$references = [
    'purchase' => 'Satın Alma',
    'prescription' => 'Reçete',
    'transfer' => 'Transfer',
    'expired' => 'Son Kullanma',
    'adjustment' => 'Manuel Düzeltme',
    'inventory' => 'Envanter Sayımı'
];

// Stok geçmişi verileri
try {
    $db = getDbInstance();
    
    if ($medication_id) {
        $db->where('medication_id', $medication_id);
    }
    
    if ($filter_type) {
        $db->where('type', $filter_type);
    }
    
    if ($filter_reference) {
        $db->where('reference_type', $filter_reference);
    }
    
    $db->where('DATE(created_at) BETWEEN ? AND ?', [$filter_date_start, $filter_date_end]);
    $db->orderBy('created_at', 'DESC');
    
    // Pagination
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $items_per_page = 20;
    $total_count = $db->getValue("stock_history", "count(*)");
    $db->pageLimit = $items_per_page;
    
    // Get records
    $result = $db->arraybuilder()->paginate("stock_history", $page);
    
    // Add medication details
    if (!empty($result)) {
        $medication_ids = array_unique(array_column($result, 'medication_id'));
        
        $db = getDbInstance();
        $db->where('id', $medication_ids, 'IN');
        $medications = $db->get('medications', null, 'id, name, unit');
        
        $medications_by_id = [];
        foreach ($medications as $med) {
            $medications_by_id[$med['id']] = $med;
        }
        
        foreach ($result as &$record) {
            $med_id = $record['medication_id'];
            if (isset($medications_by_id[$med_id])) {
                $med = $medications_by_id[$med_id];
                $record['medication_name'] = $med['name'];
                $record['unit'] = $med['unit'];
            } else {
                $record['medication_name'] = 'Bilinmeyen İlaç';
                $record['unit'] = 'birim';
            }
            $record['user_name'] = $current_user;
        }
        unset($record);
    }
    
    $total_pages = ceil($total_count / $items_per_page);
    
    // Statistics for specific medication
    if ($medication_id) {
        // Totals
        $db = getDbInstance();
        $db->where('medication_id', $medication_id);
        $db->where('type', 'add');
        $total_added = $db->getValue("stock_history", "SUM(quantity_change)") ?: 0;
        
        $db = getDbInstance();
        $db->where('medication_id', $medication_id);
        $db->where('type', 'subtract');
        $total_subtracted = $db->getValue("stock_history", "SUM(quantity_change)") ?: 0;
        
        $db = getDbInstance();
        $db->where('medication_id', $medication_id);
        $db->where('type', 'adjust');
        $total_adjusted = $db->getValue("stock_history", "SUM(quantity_change)") ?: 0;
        
        // Weekly chart data
        $db = getDbInstance();
        $db->where('medication_id', $medication_id);
        $db->where('DATE(created_at) >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)');
        $db->orderBy('DATE(created_at)', 'ASC');
        $db->groupBy('DATE(created_at), type');
        $weekly_movements = $db->get("stock_history", null, "DATE(created_at) as date, type, SUM(quantity_change) as total");
        
        // Chart data arrays
        $chart_dates = [];
        $chart_add = [];
        $chart_subtract = [];
        $chart_adjust = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $chart_dates[] = date('d.m.Y', strtotime($date));
            
            $add_found = $subtract_found = $adjust_found = false;
            
            foreach ($weekly_movements as $move) {
                if ($move['date'] == $date) {
                    switch ($move['type']) {
                        case 'add':
                            $chart_add[] = $move['total'];
                            $add_found = true;
                            break;
                        case 'subtract':
                            $chart_subtract[] = $move['total'];
                            $subtract_found = true;
                            break;
                        case 'adjust':
                            $chart_adjust[] = $move['total'];
                            $adjust_found = true;
                            break;
                    }
                }
            }
            
            if (!$add_found) $chart_add[] = 0;
            if (!$subtract_found) $chart_subtract[] = 0;
            if (!$adjust_found) $chart_adjust[] = 0;
        }
    }
    
} catch (Exception $e) {
    $_SESSION['failure'] = "Veritabanı hatası: " . $e->getMessage();
    $result = [];
    $total_count = 0;
    $total_pages = 0;
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <?php if ($medication): ?>
                <h1 class="page-header"><?php echo htmlspecialchars($medication['name']); ?> - Stok Hareket Geçmişi</h1>
            <?php else: ?>
                <h1 class="page-header">Stok Hareket Geçmişi</h1>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <?php if (empty($result) && $total_count == 0): ?>
    <div class="alert alert-info">
        <h4><i class="fa fa-info-circle"></i> Stok Geçmişi Bulunamadı</h4>
        <p>Henüz stok hareketi kaydedilmemiş.</p>
        <a href="index.php" class="btn btn-primary">Ana Sayfaya Dön</a>
    </div>
    <?php else: ?>
    
    <!-- İlaç Bilgileri -->
    <?php if ($medication): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-info-circle fa-fw"></i> İlaç Bilgileri
                    <div class="pull-right">
                        <a href="medications.php" class="btn btn-default btn-xs">
                            <i class="fa fa-list"></i> Tüm İlaçlar
                        </a>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered table-striped">
                                <tr>
                                    <th style="width: 30%;">İlaç Adı:</th>
                                    <td><?php echo htmlspecialchars($medication['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Birim:</th>
                                    <td><?php echo htmlspecialchars($medication['unit']); ?></td>
                                </tr>
                                <tr>
                                    <th>Açıklama:</th>
                                    <td><?php echo htmlspecialchars($medication['description']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- İstatistikler ve Grafik -->
    <div class="row">
        <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-bar-chart-o fa-fw"></i> Stok Hareketleri Özeti
                </div>
                <div class="panel-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item">
                            <i class="fa fa-plus text-success"></i> Toplam Giriş
                            <span class="badge" style="background-color: #5cb85c;">
                                <?php echo isset($total_added) ? number_format($total_added, 2) : 0; ?> 
                                <?php echo htmlspecialchars($medication['unit']); ?>
                            </span>
                        </a>
                        <a href="#" class="list-group-item">
                            <i class="fa fa-minus text-danger"></i> Toplam Çıkış
                            <span class="badge" style="background-color: #d9534f;">
                                <?php echo isset($total_subtracted) ? number_format($total_subtracted, 2) : 0; ?> 
                                <?php echo htmlspecialchars($medication['unit']); ?>
                            </span>
                        </a>
                        <a href="#" class="list-group-item">
                            <i class="fa fa-refresh text-warning"></i> Toplam Düzeltme
                            <span class="badge" style="background-color: #f0ad4e;">
                                <?php echo isset($total_adjusted) ? number_format($total_adjusted, 2) : 0; ?> 
                                <?php echo htmlspecialchars($medication['unit']); ?>
                            </span>
                        </a>
                        <a href="#" class="list-group-item">
                            <i class="fa fa-balance-scale text-primary"></i> Net Değişim
                            <span class="badge" style="background-color: #337ab7;">
                                <?php 
                                $net_change = ($total_added ?? 0) - ($total_subtracted ?? 0) + ($total_adjusted ?? 0);
                                echo number_format($net_change, 2) . ' ' . htmlspecialchars($medication['unit']); 
                                ?>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-area-chart fa-fw"></i> Son 7 Günlük Stok Hareketleri
                </div>
                <div class="panel-body">
                    <canvas id="stockMovementChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filtreleme -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="fa fa-filter fa-fw"></i> Filtrele
            <div class="pull-right">
                <button type="button" class="btn btn-default btn-xs" data-toggle="collapse" data-target="#filterCollapse">
                    <i class="fa fa-caret-down"></i> Filtre Seçenekleri
                </button>
            </div>
        </div>
        <div id="filterCollapse" class="panel-body collapse <?php echo (isset($_GET['date_start']) || isset($_GET['type']) || isset($_GET['reference'])) ? 'in' : ''; ?>">
            <form method="get" action="" class="form-horizontal">
                <?php if ($medication_id): ?>
                <input type="hidden" name="medication_id" value="<?php echo $medication_id; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="col-sm-4 control-label">Tarih Aralığı</label>
                            <div class="col-sm-8">
                                <div class="input-group input-daterange">
                                    <input type="date" class="form-control" name="date_start" value="<?php echo $filter_date_start; ?>">
                                    <span class="input-group-addon">-</span>
                                    <input type="date" class="form-control" name="date_end" value="<?php echo $filter_date_end; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="type" class="col-sm-4 control-label">İşlem Tipi</label>
                            <div class="col-sm-8">
                                <select name="type" id="type" class="form-control">
                                    <option value="">Tümü</option>
                                    <?php foreach ($types as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filter_type == $key) ? 'selected': ''; ?>><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="reference" class="col-sm-4 control-label">Referans</label>
                            <div class="col-sm-8">
                                <select name="reference" id="reference" class="form-control">
                                    <option value="">Tümü</option>
                                    <?php foreach ($references as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($filter_reference == $key) ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i> Filtrele
                        </button>
                        <?php if ($medication_id): ?>
                            <a href="stock_history.php?medication_id=<?php echo $medication_id; ?>" class="btn btn-default">
                                <i class="fa fa-refresh"></i> Sıfırla
                            </a>
                        <?php else: ?>
                            <a href="stock_history.php" class="btn btn-default">
                                <i class="fa fa-refresh"></i> Sıfırla
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stok Hareketleri Tablosu -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="12%">Tarih/Saat</th>
                    <?php if (!$medication_id): ?>
                    <th width="18%">İlaç</th>
                    <?php endif; ?>
                    <th width="10%">İşlem</th>
                    <th width="10%">Miktar</th>
                    <th width="15%">Referans</th>
                    <th width="20%">Not</th>
                    <th width="10%">Kullanıcı</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result): ?>
                    <?php $row_number = ($page - 1) * $items_per_page + 1; ?>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?php echo $row_number++; ?></td>
                            <td><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                            
                            <?php if (!$medication_id): ?>
                            <td>
                                <a href="stock_history.php?medication_id=<?php echo $row['medication_id']; ?>">
                                    <?php echo htmlspecialchars($row['medication_name']); ?>
                                </a>
                            </td>
                            <?php endif; ?>
                            
                            <td>
                                <?php if ($row['type'] == 'add'): ?>
                                    <span class="label label-success">Ekleme</span>
                                <?php elseif ($row['type'] == 'subtract'): ?>
                                    <span class="label label-danger">Çıkarma</span>
                                <?php else: ?>
                                    <span class="label label-warning">Düzeltme</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php 
                                    $prefix = '';
                                    $class = '';
                                    if ($row['type'] == 'add') {
                                        $prefix = '+';
                                        $class = 'text-success';
                                    } elseif ($row['type'] == 'subtract') {
                                        $prefix = '-';
                                        $class = 'text-danger';
                                    }
                                    echo '<span class="'.$class.'">'.$prefix . number_format($row['quantity_change'], 2) . ' ' . $row['unit'].'</span>'; 
                                ?>
                            </td>
                            
                            <td>
                                <?php
                                    $ref_text = '';
                                    $ref_link = '#';
                                    $ref_class = 'default';
                                    
                                    switch($row['reference_type']) {
                                        case 'purchase':
                                            $ref_text = 'Satın Alma';
                                            $ref_link = 'purchase_details.php?id=' . $row['reference_id'];
                                            $ref_class = 'success';
                                            break;
                                        case 'prescription':
                                            $ref_text = 'Reçete #' . $row['reference_id'];
                                            $ref_link = 'prescription_details.php?id=' . $row['reference_id'];
                                            $ref_class = 'info';
                                            break;
                                        case 'transfer':
                                            $ref_text = 'Transfer';
                                            $ref_class = 'primary';
                                            break;
                                        case 'expired':
                                            $ref_text = 'Son Kullanma';
                                            $ref_class = 'warning';
                                            break;
                                        case 'adjustment':
                                            $ref_text = 'Manuel Düzeltme';
                                            $ref_class = 'default';
                                            break;
                                        case 'inventory':
                                            $ref_text = 'Envanter Sayımı';
                                            $ref_link = 'inventory_details.php?id=' . $row['reference_id'];
                                            $ref_class = 'default';
                                            break;
                                        default:
                                            $ref_text = $row['reference_type'] ?: 'Bilinmiyor';
                                    }
                                    
                                    if ($row['reference_id']) {
                                        echo '<a href="'.$ref_link.'" class="btn btn-'.$ref_class.' btn-xs">'.$ref_text.'</a>';
                                    } else {
                                        echo '<span class="label label-'.$ref_class.'">'.$ref_text.'</span>';
                                    }
                                ?>
                            </td>
                            
                            <td><?php echo nl2br(htmlspecialchars($row['notes'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $medication_id ? '7' : '8'; ?>" class="text-center">
                            <i class="fa fa-info-circle"></i> Kayıt bulunamadı.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Sayfalama -->
    <?php if ($total_pages > 1): ?>
    <div class="text-center">
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li>
                    <a href="<?php echo '?page=1'.($medication_id ? '&medication_id='.$medication_id : '')
                        .($filter_date_start ? '&date_start='.$filter_date_start : '')
                        .($filter_date_end ? '&date_end='.$filter_date_end : '')
                        .($filter_type ? '&type='.$filter_type : '')
                        .($filter_reference ? '&reference='.$filter_reference : ''); ?>" aria-label="İlk">
                        <span aria-hidden="true">&laquo;&laquo;</span>
                    </a>
                </li>
                <li>
                <a href="<?php echo '?page='.($page-1).($medication_id ? '&medication_id='.$medication_id : '')
                        .($filter_date_start ? '&date_start='.$filter_date_start : '')
                        .($filter_date_end ? '&date_end='.$filter_date_end : '')
                        .($filter_type ? '&type='.$filter_type : '')
                        .($filter_reference ? '&reference='.$filter_reference : ''); ?>" aria-label="Önceki">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 3);
            $end_page = min($total_pages, $page + 3);
            
            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
                <li class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a href="<?php echo '?page='.$i.($medication_id ? '&medication_id='.$medication_id : '')
                        .($filter_date_start ? '&date_start='.$filter_date_start : '')
                        .($filter_date_end ? '&date_end='.$filter_date_end : '')
                        .($filter_type ? '&type='.$filter_type : '')
                        .($filter_reference ? '&reference='.$filter_reference : ''); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <li>
                    <a href="<?php echo '?page='.($page+1).($medication_id ? '&medication_id='.$medication_id : '')
                        .($filter_date_start ? '&date_start='.$filter_date_start : '')
                        .($filter_date_end ? '&date_end='.$filter_date_end : '')
                        .($filter_type ? '&type='.$filter_type : '')
                        .($filter_reference ? '&reference='.$filter_reference : ''); ?>" aria-label="Sonraki">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo '?page='.$total_pages.($medication_id ? '&medication_id='.$medication_id : '')
                        .($filter_date_start ? '&date_start='.$filter_date_start : '')
                        .($filter_date_end ? '&date_end='.$filter_date_end : '')
                        .($filter_type ? '&type='.$filter_type : '')
                        .($filter_reference ? '&reference='.$filter_reference : ''); ?>" aria-label="Son">
                        <span aria-hidden="true">&raquo;&raquo;</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="panel-footer">
        <div class="row">
            <div class="col-md-6">
                <p>Toplam <strong><?php echo $total_count; ?></strong> stok hareketi kayıtlı.</p>
            </div>
            <div class="col-md-6 text-right">
                <p class="text-muted">
                    <small>Son Güncelleme: <?php echo date('d.m.Y H:i:s', strtotime($current_date)); ?> | <?php echo htmlspecialchars($current_user); ?></small>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

<?php if ($medication_id && isset($chart_dates)): ?>
<script>
// Son 7 günlük stok hareketleri grafiği
var ctx = document.getElementById('stockMovementChart').getContext('2d');
var stockMovementChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_dates); ?>,
        datasets: [
            {
                label: 'Eklemeler',
                data: <?php echo json_encode($chart_add); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.4
            },
            {
                label: 'Çıkarmalar',
                data: <?php echo json_encode($chart_subtract); ?>,
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.4
            },
            {
                label: 'Düzeltmeler',
                data: <?php echo json_encode($chart_adjust); ?>,
                backgroundColor: 'rgba(255, 206, 86, 0.2)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 2,
                pointRadius: 4,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            yAxes: [{
                ticks: {
                    beginAtZero: true
                }
            }]
        },
        tooltips: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: function(tooltipItem, data) {
                    var label = data.datasets[tooltipItem.datasetIndex].label || '';
                    if (label) {
                        label += ': ';
                    }
                    label += tooltipItem.yLabel + ' <?php echo htmlspecialchars($medication['unit']); ?>';
                    return label;
                }
            }
        },
        hover: {
            mode: 'nearest',
            intersect: true
        },
        animation: {
            duration: 1000
        }
    }
});
</script>
<?php endif; ?>

<?php include_once 'includes/footer.php'; ?>