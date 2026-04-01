<?php
$sayfa = "İşlem Geçmişi";
session_start();
include("baglan.php");
require_once("helper_functions.php");

// Oturum kontrolü
if (!isset($_SESSION["oturum"])) {
    header("Location: login.php");
    exit;
}

// Modül bazlı yetki kontrolü
sayfaErisimKontrol($baglanti);

// Arama ve Filtreleme
$search = isset($_GET['search']) ? mysqli_real_escape_string($baglanti, $_GET['search']) : '';
$filter_module = isset($_GET['module']) ? mysqli_real_escape_string($baglanti, $_GET['module']) : '';
$filter_action = isset($_GET['action']) ? mysqli_real_escape_string($baglanti, $_GET['action']) : '';

// Logları çek
$sql = "SELECT l.*, u.kadi as user_name 
        FROM system_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE 1=1";

if ($search) {
    $sql .= " AND (l.description LIKE '%$search%' OR u.kadi LIKE '%$search%' OR l.ip_address LIKE '%$search%')";
}

if ($filter_module) {
    $sql .= " AND l.module = '$filter_module'";
}

if ($filter_action) {
    $sql .= " AND l.action_type = '$filter_action'";
}

$sql .= " ORDER BY l.created_at DESC LIMIT 100";

$result = $baglanti->query($sql);
$logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Mevcut modülleri çek
$modules_result = $baglanti->query("SELECT DISTINCT module FROM system_logs ORDER BY module");
$modules = [];
if ($modules_result) {
    while ($m = $modules_result->fetch_assoc()) {
        $modules[] = $m['module'];
    }
}

// Mevcut işlem tiplerini çek
$actions_result = $baglanti->query("SELECT DISTINCT action_type FROM system_logs ORDER BY action_type");
$actions = [];
if ($actions_result) {
    while ($a = $actions_result->fetch_assoc()) {
        $actions[] = $a['action_type'];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İşlem Geçmişi - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-light">
    <?php include("navbar.php"); ?>

    <div class="container-fluid py-4">
        <!-- Başlık -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-history text-warning me-2"></i>Sistem İşlem Geçmişi</h2>
                <p class="text-muted small mb-0">Tüm kritik sistem hareketlerinin kayıtları</p>
            </div>
            <div>
                <button class="btn btn-outline-primary btn-sm" onclick="location.reload();">
                    <i class="fas fa-sync-alt me-1"></i> Yenile
                </button>
            </div>
        </div>

        <!-- Arama ve Filtreleme -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Arama</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Kullanıcı, açıklama veya IP ara..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">Modül</label>
                        <select name="module" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($modules as $mod): ?>
                                <option value="<?php echo $mod; ?>" <?php if ($filter_module == $mod)
                                       echo 'selected'; ?>>
                                    <?php echo strtoupper($mod); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">İşlem Tipi</label>
                        <select name="action" class="form-select">
                            <option value="">Tümü</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo $act; ?>" <?php if ($filter_action == $act)
                                       echo 'selected'; ?>>
                                    <?php echo $act; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Filtrele
                        </button>
                        <a href="islem_gecmisi.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row mb-4 g-2">
            <div class="col">
                <div class="card text-white bg-primary shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <h6 class="card-title mb-0 small">Toplam</h6>
                            <h4 class="mb-0"><?php echo count($logs); ?></h4>
                        </div>
                        <i class="fas fa-list-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-success shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <h6 class="card-title mb-0 small">Giriş</h6>
                            <h4 class="mb-0">
                                <?php echo count(array_filter($logs, fn($l) => $l['action_type'] == 'LOGIN')); ?>
                            </h4>
                        </div>
                        <i class="fas fa-sign-in-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-secondary shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <h6 class="card-title mb-0 small">Çıkış</h6>
                            <h4 class="mb-0">
                                <?php echo count(array_filter($logs, fn($l) => $l['action_type'] == 'LOGOUT')); ?>
                            </h4>
                        </div>
                        <i class="fas fa-sign-out-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-warning shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <h6 class="card-title mb-0 small">Onay</h6>
                            <h4 class="mb-0">
                                <?php echo count(array_filter($logs, fn($l) => $l['action_type'] == 'APPROVAL')); ?>
                            </h4>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-info shadow-sm h-100">
                    <div class="card-body d-flex justify-content-between align-items-center py-2">
                        <div>
                            <h6 class="card-title mb-0 small">Veri İşlemi</h6>
                            <h4 class="mb-0">
                                <?php echo count(array_filter($logs, fn($l) => in_array($l['action_type'], ['INSERT', 'UPDATE', 'DELETE']))); ?>
                            </h4>
                        </div>
                        <i class="fas fa-database fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablo -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#ID</th>
                                <th>Tarih & Saat</th>
                                <th>Kullanıcı</th>
                                <th>Modül</th>
                                <th>İşlem</th>
                                <th style="width: 35%;">Açıklama</th>
                                <th class="text-end pe-4">IP Adresi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-search fa-3x mb-3 d-block opacity-25"></i>
                                        <h5>Kayıt Bulunamadı</h5>
                                        <p class="small">Arama kriterlerinize uygun kayıt bulunamamıştır.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-secondary">#<?php echo $log['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <i class="far fa-calendar text-primary me-1"></i>
                                                <?php echo date('d.m.Y', strtotime($log['created_at'])); ?>
                                            </div>
                                            <div class="text-muted smaller">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-small">
                                                    <?php echo strtoupper(substr($log['user_name'] ?? '?', 0, 1)); ?>
                                                </div>
                                                <span
                                                    class="fw-medium"><?php echo htmlspecialchars($log['user_name'] ?? 'Bilinmiyor'); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">
                                                <?php echo strtoupper($log['module']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = match ($log['action_type']) {
                                                'INSERT' => 'bg-success',
                                                'UPDATE' => 'bg-primary',
                                                'DELETE' => 'bg-danger',
                                                'LOGIN' => 'bg-info',
                                                'LOGOUT' => 'bg-secondary',
                                                'APPROVAL' => 'bg-warning text-dark',
                                                'REJECT' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };

                                            $icon_class = match ($log['action_type']) {
                                                'INSERT' => 'fa-plus-circle',
                                                'UPDATE' => 'fa-edit',
                                                'DELETE' => 'fa-trash',
                                                'LOGIN' => 'fa-sign-in-alt',
                                                'LOGOUT' => 'fa-sign-out-alt',
                                                'APPROVAL' => 'fa-check-circle',
                                                'REJECT' => 'fa-times-circle',
                                                default => 'fa-info-circle'
                                            };
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <i class="fas <?php echo $icon_class; ?> me-1"></i>
                                                <?php echo $log['action_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"
                                                title="<?php echo htmlspecialchars($log['description']); ?>">
                                                <?php echo mb_substr(htmlspecialchars($log['description']), 0, 60) . (mb_strlen($log['description']) > 60 ? '...' : ''); ?>
                                            </small>
                                        </td>
                                        <td class="text-end pe-4">
                                            <code class="small text-muted"><?php echo $log['ip_address']; ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light text-muted small text-center">
                Son 100 işlem kaydı gösteriliyor
            </div>
        </div>
    </div>

    <style>
        .user-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75rem;
        }

        .smaller {
            font-size: 0.75rem;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }

        /* İstatistik kartları hover animasyonu */
        .row.g-2 .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .row.g-2 .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
