<?php
/**
 * Flash Messages
 *
 * Kullanıcıya gösterilen başarı ve hata mesajlarını yönetir
 */
?>

<!-- Başarılı mesajları -->
<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <strong>Başarılı!</strong> <?php echo $_SESSION['success']; ?>
    <?php unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<!-- Hata mesajları -->
<?php if (isset($_SESSION['failure'])): ?>
<div class="alert alert-danger alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <strong>Hata!</strong> <?php echo $_SESSION['failure']; ?>
    <?php unset($_SESSION['failure']); ?>
</div>
<?php endif; ?>

<!-- Bilgi mesajları -->
<?php if (isset($_SESSION['info'])): ?>
<div class="alert alert-info alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <strong>Bilgi:</strong> <?php echo $_SESSION['info']; ?>
    <?php unset($_SESSION['info']); ?>
</div>
<?php endif; ?>

<!-- Uyarı mesajları -->
<?php if (isset($_SESSION['warning'])): ?>
<div class="alert alert-warning alert-dismissable">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <strong>Uyarı!</strong> <?php echo $_SESSION['warning']; ?>
    <?php unset($_SESSION['warning']); ?>
</div>
<?php endif; ?>