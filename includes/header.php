<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Veteriner Reçete Sistemi">
        <meta name="author" content="KIRIKKALE ÜNİVERSİTESİ">
<!-- Favicon için Base64 kodlanmış pati ikonu -->
        <link rel="icon" type="image/png" href="/assets/images/favicon.png">
        <title>Veteriner Reçete Sistemi</title>

        <!-- Bootstrap Core CSS -->
        <link rel="stylesheet" href="assets/css/bootstrap.min.css"/>
        <!-- MetisMenu CSS -->
        <link href="assets/js/metisMenu/metisMenu.min.css" rel="stylesheet">
        <!-- Custom CSS -->
        <link href="assets/css/sb-admin-2.css" rel="stylesheet">
        <!-- Custom Fonts -->
        <link href="assets/fonts/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css">
        
        <script src="assets/js/jquery.min.js" type="text/javascript"></script>
    </head>

    <body>
        <div id="wrapper">
            <!-- Navigation -->
            <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] == true): ?>
                <nav class="navbar navbar-default navbar-static-top" role="navigation" style="margin-bottom: 0">
                    <div class="navbar-header">
                        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                            <span class="sr-only">Menu</span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                            <span class="icon-bar"></span>
                        </button>
                        <a class="navbar-brand" href="index.php">Veteriner Reçete Sistemi</a>
                    </div>
                    <!-- /.navbar-header -->

                    <ul class="nav navbar-top-links navbar-right">
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                                <i class="fa fa-user fa-fw"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?> <i class="fa fa-caret-down"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-user">
                                <li><a href="profile.php"><i class="fa fa-user fa-fw"></i> Profilim</a></li>
                                <li><a href="settings.php"><i class="fa fa-gear fa-fw"></i> Ayarlar</a></li>
                                <li class="divider"></li>
                                <li><a href="logout.php"><i class="fa fa-sign-out fa-fw"></i> Çıkış</a></li>
                            </ul>
                        </li>
                    </ul>

                    <div class="navbar-default sidebar" role="navigation">
                        <div class="sidebar-nav navbar-collapse">
                            <ul class="nav" id="side-menu">
                                <li>
                                    <a href="index.php"><i class="fa fa-dashboard fa-fw"></i> Ana Sayfa</a>
                                </li>

                                <li <?php echo (CURRENT_PAGE == "patients.php" || CURRENT_PAGE == "add_patient.php" || CURRENT_PAGE == "edit_patient.php" || CURRENT_PAGE == "patient_details.php") ? 'class="active"' : ''; ?>>
                                    <a href="#"><i class="fa fa-paw fa-fw"></i> Hastalar<span class="fa arrow"></span></a>
                                    <ul class="nav nav-second-level">
                                        <li><a href="patients.php"><i class="fa fa-list fa-fw"></i> Hasta Listesi</a></li>
                                        <li><a href="add_patient.php"><i class="fa fa-plus fa-fw"></i> Yeni Hasta</a></li>
                                    </ul>
                                </li>

                                <li <?php echo (CURRENT_PAGE == "prescriptions.php" || CURRENT_PAGE == "add_prescription.php" || CURRENT_PAGE == "edit_prescription.php" || CURRENT_PAGE == "prescription_details.php" || CURRENT_PAGE == "prescription_pdf.php") ? 'class="active"' : ''; ?>>
                                    <a href="#"><i class="fa fa-file-text-o fa-fw"></i> Reçeteler<span class="fa arrow"></span></a>
                                    <ul class="nav nav-second-level">
                                        <li><a href="prescriptions.php"><i class="fa fa-list fa-fw"></i> Reçete Listesi</a></li>
                                        <li><a href="add_prescription.php"><i class="fa fa-plus fa-fw"></i> Yeni Reçete</a></li>
                                    </ul>
                                </li>

                                <li <?php echo (CURRENT_PAGE == "medications.php" || CURRENT_PAGE == "add_medication.php" || CURRENT_PAGE == "edit_medication.php") ? 'class="active"' : ''; ?>>
                                    <a href="#"><i class="fa fa-medkit fa-fw"></i> İlaçlar<span class="fa arrow"></span></a>
                                    <ul class="nav nav-second-level">
                                        <li><a href="medications.php"><i class="fa fa-list fa-fw"></i> İlaç Listesi</a></li>
                                        <li><a href="add_medication.php"><i class="fa fa-plus fa-fw"></i> Yeni İlaç</a></li>
                                    </ul>
                                </li>

                                <li <?php echo (CURRENT_PAGE == "stock_list.php" || CURRENT_PAGE == "stock_adjustment.php" || CURRENT_PAGE == "stock_history.php" || CURRENT_PAGE == "add_stock.php" || CURRENT_PAGE == "add_stock_entry.php" || CURRENT_PAGE == "edit_stock.php" || CURRENT_PAGE == "stock_report.php" || CURRENT_PAGE == "stock_history_print.php") ? 'class="active"' : ''; ?>>
                                    <a href="#"><i class="fa fa-cubes fa-fw"></i> Stok Yönetimi<span class="fa arrow"></span></a>
                                    <ul class="nav nav-second-level">
                                        <li><a href="stock_list.php"><i class="fa fa-list fa-fw"></i> Stok Listesi</a></li>
                                        <li><a href="stock_adjustment.php"><i class="fa fa-edit fa-fw"></i> Stok Düzenle</a></li>
                                        <li><a href="stock_history.php"><i class="fa fa-history fa-fw"></i> Stok Geçmişi</a></li>
                                        <li><a href="stock_report.php"><i class="fa fa-bar-chart fa-fw"></i> Stok Raporu</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            <?php endif;?>