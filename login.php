<?php
session_start();
require_once './config/config.php';
//If User has already logged in, redirect to home page.
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === TRUE) {
    header('Location:index.php');
}

//If user has previously selected "remember me option": 
if (isset($_COOKIE['series_id']) && isset($_COOKIE['remember_token'])) {
    $series_id = filter_var($_COOKIE['series_id']);
    $remember_token = filter_var($_COOKIE['remember_token']);
    $db = getDbInstance();
    $db->where('series_id', $series_id);
    $db->where('remember_token', $remember_token);
    $row = $db->getOne('admin_users');
    
    if ($row) {
        $_SESSION['user_logged_in'] = TRUE;
        $_SESSION['admin_type'] = $row['admin_type'];
        $_SESSION['user_name'] = $row['user_name'];
        header('Location:index.php');
        exit;
    }
}

include_once 'includes/header.php';
?>

<div id="page-" class="col-md-4 col-md-offset-4">
    <form class="form loginform" method="POST" action="authenticate.php">
        <div class="login-panel panel panel-default">
            <div class="panel-heading">Veteriner Reçete Sistemi</div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="control-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required="required">
                </div>
                <div class="form-group">
                    <label class="control-label">Şifre</label>
                    <input type="password" name="passwd" class="form-control" required="required">
                </div>
                <div class="checkbox">
                    <label>
                        <input name="remember" type="checkbox" value="1">Beni Hatırla
                    </label>
                </div>
                <?php
                if (isset($_SESSION['login_failure'])) {
                    echo '<div class="alert alert-danger alert-dismissable fade in">';
                    echo '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                    echo $_SESSION['login_failure'];
                    echo '</div>';
                    unset($_SESSION['login_failure']);
                }
                ?>
                <button type="submit" class="btn btn-success btn-block">Giriş Yap</button>
            </div>
            <div class="panel-footer">
                <div class="text-center">
                    <p class="hackathon-info">
                        Bu sistem Hayvan Sağlığı Teknolojileri Hackathonu Ön Yarışması için tasarlanmıştır.<br>
                        <span class="text-info">
                            <strong>Demo Giriş Bilgileri:</strong><br>
                            Kullanıcı Adı: kkuhackathon25<br>
                            Şifre: admin
                        </span>
                    </p>
                    <hr style="margin: 10px 0;">
                    <p><small>Son Güncelleme: <?php echo date('d.m.Y H:i', strtotime('2025-03-01 02:11:47')); ?></small></p>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Login sayfası için özel stil -->
<style>
.loginform {
    margin-top: 50px;
}
.login-panel {
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.panel-heading {
    font-size: 18px;
    font-weight: bold;
    text-align: center;
    padding: 15px;
    background: #337ab7 !important;
    color: white !important;
    border-radius: 4px 4px 0 0 !important;
}
.panel-footer {
    background: #f8f9fa;
    border-radius: 0 0 4px 4px;
    padding: 15px;
}
.btn-success {
    margin-top: 10px;
}
.form-control {
    height: 40px;
}
.alert {
    margin-top: 10px;
    margin-bottom: 0;
}
.hackathon-info {
    font-size: 13px;
    color: #666;
    margin: 10px 0;
}
.text-info {
    display: block;
    margin: 10px 0;
    padding: 10px;
    background: #f0f9ff;
    border-radius: 4px;
}
hr {
    border-top: 1px solid #ddd;
}
</style>

<?php include_once 'includes/footer.php'; ?>