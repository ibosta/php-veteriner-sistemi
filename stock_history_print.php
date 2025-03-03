<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Parametre alma
$format = filter_input(INPUT_GET, 'format');
$format = $format !== null ? htmlspecialchars($format, ENT_QUOTES, 'UTF-8') : '';

$medication_id = filter_input(INPUT_GET, 'medication_id', FILTER_VALIDATE_INT);

$filter_date_start = filter_input(INPUT_GET, 'date_start');
$filter_date_start = $filter_date_start !== null ? htmlspecialchars($filter_date_start, ENT_QUOTES, 'UTF-8') : '';

$filter_date_end = filter_input(INPUT_GET, 'date_end');
$filter_date_end = $filter_date_end !== null ? htmlspecialchars($filter_date_end, ENT_QUOTES, 'UTF-8') : '';

$filter_type = filter_input(INPUT_GET, 'type');
$filter_type = $filter_type !== null ? htmlspecialchars($filter_type, ENT_QUOTES, 'UTF-8') : '';

$filter_reference = filter_input(INPUT_GET, 'reference');
$filter_reference = $filter_reference !== null ? htmlspecialchars($filter_reference, ENT_QUOTES, 'UTF-8') : '';

// Varsayılan tarih aralığı
if (!$filter_date_start) {
    $filter_date_start = date('Y-m-d', strtotime('-30 days'));
}

if (!$filter_date_end) {
    $filter_date_end = date('Y-m-d');
}

// DB bağlantısı
$db = getDbInstance();

// İlaç bilgilerini al (belirli bir ilaç seçilmişse)
$medication = null;
if ($medication_id) {
    $db->where('id', $medication_id);
    $medication = $db->getOne('medications');
    
    if (!$medication) {
        echo "İlaç bulunamadı!";
        exit;
    }
}

// Stok geçmişi verileri
try {
    // Ana sorgu
    $db = getDbInstance();
    
    // İlaç ID filtrelemesi
    if ($medication_id) {
        $db->where('medication_id', $medication_id);
    }
    
    // Tür filtrelemesi
    if ($filter_type) {
        $db->where('type', $filter_type);
    }
    
    // Referans filtrelemesi
    if ($filter_reference) {
        $db->where('reference_type', $filter_reference);
    }
    
    // Tarih filtrelemesi
    $db->where('DATE(created_at) BETWEEN ? AND ?', [$filter_date_start, $filter_date_end]);
    
    // Sıralama
    $db->orderBy('created_at', 'DESC');
    
    // Tüm verileri çek (sayfalama olmadan)
    $result = $db->get("stock_history");
    
    // İlaç bilgilerini stok geçmişi kayıtlarına ekle
    if (!empty($result)) {
        // İlgili tüm ilaçların ID'lerini topla
        $medication_ids = array_column($result, 'medication_id');
        $medication_ids = array_unique($medication_ids);
        
        // Bu ID'lere sahip ilaçları getir
        $db = getDbInstance();
        $db->where('id', $medication_ids, 'IN');
        $medications = $db->get('medications');
        
        // İlaçları ID'lerine göre indisle
        $medications_by_id = [];
        foreach ($medications as $med) {
            $medications_by_id[$med['id']] = $med;
        }
        
        // Her stok kaydına ilgili ilaç bilgisini ekle
        foreach ($result as &$record) {
            $med_id = $record['medication_id'];
            
            // İlgili ilaç varsa bilgilerini ekle
            if (isset($medications_by_id[$med_id])) {
                $med = $medications_by_id[$med_id];
                $record['medication_name'] = $med['name'];
                $record['unit'] = $med['unit'];
            } else {
                $record['medication_name'] = 'Bilinmeyen İlaç';
                $record['unit'] = 'birim';
            }
            
            // Kullanıcı bilgisini manuel ekle - users tablosu olmadığı için
            $record['user_name'] = 'ibosta';
        }
        unset($record); // Referans bağlantısını kaldır
    }
    
} catch (Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
    exit;
}

// İlgili verileri al (dropdown'lar için)
$types = ['add' => 'Ekleme', 'subtract' => 'Çıkarma', 'adjust' => 'Düzeltme'];
$references = ['purchase' => 'Satın Alma', 'prescription' => 'Reçete', 'transfer' => 'Transfer', 'expired' => 'Son Kullanma', 'adjustment' => 'Manuel Düzeltme', 'inventory' => 'Envanter Sayımı'];

// İstatistikler için veri hazırlama
$total_added = 0;
$total_subtracted = 0;
$total_adjusted = 0;

if ($medication_id) {
    // Belirli ilaç için toplam giriş/çıkışlar
    foreach ($result as $row) {
        if ($row['type'] == 'add') {
            $total_added += $row['quantity_change'];
        } elseif ($row['type'] == 'subtract') {
            $total_subtracted += $row['quantity_change'];
        } elseif ($row['type'] == 'adjust') {
            $total_adjusted += $row['quantity_change'];
        }
    }
}

// Sayfa başlığı
$title = $medication ? $medication['name'] . " - Stok Hareketleri" : "Tüm Stok Hareketleri";

// CSS ve HTML
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    
    <?php if ($format == 'excel'): ?>
    <!-- Excel için basit stil -->
    <style type="text/css">
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f9f9f9; }
        h1, h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #ddd; }
        .header-info { margin-bottom: 20px; text-align: right; }
        .print-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .print-title { flex-grow: 1; text-align: center; }
        .print-logo { width: 150px; }
        .print-info { width: 250px; text-align: right; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .text-center { text-align: center; }
        .btn-print { background-color: #007bff; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; margin-top: 20px; }
        
        @media print {
            .no-print { display: none; }
            body { background-color: #fff; }
        }
    </style>
    <?php else: ?>
    <!-- PDF için stil -->
    <style type="text/css">
        body { font-family: "Times New Roman", Times, serif; margin: 0; padding: 20px; background-color: white; }
        h1, h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .header-info { margin-bottom: 20px; text-align: right; }
        .print-header { margin-bottom: 30px; position: relative; height: 100px; }
        .print-title { position: absolute; width: 100%; text-align: center; top: 20px; }
        .print-logo { position: absolute; left: 0; top: 0; width: 150px; }
        .print-info { position: absolute; right: 0; top: 0; width: 250px; text-align: right; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .text-center { text-align: center; }
        .btn-print { background-color: #007bff; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; margin-top: 20px; }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button class="btn-print" onclick="window.print()">Yazdır</button>
    </div>
    
    <div class="print-header">
        <div class="print-logo">
            <img src="assets/images/clinic_logo.svg" alt="Klinik Logo" style="max-width: 100px;">
        </div>
        <div class="print-title">
            <h1><?php echo $title; ?></h1>
            <p>Tarih Aralığı: <?php echo date('d.m.Y', strtotime($filter_date_start)); ?> - <?php echo date('d.m.Y', strtotime($filter_date_end)); ?></p>
        </div>
        <div class="print-info">
            <p>Rapor Tarihi: <?php echo date('d.m.Y H:i'); ?></p>
            <p>Kullanıcı: ibosta</p>
        </div>
    </div>
    
    <?php if ($medication): ?>
    <div style="margin-bottom: 20px;">
        <table>
            <tr>
                <th colspan="2">İlaç Bilgileri</th>
            </tr>
            <tr>
                <td style="width: 30%;">İlaç Adı:</td>
                <td><?php echo htmlspecialchars($medication['name']); ?></td>
            </tr>
            <tr>
                <td>Açıklama:</td>
                <td><?php echo htmlspecialchars($medication['description']); ?></td>
            </tr>
            <tr>
                <td>Mevcut Stok:</td>
                <td><?php echo $medication['quantity']; ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
            </tr>
            <tr>
                <td>Kritik Seviye:</td>
                <td><?php echo $medication['min_stock']; ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
            </tr>
        </table>
        
        <table>
            <tr>
                <th colspan="4">Stok Özeti</th>
            </tr>
            <tr>
                <td style="width: 25%;">Toplam Giriş:</td>
                <td style="width: 25%;"><?php echo $total_added; ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
                <td style="width: 25%;">Toplam Çıkış:</td>
                <td style="width: 25%;"><?php echo $total_subtracted; ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
            </tr>
            <tr>
                <td>Toplam Düzeltme:</td>
                <td><?php echo $total_adjusted; ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
                <td>Net Değişim:</td>
                <td><?php echo ($total_added - $total_subtracted + $total_adjusted); ?> <?php echo htmlspecialchars($medication['unit']); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    
    <h2>Stok Hareketleri</h2>
    <?php if ($format == 'excel'): ?>
    <!-- Excel formatında tablo -->
    <table border="1">
        <thead>
            <tr>
                <th>#</th>
                <th>Tarih/Saat</th>
                <?php if (!$medication_id): ?>
                    <th>İlaç</th>
                <?php endif; ?>
                <th>İşlem</th>
                <th>Miktar</th>
                <th>Referans</th>
                <th>Not</th>
                <th>Kullanıcı</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $row_number = 1;
            foreach ($result as $row): 
            ?>
                <tr>
                    <td><?php echo $row_number++; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                    
                    <?php if (!$medication_id): ?>
                    <td><?php echo htmlspecialchars($row['medication_name']); ?></td>
                    <?php endif; ?>
                    
                    <td>
                        <?php 
                        if ($row['type'] == 'add') {
                            echo 'Ekleme';
                        } elseif ($row['type'] == 'subtract') {
                            echo 'Çıkarma';
                        } else {
                            echo 'Düzeltme';
                        }
                        ?>
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
                        echo '<span class="'.$class.'">'.$prefix . $row['quantity_change'] . ' ' . $row['unit'].'</span>'; 
                        ?>
                    </td>
                    
                    <td>
                        <?php
                        $ref_text = '';
                        switch($row['reference_type']) {
                            case 'purchase':
                                $ref_text = 'Satın Alma';
                                break;
                            case 'prescription':
                                $ref_text = 'Reçete #' . $row['reference_id'];
                                break;
                            case 'transfer':
                                $ref_text = 'Transfer';
                                break;
                            case 'expired':
                                $ref_text = 'Son Kullanma';
                                break;
                            case 'adjustment':
                                $ref_text = 'Manuel Düzeltme';
                                break;
                            case 'inventory':
                                $ref_text = 'Envanter Sayımı';
                                break;
                            default:
                                $ref_text = $row['reference_type'] ?: 'Bilinmiyor';
                        }
                        echo $ref_text;
                        ?>
                    </td>
                    
                    <td><?php echo htmlspecialchars($row['notes'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($result)): ?>
                <tr>
                    <td colspan="<?php echo $medication_id ? '6' : '7'; ?>" class="text-center">Kayıt bulunamadı.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php else: ?>
    <!-- PDF formatında tablo -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 12%;">Tarih/Saat</th>
                <?php if (!$medication_id): ?>
                <th style="width: 18%;">İlaç</th>
                <?php endif; ?>
                <th style="width: 10%;">İşlem</th>
                <th style="width: 10%;">Miktar</th>
                <th style="width: 15%;">Referans</th>
                <th style="width: 20%;">Not</th>
                <th style="width: 10%;">Kullanıcı</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $row_number = 1;
            foreach ($result as $row): 
            ?>
                <tr>
                    <td><?php echo $row_number++; ?></td>
                    <td><?php echo date('d.m.Y H:i', strtotime($row['created_at'])); ?></td>
                    
                    <?php if (!$medication_id): ?>
                    <td><?php echo htmlspecialchars($row['medication_name']); ?></td>
                    <?php endif; ?>
                    
                    <td>
                        <?php 
                        if ($row['type'] == 'add') {
                            echo '<span class="text-success">Ekleme</span>';
                        } elseif ($row['type'] == 'subtract') {
                            echo '<span class="text-danger">Çıkarma</span>';
                        } else {
                            echo '<span class="text-warning">Düzeltme</span>';
                        }
                        ?>
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
                        echo '<span class="'.$class.'">'.$prefix . $row['quantity_change'] . ' ' . $row['unit'].'</span>'; 
                        ?>
                    </td>
                    
                    <td>
                        <?php
                        $ref_text = '';
                        switch($row['reference_type']) {
                            case 'purchase':
                                $ref_text = 'Satın Alma';
                                break;
                            case 'prescription':
                                $ref_text = 'Reçete #' . $row['reference_id'];
                                break;
                            case 'transfer':
                                $ref_text = 'Transfer';
                                break;
                            case 'expired':
                                $ref_text = 'Son Kullanma';
                                break;
                            case 'adjustment':
                                $ref_text = 'Manuel Düzeltme';
                                break;
                            case 'inventory':
                                $ref_text = 'Envanter Sayımı';
                                break;
                            default:
                                $ref_text = $row['reference_type'] ?: 'Bilinmiyor';
                        }
                        echo $ref_text;
                        ?>
                    </td>
                    
                    <td><?php echo htmlspecialchars($row['notes'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($result)): ?>
                <tr>
                    <td colspan="<?php echo $medication_id ? '6' : '7'; ?>" class="text-center">Kayıt bulunamadı.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button class="btn-print" onclick="window.print()">Yazdır</button>
        <button class="btn-print" onclick="window.close()">Kapat</button>
    </div>
    
    <div class="print-footer" style="margin-top: 30px; text-align: center; font-size: 12px;">
        <p>©2025 Veteriner Klinik Yönetim Sistemi. Bu rapor <?php echo date('d.m.Y H:i:s'); ?> tarihinde <?php echo 'ibosta'; ?> tarafından oluşturulmuştur.</p>
    </div>
    
    <script type="text/javascript">
    // Excel görünümü için tablo verilerini indirme fonksiyonu
    <?php if ($format == 'excel'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Tarayıcı Excel'e benzer bir görünüm için basit bir JavaScript
        // Gerçek Excel dosyası indirme özelliği eklenebilir
        var tables = document.getElementsByTagName('table');
        for (var i = 0; i < tables.length; i++) {
            var table = tables[i];
            var rows = table.getElementsByTagName('tr');
            
            // Tüm satırlar için
            for (var j = 0; j < rows.length; j++) {
                var cells = rows[j].getElementsByTagName('td');
                
                // Her hücre için renklendirme
                for (var k = 0; k < cells.length; k++) {
                    cells[k].addEventListener('mouseover', function() {
                        this.style.backgroundColor = '#e3f2fd';
                    });
                    
                    cells[k].addEventListener('mouseout', function() {
                        if (this.parentNode.sectionRowIndex % 2 == 0) {
                            this.style.backgroundColor = '#f2f2f2';
                        } else {
                            this.style.backgroundColor = '';
                        }
                    });
                }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>