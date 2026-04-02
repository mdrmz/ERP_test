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

function fixTurkceMangle($str)
{
    if (!$str)
        return $str;
    $replacements = [
        "\x30\x01" => "İ",
        "\x31\x01" => "ı",
        "\x5e\x01" => "Ş",
        "\x5f\x01" => "ş",
        "\x1e\x01" => "Ğ",
        "\x1f\x01" => "ğ"
    ];
    return strtr($str, $replacements);
}

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
            box-shadow: 0 16px 28px -14px rgba(15, 23, 42, .55);
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
            background: radial-gradient(circle, rgba(245, 158, 11, .2) 0%, rgba(245, 158, 11, 0) 72%);
            pointer-events: none;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .04);
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
                    <p class="mb-0" style="color: rgba(255,255,255,.78)">Kantar cihazından arka planda otomatik okunan
                        tartım kayıtları (Son <?php echo $limit; ?> Kayıt)</p>
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
                                <th>Giriş Zamanı</th>
                                <th>Çıkış Zamanı</th>
                                <th>Net</th>
                                <th>Malzeme</th>
                                <th>Firma / Tedarikçi</th>
                                <th>Sürücü</th>
                                <th class="text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($sonuclar && $sonuclar->num_rows > 0) {
                                while ($row = $sonuclar->fetch_assoc()) {
                                    $row['firma'] = fixTurkceMangle($row['firma']);
                                    $row['surucu'] = fixTurkceMangle($row['surucu']);
                                    $row['plaka_norm'] = fixTurkceMangle($row['plaka_norm']);
                                    $row['urun'] = fixTurkceMangle($row['urun']);

                                    $ham_veri = $row['ham_veri'] ?? '';
                                    // Remove any leftover #* or *# or newlines just in case
                                    $hamVeriClean = trim($ham_veri, "#*\t\n\r ");
                                    $parts = explode('*', $hamVeriClean);

                                    $plaka_ham = $parts[0] ?? $row['plaka_raw'];
                                    $plakalar = explode('-', $plaka_ham);
                                    $plaka_arac = $plakalar[0] ?? '';
                                    $plaka_dorse = $plakalar[1] ?? '';

                                    $cikis_tarihi = $parts[1] ?? '';
                                    $cikis_saati = $parts[2] ?? '';
                                    $giris_tarihi = $parts[4] ?? '';
                                    $giris_saati = $parts[5] ?? '';

                                    $tartim_1 = $parts[7] ?? number_format($row['tara_kg'], 0, '', '');
                                    $tartim_2 = $parts[6] ?? number_format($row['brut_kg'], 0, '', '');

                                    $urun = $parts[9] ?? $row['urun'];
                                    $gelis_yeri = $parts[11] ?? $row['kaynak_il'];
                                    $gidis_yeri = $parts[12] ?? $row['hedef_il'];

                                    $telefon = isset($parts[15]) ? trim($parts[15]) : '';
                                    if (empty($telefon) && isset($parts[14])) {
                                        $telefon = trim($parts[14]); // Fallback in case length differs
                                    }

                                    $t_zaman = $row['tartim_zamani'] ? date('d.m.Y H:i', strtotime($row['tartim_zamani'])) : '-';

                                    $ticketData = [
                                        'id' => $row['id'],
                                        'plaka_arac' => fixTurkceMangle($plaka_arac),
                                        'plaka_dorse' => fixTurkceMangle($plaka_dorse),
                                        'giris_tarihi' => $giris_tarihi,
                                        'giris_saati' => $giris_saati,
                                        'cikis_tarihi' => $cikis_tarihi,
                                        'cikis_saati' => $cikis_saati,
                                        'tartim_1' => intval($tartim_1),
                                        'tartim_2' => intval($tartim_2),
                                        'net' => $row['net_kg'],
                                        'firma' => $row['firma'],
                                        'urun' => fixTurkceMangle($urun),
                                        'gelis_yeri' => fixTurkceMangle($gelis_yeri),
                                        'gidis_yeri' => fixTurkceMangle($gidis_yeri),
                                        'surucu' => $row['surucu'] ?? '-',
                                        'telefon' => $telefon,
                                        'tartim_zamani' => $t_zaman // fallback
                                    ];
                                    $ticketJson = htmlspecialchars(json_encode($ticketData), ENT_QUOTES, 'UTF-8');

                                    // Display strings
                                    $displayGiris = ($giris_tarihi || $giris_saati) ? trim($giris_tarihi . ' ' . $giris_saati) : '-';
                                    $displayCikis = ($cikis_tarihi || $cikis_saati) ? trim($cikis_tarihi . ' ' . $cikis_saati) : '-';

                                    echo "<tr>";
                                    echo "<td>{$row['id']}</td>";
                                    echo "<td><span class='plate-badge'>" . htmlspecialchars($plaka_arac) . ($plaka_dorse ? " - " . htmlspecialchars($plaka_dorse) : "") . "</span></td>";
                                    echo "<td><div style='font-size: 0.85rem;' title='1. Tartım (Giriş)'><i class='fas fa-sign-in-alt me-1 text-success'></i>{$displayGiris}</div></td>";
                                    echo "<td><div style='font-size: 0.85rem;' title='2. Tartım (Çıkış)'><i class='fas fa-sign-out-alt me-1 text-danger'></i>{$displayCikis}</div></td>";
                                    echo "<td><span class='weight-badge'>" . number_format($row['net_kg'], 0, ',', '.') . " kg</span></td>";
                                    echo "<td><span style='background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 500; color: #475569;'>" . htmlspecialchars(fixTurkceMangle($urun) ?: '-') . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['firma']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['surucu'] ?? '-') . "</td>";
                                    echo "<td class='text-center'>
                                              <button class='btn btn-sm btn-dark text-nowrap' onclick='printTicket(this)' data-ticket='{$ticketJson}'>
                                                  <i class='fas fa-print me-1'></i> Fiş Çıkart
                                              </button>
                                          </td>";
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
        $(document).ready(function () {
            $('#kantarTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json"
                },
                "order": [[0, "desc"]],
                "pageLength": 50
            });
        });

        function printTicket(btn) {
            const data = JSON.parse(btn.getAttribute('data-ticket'));
            const printWindow = window.open('', '_blank', 'width=800,height=600');

            const html = `
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <title>Kantar Fişi - #${data.id}</title>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
                    @page {
                        size: A5 landscape;
                        margin: 8mm 10mm;
                    }
                    body {
                        font-family: 'Inter', sans-serif;
                        color: #1e293b;
                        margin: 0;
                        padding: 0;
                        background: #f8fafc;
                        box-sizing: border-box;
                    }
                    .ticket-container {
                        width: 100%;
                        background: #fff;
                        border: 1px solid #cbd5e1;
                        border-radius: 8px;
                        padding: 12px 18px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        box-sizing: border-box;
                        display: flex;
                        flex-direction: column;
                    }
                    .ticket-header {
                        text-align: center;
                        border-bottom: 1px dashed #cbd5e1;
                        padding-bottom: 8px;
                        margin-bottom: 8px;
                    }
                    .ticket-header img {
                        max-width: 100px;
                        margin-bottom: 4px;
                    }
                    .ticket-header .company-name {
                        font-size: 14px;
                        font-weight: 700;
                        color: #0f172a;
                        margin-bottom: 2px;
                    }
                    .ticket-header .company-address {
                        font-size: 10px;
                        color: #475569;
                    }
                    .ticket-title {
                        font-size: 14px;
                        font-weight: 700;
                        color: #0f172a;
                        margin: 6px 0 0 0;
                        letter-spacing: 1px;
                        text-transform: uppercase;
                    }
                    .content-wrapper {
                        flex-grow: 1;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                    }
                    .info-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 6px;
                    }
                    .info-box {
                        background: #f8fafc;
                        padding: 5px 8px;
                        border-radius: 6px;
                        border: 1px solid #e2e8f0;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    .info-label {
                        font-size: 10px;
                        color: #64748b;
                        text-transform: uppercase;
                        font-weight: 600;
                    }
                    .info-value {
                        font-size: 12px;
                        font-weight: 600;
                        color: #0f172a;
                        text-align: right;
                    }
                    .plate-badge {
                        display: inline-block;
                        background: #fcf8e3;
                        color: #8a6d3b;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-weight: 700;
                        letter-spacing: 1px;
                        border: 1px solid #faebcc;
                        font-size: 11px;
                    }
                    .weights-container {
                        display: flex;
                        justify-content: space-between;
                        background: #f0f9ff;
                        border: 1px solid #bae6fd;
                        border-radius: 8px;
                        padding: 6px 15px;
                        margin: 8px 0;
                    }
                    .weight-item {
                        text-align: center;
                    }
                    .weight-item.net .weight-value {
                        color: #0284c7;
                        font-size: 16px;
                    }
                    .weight-label {
                        font-size: 10px;
                        color: #0369a1;
                        text-transform: uppercase;
                        font-weight: 600;
                        margin-bottom: 2px;
                    }
                    .weight-value {
                        font-size: 14px;
                        font-weight: 700;
                        color: #0f172a;
                    }
                    .signatures {
                        display: flex;
                        justify-content: space-between;
                        margin-top: 6px;
                        padding: 0 30px;
                    }
                    .signature-box {
                        text-align: center;
                        width: 35%;
                    }
                    .signature-line {
                        border-top: 1px solid #94a3b8;
                        margin-top: 22px;
                        padding-top: 4px;
                        font-size: 11px;
                        color: #475569;
                        font-weight: 600;
                    }
                    .footer {
                        text-align: center;
                        margin-top: 6px;
                        font-size: 9px;
                        color: #94a3b8;
                        padding-top: 4px;
                        border-top: 1px dashed #e2e8f0;
                    }
                    @media print {
                        body { 
                            padding: 0; 
                            background: #fff;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .ticket-container {
                            border: 1px solid #cbd5e1;
                            box-shadow: none;
                            width: 100%;
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="ticket-container">
                    <div class="ticket-header">
                        <img src="https://ozbalun.com/images/logo.svg" alt="Özbal Un Logo" onerror="this.style.display='none'">
                        <div class="company-name">PAK GIDA (ÖZBAL UN)</div>
                        <div class="company-address">BAŞPINAR 5. OSB 83542 NOLU CADDE NO:3 GAZİANTEP</div>
                        <div class="company-address">TEL: 0342 225 28 25</div>
                        <h1 class="ticket-title">Kantar Fişi</h1>
                    </div>
                    
                    <div class="content-wrapper">
                        <div class="info-grid">
                            <div class="info-box">
                                <div class="info-label">Fiş No</div>
                                <div class="info-value">#${data.id}</div>
                            </div>
                            <div class="info-box">
                                <div class="info-label">E-İrsaliye No</div>
                                <div class="info-value"></div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Malzeme</div>
                                <div class="info-value">${data.urun || '-'}</div>
                            </div>
                            <div class="info-box">
                                <div class="info-label">Firma / Tedarikçi</div>
                                <div class="info-value">${data.firma || '-'}</div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Geldiği Yer</div>
                                <div class="info-value">${data.gelis_yeri || '-'}</div>
                            </div>
                            <div class="info-box">
                                <div class="info-label">Gittiği Yer</div>
                                <div class="info-value">${data.gidis_yeri || '-'}</div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Giriş Zamanı</div>
                                <div class="info-value">${data.giris_tarihi || '-'} ${data.giris_saati || ''}</div>
                            </div>
                            <div class="info-box">
                                <div class="info-label">Çıkış Zamanı</div>
                                <div class="info-value">${data.cikis_tarihi || '-'} ${data.cikis_saati || ''}</div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">Araç Plakası</div>
                                <div class="info-value"><span class="plate-badge">${data.plaka_arac}</span></div>
                            </div>
                            <div class="info-box">
                                <div class="info-label">Dorse Plakası</div>
                                <div class="info-value">${data.plaka_dorse ? '<span class="plate-badge">' + data.plaka_dorse + '</span>' : '-'}</div>
                            </div>

                            <div class="info-box" style="grid-column: span 2;">
                                <div class="info-label">Şoför Bilgisi</div>
                                <div class="info-value">${data.surucu || '-'} ${data.telefon ? ' (' + data.telefon + ')' : ''}</div>
                            </div>
                        </div>

                        <div class="weights-container">
                            <div class="weight-item">
                                <div class="weight-label">1. Tartım</div>
                                <div class="weight-value">${String(data.tartim_1).padStart(6, '0')} kg</div>
                            </div>
                            <div class="weight-item">
                                <div class="weight-label">2. Tartım</div>
                                <div class="weight-value">${String(data.tartim_2).padStart(6, '0')} kg</div>
                            </div>
                            <div class="weight-item net">
                                <div class="weight-label">Net Ağırlık</div>
                                <div class="weight-value">${String(data.net).padStart(6, '0')} kg</div>
                            </div>
                        </div>

                        <div class="signatures">
                            <div class="signature-box">
                                <div class="signature-line">Kantar Operatörü</div>
                            </div>
                            <div class="signature-box">
                                <div class="signature-line">Teslim Eden / Şoför</div>
                            </div>
                        </div>

                        <div class="footer">
                            Özbal Un Fabrikası - Otomatik Kantar Sistemi<br>
                            Belge Yazdırılma Tarihi: ${new Date().toLocaleString('tr-TR')}
                        </div>
                    </div>
                </div>
            </body>
            </html>
            `;

            printWindow.document.write(html);
            printWindow.document.close();

            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
            }, 800);
        }
    </script>
</body>

</html>