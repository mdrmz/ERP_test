<?php
ob_start();
session_start();
include("baglan.php");

$hata = "";

if (isset($_POST["giris_yap"])) {
    // Formdan gelen verileri al ve temizle
    $kadi = mysqli_real_escape_string($baglanti, $_POST["kadi"]);
    $sifre = md5($_POST["sifre"]); // MD5 hash

    // Veritabanında ara - Rol bilgisiyle birlikte
    $sorgu = "SELECT u.*, r.id as rol_id, r.rol_adi 
              FROM users u 
              LEFT JOIN kullanici_rolleri r ON u.rol_id = r.id 
              WHERE u.kadi='$kadi' AND u.sifre='$sifre'";
    $kontrol = $baglanti->query($sorgu);

    if ($kontrol->num_rows > 0) {
        // Kullanıcı bulundu! Bilgileri çek.
        $row = $kontrol->fetch_assoc();

        // Aktif kontrolü - Pasif kullanıcılar giriş yapamaz
        if ($row['aktif'] != 1) {
            $hata = "Hesabınız pasif durumda. Lütfen yönetici ile iletişime geçin.";
        } else {
            // --- SESSION BİLGİLERİ ---
            $_SESSION["oturum"] = true;
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["kadi"] = $kadi;
            $_SESSION["rol_id"] = $row["rol_id"];
            $_SESSION["rol_adi"] = $row["rol_adi"];
            $_SESSION["yetki"] = $row["yetki"];
            // -------------------------

            // Login logunu kaydet (non-blocking - hata olsa da giriş engellenmez)
            $user_id = $row["id"];
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $kadi_esc = $baglanti->real_escape_string($kadi);
            $baglanti->query("INSERT INTO system_logs (user_id, action_type, module, description, ip_address)
                              VALUES ($user_id, 'LOGIN', 'auth', 'Kullanıcı girişi: $kadi_esc', '$ip')");

            header("Location: panel.php");
            exit;
        }
    } else {
        $hata = "Hatalı kullanıcı adı veya şifre!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Özbal Un</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            box-shadow: none;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .password-toggle-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: rgba(255, 255, 255, 0.6);
        }

        .password-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .btn-giris {
            background: #f59e0b;
            color: #000;
            font-weight: bold;
            border: none;
            padding: 12px;
        }

        .btn-giris:hover {
            background: #d97706;
            color: #fff;
        }
    </style>
</head>

<body>

    <div class="login-card text-center">
        <h3 class="fw-bold mb-4">ÖZBAL UN <span class="text-warning">ERP</span></h3>



        <form method="post">
            <div class="mb-3 text-start">
                <label class="small text-white-50">Kullanıcı Adı</label>
                <input type="text" name="kadi" class="form-control" required placeholder="Giriş adınız">
            </div>

            <div class="mb-4 text-start">
                <label class="small text-white-50">Şifre</label>
                <div class="input-group">
                    <input type="password" name="sifre" id="sifre" class="form-control" required placeholder="******">
                    <button class="btn password-toggle-btn" type="button" id="togglePasswordBtn">
                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" name="giris_yap" class="btn btn-giris w-100 rounded-pill">
                Güvenli Giriş Yap
            </button>
        </form>

        <div class="mt-4 small text-white-50">
            &copy; 2026 Piksel Analitik
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (!empty($hata)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Giriş Başarısız',
                    text: '<?php echo addslashes(strip_tags($hata)); ?>',
                    confirmButtonColor: '#f59e0b',
                    background: '#1e293b',
                    color: '#fff',
                    heightAuto: false,
                    scrollbarPadding: false
                });
            <?php endif; ?>

            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const sifreInput = document.getElementById('sifre');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');

            togglePasswordBtn.addEventListener('click', function () {
                const type = sifreInput.getAttribute('type') === 'password' ? 'text' : 'password';
                sifreInput.setAttribute('type', type);
                
                if (type === 'password') {
                    togglePasswordIcon.classList.remove('fa-eye-slash');
                    togglePasswordIcon.classList.add('fa-eye');
                } else {
                    togglePasswordIcon.classList.remove('fa-eye');
                    togglePasswordIcon.classList.add('fa-eye-slash');
                }
            });
        });
    </script>
</body>

</html>
