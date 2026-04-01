<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("baglan.php");

if (!isset($_SESSION["oturum"])) { 
    header("Location: login.php"); 
    exit; 
}

$limit = 200;
$sql = "SELECT * FROM kantar_okumalari ORDER BY id DESC LIMIT $limit";
$sonuclar = $baglanti->query($sql);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kantar Geçmişi - Özbal Un</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background: #f1f5f9 !important; 
        }
        .page-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #fff; 
            border-radius: 1.25rem; 
            margin-top: 1.25rem; 
            margin-bottom: 1.4rem; 
            padding: 1.55rem 1.7rem; 
            box-shadow: 0 16px 28px -14px rgba(15,23,42,.55); 
            position: relative; 
            overflow: hidden; 
        }
        .page-header::before { 
            content: ""; 
            position: absolute; 
            top: -65%; 
            right: -10%; 
            width: 440px; 
            height: 440px; 
            border-radius: 50%; 
            background: radial-gradient(circle, rgba(245,158,11,.2) 0%, rgba(245,158,11,0) 72%); 
            pointer-events: none; 
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,.04);
        }
        .table-custom {
            font-size: 0.9rem;
        }
        .table-custom thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 15px;
        }
        .table-custom tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .weight-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .plate-badge {
            background: #fcf8e3;
            color: #8a6d3b;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            letter-spacing: 1px;
            border: 1px solid #faebcc;
        }
    </style>
</head>
<body>
    <?php include("navbar.php"); ?>
    
    <div class="container-fluid px-md-4 pb-4" style="max-width: 1680px; margin: 0 auto">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="fw-bold mb-1"><i class="fas fa-weight-scale me-2"></i>Kantar Geçmişi (Loglar)</h2>
                    <p class="mb-0" style="color: rgba(255,255,255,.78)">Kantar cihazından arka planda otomatik okunan tartım kayıtları (Son <?php echo $limit; ?> Kayıt)</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-light" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Yenile
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover table-custom w-100" id="kantarTable">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Plaka</th>
                                <th>Tartım Zamanı</th>
                                <th>Brüt</th>
                                <th>Dara</th>
                                <th>Net</th>
                                <th>Firma / Tedarikçi</th>
                                <th>Sürücü</th>
                                <th>Kayıt Zamanı (Sistem)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($sonuclar && $sonuclar->num_rows > 0) {
                                while($row = $sonuclar->fetch_assoc()) {
                                    $t_zaman = $row['tartim_zamani'] ? date('d.m.Y H:i', strtotime($row['tartim_zamani'])) : '-';
                                    $k_zaman = $row['cekim_zamani'] ? date('d.m.Y H:i:s', strtotime($row['cekim_zamani'])) : '-';
                                    
                                    echo "<tr>";
                                    echo "<td>{$row['id']}</td>";
                                    echo "<td><span class='plate-badge'>" . htmlspecialchars($row['plaka_norm']) . "</span></td>";
                                    echo "<td>{$t_zaman}</td>";
                                    echo "<td>" . number_format($row['brut_kg'], 0, ',', '.') . " kg</td>";
                                    echo "<td>" . number_format($row['tara_kg'], 0, ',', '.') . " kg</td>";
                                    echo "<td><span class='weight-badge'>" . number_format($row['net_kg'], 0, ',', '.') . " kg</span></td>";
                                    echo "<td>" . htmlspecialchars($row['firma']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['surucu']??'-') . "</td>";
                                    echo "<td class='text-muted' style='font-size:0.8rem;'>{$k_zaman}</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#kantarTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
                },
                "order": [[ 0, "desc" ]],
                "pageLength": 50
            });
        });
    </script>
</body>
</html>
