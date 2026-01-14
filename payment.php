<?php
require 'config.php';

$trx = mysqli_real_escape_string($conn, $_GET['trx']);
$q = mysqli_query($conn, "SELECT t.*, s.name as prod_name FROM transactions t JOIN services s ON t.service_id = s.id WHERE trx_local='$trx'");
$d = mysqli_fetch_assoc($q);

if(!$d) die("Transaksi tidak ditemukan.");

// --- LOGIC PRE-CHECK STATUS ---
$status = $d['status'];
$is_completed = ($status == 'COMPLETED');
$is_failed    = ($status == 'FAILED');
$is_pending   = ($status == 'WAITING' || $status == 'PAID' || $status == 'PROCESSING');

// Cuma request QR ke API kalau statusnya masih PENDING (Biar hemat request & loading cepet)
$qr_image = '';
if($is_pending) {
    $res_qr = requestAPI('pay_status', ['invoice' => $d['inv_deposit']]);
    $qr_image = $res_qr['data']['qr_image'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <title>Status Pembayaran</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-[#0f172a] text-gray-200 min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden">
    
    <div class="bg-gray-900 p-6 text-center border-b border-gray-700">
        <h2 class="text-xl font-bold text-white"><?= $d['prod_name'] ?></h2>
        <p class="text-gray-400 text-sm">ID: <?= $trx ?></p>
    </div>

    <div class="p-6">
        
        <div id="payment-area" class="<?= $is_pending ? '' : 'hidden' ?> text-center">
            <p class="text-sm text-gray-400 mb-2">Scan QRIS di bawah ini</p>
            <div class="bg-white p-3 rounded-xl inline-block mb-4">
                <img src="<?= $qr_image ?>" class="w-48 h-48 object-contain">
            </div>
            <div class="mb-4">
                <p class="text-gray-400 text-xs">Total Bayar</p>
                <p class="text-3xl font-bold text-white">Rp <?= number_format($d['amount_pay']) ?></p>
                <p class="text-xs text-red-400 mt-1 animate-pulse font-bold">*Transfer harus sesuai nominal digit terakhir!</p>
            </div>
            <div class="flex items-center justify-center gap-2 text-yellow-400 animate-pulse">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
                <span class="font-medium" id="status-text">
                    <?= ($status == 'PAID' || $status == 'PROCESSING') ? 'Pembayaran Diterima! Memproses Order...' : 'Menunggu Pembayaran...' ?>
                </span>
            </div>
        </div>

        <div id="result-area" class="<?= (!$is_pending) ? '' : 'hidden' ?> text-center">
            
            <div id="result-icon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 
                <?= $is_completed ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500' ?>">
                <i class="fa-solid <?= $is_completed ? 'fa-check' : 'fa-xmark' ?> text-3xl"></i>
            </div>

            <h3 id="result-title" class="text-xl font-bold text-white mb-2 <?= $is_failed ? 'text-red-500' : '' ?>">
                <?= $is_completed ? 'Pembayaran Diterima!' : 'Transaksi Gagal!' ?>
            </h3>
            
            <p id="result-desc" class="text-gray-400 text-sm mb-4">
                <?= $is_completed ? 'Berikut detail akun kamu:' : 'Maaf, stok produk habis saat proses order.' ?>
            </p>
            
            <div class="bg-gray-900 rounded-lg p-4 text-left border border-gray-700 relative group">
                <pre id="account-detail" class="font-mono text-sm whitespace-pre-wrap break-all <?= $is_completed ? 'text-green-400' : 'text-red-400 font-bold' ?>"><?= $is_completed ? $d['account_data'] : "Mohon screenshot halaman ini dan hubungi Admin untuk REFUND atau Ganti Produk." ?></pre>
            </div>
            
            <?php if($is_completed): ?>
                <a href="index.php" class="block mt-6 w-full font-medium rounded-lg px-5 py-3 transition text-white bg-gray-700 hover:bg-gray-600">Beli Lagi</a>
            <?php else: ?>
                <a href="https://wa.me/<?= NOMOR_ADMIN ?>?text=Halo%20Admin,%20Transaksi%20<?= $trx ?>%20Gagal%20(Stok%20Habis).%20Mohon%20Bantuan." class="block mt-6 w-full font-medium rounded-lg px-5 py-3 transition text-white bg-green-600 hover:bg-green-700">
                    <i class="fa-brands fa-whatsapp"></i> Hubungi Admin (Refund)
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Hanya jalankan AJAX Check kalau status MASIH PENDING
    // Kalau udah Completed/Failed dari PHP di atas, script ini gak perlu jalan.
    
    <?php if($is_pending): ?>
        
        const adminWA = "<?= NOMOR_ADMIN ?>";
        const trxID = "<?= $trx ?>";

        function checkStatus() {
            $.ajax({
                url: 'worker.php', 
                type: 'POST',
                data: {trx: trxID},
                dataType: 'json',
                success: function(res) {
                    
                    if(res.status === 'COMPLETED') {
                        // UPDATE TAMPILAN JADI SUKSES
                        $('#payment-area').slideUp();
                        $('#result-area').removeClass('hidden').slideDown();
                        
                        $('#result-icon').attr('class', 'w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-green-500/20 text-green-500')
                            .html('<i class="fa-solid fa-check text-3xl"></i>');
                        
                        $('#result-title').text('Pembayaran Diterima!').removeClass('text-red-500');
                        $('#result-desc').text('Berikut detail akun kamu:');
                        
                        $('#account-detail').attr('class', 'font-mono text-sm whitespace-pre-wrap break-all text-green-400')
                            .text(res.akun);
                        
                        $('#result-area a').attr('href', 'index.php')
                            .attr('class', 'block mt-6 w-full font-medium rounded-lg px-5 py-3 transition text-white bg-gray-700 hover:bg-gray-600')
                            .text('Beli Lagi');
                        
                        clearInterval(interval);
                    } 
                    else if(res.status === 'PAID') {
                        $('#status-text').text('Pembayaran Diterima! Memproses Order...')
                            .removeClass('text-yellow-400').addClass('text-blue-400');
                    }
                    else if(res.status === 'FAILED') {
                        // UPDATE TAMPILAN JADI GAGAL
                        $('#payment-area').slideUp();
                        $('#result-area').removeClass('hidden').slideDown();

                        $('#result-icon').attr('class', 'w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 bg-red-500/20 text-red-500')
                            .html('<i class="fa-solid fa-xmark text-3xl"></i>');
                        
                        $('#result-title').text('Transaksi Gagal!').addClass('text-red-500');
                        $('#result-desc').text('Maaf, stok produk habis saat proses order.');
                        
                        $('#account-detail').attr('class', 'font-mono text-sm whitespace-pre-wrap break-all text-red-400 font-bold')
                            .text("Mohon screenshot halaman ini dan hubungi Admin untuk REFUND atau Ganti Produk.");

                        let linkWA = `https://wa.me/${adminWA}?text=Halo Admin, Transaksi ID ${trxID} status GAGAL (Stok Habis). Mohon refund/bantuan.`;
                        
                        $('#result-area a').attr('href', linkWA)
                            .attr('class', 'block mt-6 w-full font-medium rounded-lg px-5 py-3 transition text-white bg-green-600 hover:bg-green-700')
                            .html('<i class="fa-brands fa-whatsapp"></i> Hubungi Admin (Refund)');
                        
                        clearInterval(interval);
                    }
                }
            });
        }

        let interval = setInterval(checkStatus, 3000);

    <?php endif; ?>
</script>

</body>
</html>