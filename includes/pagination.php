<?php
/**
 * Pagination Class
 * 
 * Sayfalama işlevini gerçekleştiren dosya
 */

// Geçerli sayfa
$page = !empty($_GET['page']) ? $_GET['page'] : 1;

// Son sayfa numarasını hesapla
$last_page = !empty($total_pages) ? $total_pages : ceil($total_records / $records_per_page);

// URL'den sorgu parametrelerini alma
$query_str = $_SERVER['QUERY_STRING'];

// Sayfa parametresini URL'den çıkar
$query_str = preg_replace('/&?page=[0-9]+/', '', $query_str);

// Sayfalama URL'sini oluştur
$pagination_url = !empty($pagination_url) ? $pagination_url : $_SERVER['PHP_SELF'];

// URL başlangıcına ? veya & ekle
$url_separator = (strpos($pagination_url, '?') === false) ? '?' : '&';

// Sayfalama için URL başlangıcı
$page_url = $pagination_url . $url_separator . $query_str;
$page_url = preg_replace('/&+/', '&', $page_url);
$page_url = preg_replace('/\?&/', '?', $page_url);

// Sayfalama URL'sini tamamla
$page_url .= (substr($page_url, -1) == '?' || substr($page_url, -1) == '&') ? '' : '&';
?>

<!-- Sayfalama -->
<?php if ($last_page > 1): ?>
<div class="text-center">
    <ul class="pagination">
        
        <!-- İlk sayfa linki -->
        <?php if ($page > 1): ?>
        <li><a href="<?php echo $page_url; ?>page=1" aria-label="First"><span aria-hidden="true">&laquo;&laquo;</span></a></li>
        <?php else: ?>
        <li class="disabled"><span aria-hidden="true">&laquo;&laquo;</span></li>
        <?php endif; ?>
        
        <!-- Önceki sayfa linki -->
        <?php if ($page > 1): ?>
        <li><a href="<?php echo $page_url; ?>page=<?php echo $page-1; ?>" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>
        <?php else: ?>
        <li class="disabled"><span aria-hidden="true">&laquo;</span></li>
        <?php endif; ?>
        
        <!-- Sayfa numaraları -->
        <?php
        $range = 2; // Gösterilecek sayfa aralığı
        $show_dots = false;
        
        for($i=1; $i<=$last_page; $i++) {
            // Şu anki sayfanın etrafındaki sayfaları, ilk ve son sayfaları göster
            if ($i == 1 || $i == $last_page || ($i >= $page - $range && $i <= $page + $range)) {
                if ($i == $page) {
                    echo '<li class="active"><span>' . $i . '</span></li>';
                } else {
                    echo '<li><a href="' . $page_url . 'page=' . $i . '">' . $i . '</a></li>';
                }
                $show_dots = true;
            } else if ($show_dots) {
                echo '<li class="disabled"><span>...</span></li>';
                $show_dots = false;
            }
        }
        ?>
        
        <!-- Sonraki sayfa linki -->
        <?php if ($page < $last_page): ?>
        <li><a href="<?php echo $page_url; ?>page=<?php echo $page+1; ?>" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>
        <?php else: ?>
        <li class="disabled"><span aria-hidden="true">&raquo;</span></li>
        <?php endif; ?>
        
        <!-- Son sayfa linki -->
        <?php if ($page < $last_page): ?>
        <li><a href="<?php echo $page_url; ?>page=<?php echo $last_page; ?>" aria-label="Last"><span aria-hidden="true">&raquo;&raquo;</span></a></li>
        <?php else: ?>
        <li class="disabled"><span aria-hidden="true">&raquo;&raquo;</span></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Sayfa göstergesi -->
<div class="text-center">
    <?php if ($total_records > 0): ?>
    <p>
        Toplam <?php echo $total_records; ?> kayıttan 
        <?php 
        $start_record = ($page - 1) * $records_per_page + 1;
        $end_record = min($page * $records_per_page, $total_records);
        echo $start_record . ' - ' . $end_record; 
        ?> 
        arası gösteriliyor
    </p>
    <?php else: ?>
    <p>Gösterilecek kayıt bulunamadı</p>
    <?php endif; ?>
</div>