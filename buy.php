<?php
require 'config.php';
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <title>Processing...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { background: '#020617' } } } }
    </script>
    <style>
        /* Custom Style SweetAlert biar match sama tema Dark */
        .swal2-popup { background: #0f172a !important; color: #e2e8f0 !important; border: 1px solid #334155; }
        .swal2-title { color: #fff !important; }
        .swal2-confirm { background-color: #6366f1 !important; }
    </style>
</head>
<body class="bg-background flex items-center justify-center h-screen">

<?php
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sid = mysqli_real_escape_string($conn, $_POST['sid']);
    $wa  = mysqli_real_escape_string($conn, $_POST['wa']);
    
    // Validasi No WA
    if(substr($wa, 0, 2) == '08') $wa = '62' . substr($wa, 1);
    if(empty($wa) || strlen($wa) < 10) {
        showError("Nomor WhatsApp tidak valid!", "index.php");
    }

    // Cek Produk Lokal
    $q = mysqli_query($conn, "SELECT * FROM services WHERE id='$sid'");
    $prod = mysqli_fetch_assoc($q);
    
    if(!$prod) showError("Produk tidak ditemukan.", "index.php");

    // ========================================================
    // 1. CEK STOK REALTIME (MENCEGAH ORDER BARANG KOSONG)
    // ========================================================
    $cek_stok = requestAPI('stock', ['product_id' => (int)$sid]);
    $stok_tersedia = 0;
    
    if(isset($cek_stok['success']) && $cek_stok['success'] == true) {
        $stok_tersedia = (int)$cek_stok['stock'];
    }

    // KEPUTUSAN: STOK HABIS?
    if($stok_tersedia < 1) {
        // Update DB Lokal jadi 'gangguan' biar gak muncul lagi di index
        mysqli_query($conn, "UPDATE services SET status='gangguan' WHERE id='$sid'");
        
        // Tampilkan SweetAlert Error
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Stok Habis!',
                text: 'Yah, telat daks! Stok produk ini baru aja abis di pusat.',
                confirmButtonText: 'Cari Produk Lain'
            }).then((result) => {
                window.location = 'index.php';
            });
        </script>";
        exit;
    }

    // ========================================================
    // 2. PROSES PEMBAYARAN (QRIS)
    // ========================================================
    $amount = (int)$prod['price_jual'];
    $trx_local = "TRX-" . time() . rand(100,999);

    $res = requestAPI('pay', ['amount' => $amount]);

    if(isset($res['success']) && $res['success'] == true) {
        $inv_depo = $res['data']['invoice'];
        $total    = $res['data']['total_bayar'];
        
        // Simpan Transaksi
        $sql = "INSERT INTO transactions (trx_local, inv_deposit, service_id, target_wa, amount_pay, status) 
                VALUES ('$trx_local', '$inv_depo', '$sid', '$wa', '$total', 'WAITING')";
        
        if(mysqli_query($conn, $sql)) {
            // Redirect langsung ke Payment Page
            echo "<script>window.location = 'payment.php?trx=$trx_local';</script>";
        } else {
            showError("Database Error: Gagal menyimpan pesanan.", "index.php");
        }
    } else {
        // Error dari API Deposit
        $msg = $res['message'] ?? 'Gangguan koneksi ke pusat.';
        showError("Gagal Membuat Pembayaran: $msg", "index.php");
    }

} else {
    // Kalau akses file ini langsung tanpa POST
    echo "<script>window.location='index.php';</script>";
}

// Fungsi Bantuan Tampil Error
function showError($msg, $redirect) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '$msg',
            confirmButtonText: 'Kembali'
        }).then(() => {
            window.location = '$redirect';
        });
    </script>";
    exit;
}
?>

</body>
</html>