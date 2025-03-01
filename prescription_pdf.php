<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get prescription ID from URL parameter
$prescription_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if ID is valid
if (!$prescription_id) {
    $_SESSION['failure'] = "Geçersiz reçete ID'si.";
    header('Location: prescriptions.php');
    exit();
}

// Get DB instance
$db = getDbInstance();

// Get prescription data
$db->where('id', $prescription_id);
$prescription = $db->getOne('prescriptions');

// Check if prescription exists
if (!$prescription) {
    $_SESSION['failure'] = "Reçete bulunamadı.";
    header('Location: prescriptions.php');
    exit();
}

// Get prescription items
$db->join("medications m", "pi.medication_id = m.id", "LEFT");
$db->where('pi.prescription_id', $prescription_id);
$items = $db->get('prescription_items pi', null, 'pi.*, m.name as medication_name, m.unit');

// Get clinic information
$clinic_name = "KIRIKKALE ÜNİVERSİTESİ VETERİNER FAKÜLTESİ";
$clinic_address = "Ankara Yolu 7. Km. 71450 Yahşihan/KIRIKKALE";
$clinic_phone = "+90 318 357 33 01";
$clinic_email = "veteriner@kku.edu.tr";
$current_date = date('Y-m-d H:i:s');
$current_user = "ibosta";

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçete #<?php echo $prescription_id; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12pt;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        .clinic-name {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .prescription-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 20px 0;
            padding: 5px;
        }
        .medications {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .medications th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        .medications td {
            border: 1px solid #000;
            padding: 5px;
        }
        .footer {
            margin-top: 50px;
            text-align: right;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Yazdır</button>
        <button onclick="window.location.href='prescriptions.php'">Geri Dön</button>
    </div>

    <div class="header">
        <div class="clinic-name"><?php echo $clinic_name; ?></div>
        <div><?php echo $clinic_address; ?></div>
        <div>Tel: <?php echo $clinic_phone; ?> | E-posta: <?php echo $clinic_email; ?></div>
    </div>
    
    <div class="prescription-title">REÇETE #<?php echo $prescription_id; ?></div>
    
    <div>
        <p><strong>Tarih:</strong> <?php echo date('d.m.Y', strtotime($current_date)); ?></p>
        <p><strong>Tanı:</strong> <?php echo htmlspecialchars($prescription['diagnosis']); ?></p>
        <?php if (!empty($prescription['diagnosis_details'])): ?>
            <p><strong>Tanı Detayları:</strong> <?php echo nl2br(htmlspecialchars($prescription['diagnosis_details'])); ?></p>
        <?php endif; ?>
    </div>
    
    <div>
        <table class="medications">
            <tr>
                <th width="5%">#</th>
                <th width="30%">İlaç</th>
                <th width="15%">Doz</th>
                <th width="25%">Kullanım</th>
                <th width="25%">Süre</th>
            </tr>
            <?php if (!empty($items)): ?>
                <?php $counter = 1; foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($item['medication_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['dosage']) . ' ' . htmlspecialchars($item['unit']); ?></td>
                        <td><?php echo htmlspecialchars($item['daily_usage']); ?></td>
                        <td><?php echo htmlspecialchars($item['usage_period']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">Bu reçetede ilaç bulunmamaktadır.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if (!empty($prescription['notes'])): ?>
        <div style="margin-top: 20px;">
            <strong>Notlar:</strong><br>
            <?php echo nl2br(htmlspecialchars($prescription['notes'])); ?>
        </div>
    <?php endif; ?>

    <div class="footer">
        <div><?php echo htmlspecialchars($current_user); ?></div>
        <div>Veteriner Hekim</div>
        <div style="margin-top: 30px;">İmza: _______________________</div>
    </div>
</body>
</html>