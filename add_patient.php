<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get DB instance
$db = getDbInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_to_db = array(
        'name' => $_POST['name'],
        'type' => $_POST['type'],
        'breed' => $_POST['breed'],
        'age' => $_POST['age'],
        'gender' => $_POST['gender'],
        'owner_name' => $_POST['owner_name'],
        'owner_phone' => $_POST['owner_phone'],
        'notes' => $_POST['notes'],
        'created_at' => '2025-03-01 01:44:02',
        'user_id' => $_SESSION['user_id']
    );
    
    $id = $db->insert('patients', $data_to_db);
    
    if ($id) {
        $_SESSION['success'] = "Hasta başarıyla eklendi";
        header('Location: patients.php');
        exit();
    } else {
        $_SESSION['failure'] = "Hasta eklenirken hata oluştu";
    }
}

include_once 'includes/header.php';
?>

<div id="page-wrapper">
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">Yeni Hasta Ekle</h1>
        </div>
    </div>
    
    <?php include_once 'includes/flash_messages.php'; ?>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    Hasta Bilgileri
                </div>
                <div class="panel-body">
                    <form method="post" action="">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Hasta Adı *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Hayvan Türü *</label>
                                    <select name="type" class="form-control" required>
                                        <option value="">Seçiniz...</option>
                                        <option value="Kedi">Kedi</option>
                                        <option value="Köpek">Köpek</option>
                                        <option value="Kuş">Kuş</option>
                                        <option value="At">At</option>
                                        <option value="Sığır">Sığır</option>
                                        <option value="Koyun">Koyun</option>
                                        <option value="Keçi">Keçi</option>
                                        <option value="Diğer">Diğer</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Irk</label>
                                    <input type="text" name="breed" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Yaş (Ay)</label>
                                    <input type="number" name="age" class="form-control">
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Cinsiyet</label>
                                    <select name="gender" class="form-control">
                                        <option value="">Seçiniz...</option>
                                        <option value="Erkek">Erkek</option>
                                        <option value="Dişi">Dişi</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Sahip Adı *</label>
                                    <input type="text" name="owner_name" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Telefon *</label>
                                    <input type="text" name="owner_phone" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Notlar</label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-lg-12">
                                <hr>
                                <div class="form-group text-center">
                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                    <a href="patients.php" class="btn btn-default">İptal</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>