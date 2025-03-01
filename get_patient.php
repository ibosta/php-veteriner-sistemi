<?php
session_start();
require_once './config/config.php';
require_once 'includes/auth_validate.php';

// Get patient ID from URL parameter
$patient_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Check if ID is valid
if (!$patient_id) {
    echo '<div class="alert alert-danger">Geçersiz hasta ID\'si</div>';
    exit();
}

// Get DB instance and fetch patient data
$db = getDbInstance();
$db->where('id', $patient_id);
$patient = $db->getOne('patients');

// Check if patient exists
if (!$patient) {
    echo '<div class="alert alert-danger">Hasta bulunamadı.</div>';
    exit();
}
?>

<input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">

<div class="row">
    <div class="col-md-12">
        <h4>Hasta Bilgileri</h4>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="name">Hasta Adı *</label>
            <input type="text" name="name" id="edit_name" class="form-control" required maxlength="100" value="<?php echo htmlspecialchars($patient['name']); ?>">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="type">Tür *</label>
            <select name="type" id="edit_type" class="form-control" required>
                <option value="">Seçiniz...</option>
                <option value="Kedi" <?php if ($patient['type'] == 'Kedi') echo 'selected'; ?>>Kedi</option>
                <option value="Köpek" <?php if ($patient['type'] == 'Köpek') echo 'selected'; ?>>Köpek</option>
                <option value="Kuş" <?php if ($patient['type'] == 'Kuş') echo 'selected'; ?>>Kuş</option>
                <option value="Kemirgen" <?php if ($patient['type'] == 'Kemirgen') echo 'selected'; ?>>Kemirgen</option>
                <option value="Balık" <?php if ($patient['type'] == 'Balık') echo 'selected'; ?>>Balık</option>
                <option value="Sürüngen" <?php if ($patient['type'] == 'Sürüngen') echo 'selected'; ?>>Sürüngen</option>
                <option value="Diğer" <?php if ($patient['type'] == 'Diğer') echo 'selected'; ?>>Diğer</option>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="breed">Irk</label>
            <input type="text" name="breed" id="edit_breed" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($patient['breed'] ?? ''); ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="age">Yaş (ay)</label>
            <input type="number" name="age" id="edit_age" class="form-control" min="0" value="<?php echo $patient['age'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="gender">Cinsiyet</label>
            <select name="gender" id="edit_gender" class="form-control">
                <option value="">Seçiniz...</option>
                <option value="erkek" <?php if ($patient['gender'] == 'erkek') echo 'selected'; ?>>Erkek</option>
                <option value="dişi" <?php if ($patient['gender'] == 'dişi') echo 'selected'; ?>>Dişi</option>
            </select>
        </div>
    </div>
    
    <div class="col-md-12">
        <h4>Sahip Bilgileri</h4>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="owner_name">Sahibinin Adı *</label>
            <input type="text" name="owner_name" id="edit_owner_name" class="form-control" required maxlength="100" value="<?php echo htmlspecialchars($patient['owner_name']); ?>">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="owner_phone">Sahibinin Telefonu *</label>
            <input type="text" name="owner_phone" id="edit_owner_phone" class="form-control" required maxlength="20" value="<?php echo htmlspecialchars($patient['owner_phone']); ?>">
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="owner_email">Sahibinin E-Postası</label>
            <input type="email" name="owner_email" id="edit_owner_email" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($patient['owner_email'] ?? ''); ?>">
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <label for="owner_address">Sahibinin Adresi</label>
            <textarea name="owner_address" id="edit_owner_address" class="form-control" rows="2"><?php echo htmlspecialchars($patient['owner_address'] ?? ''); ?></textarea>
        </div>
    </div>
    
    <div class="col-md-12">
        <div class="form-group">
            <label for="notes">Notlar</label>
            <textarea name="notes" id="edit_notes" class="form-control" rows="3"><?php echo htmlspecialchars($patient['notes'] ?? ''); ?></textarea>
        </div>
    </div>
</div>