<?php
require 'config.php';
header('Content-Type: application/json');

// Error Handling
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

$trx = $_POST['trx'];
$q = mysqli_query($conn, "SELECT * FROM transactions WHERE trx_local='$trx'");
$d = mysqli_fetch_assoc($q);

if(!$d) die(json_encode(['status' => 'ERROR', 'msg' => 'Trx tidak ditemukan']));

// Kalau sudah selesai (Sukses/Gagal), langsung balikin data biar gak proses ulang
if($d['status'] == 'COMPLETED') {
    echo json_encode(['status' => 'COMPLETED', 'akun' => $d['account_data']]);
    exit;
}
if($d['status'] == 'FAILED') {
    echo json_encode(['status' => 'FAILED', 'msg' => $d['account_data']]);
    exit;
}

// ==========================================
// 1. CEK PEMBAYARAN USER (DEPOSIT)
// ==========================================
if($d['status'] == 'WAITING') {
    $res = requestAPI('pay_status', ['invoice' => $d['inv_deposit']]);
    
    if(isset($res['data']['status']) && $res['data']['status'] == 'success') {
        mysqli_query($conn, "UPDATE transactions SET status='PAID' WHERE id='{$d['id']}'");
        $d['status'] = 'PAID'; 
    }
}

// ==========================================
// 2. ORDER BARANG KE PUSAT
// ==========================================
if($d['status'] == 'PAID' || $d['status'] == 'PROCESSING') {
    
    // Kalau belum punya invoice order, coba order sekarang
    if(empty($d['inv_order'])) {
        
        // Log Percobaan
        file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Mencoba Order Produk ID: " . $d['service_id'] . "\n", FILE_APPEND);

        $res_ord = requestAPI('order', [
            'product_id' => (int)$d['service_id'],
            'qty' => 1,
            'whatsapp' => NOMOR_ADMIN 
        ]);

        if(isset($res_ord['success']) && $res_ord['success'] == true) {
            // SUKSES ORDER
            $inv_ord = $res_ord['invoice'];
            mysqli_query($conn, "UPDATE transactions SET status='PROCESSING', inv_order='$inv_ord' WHERE id='{$d['id']}'");
            $d['inv_order'] = $inv_ord;
            
        } else {
            // [BAGIAN PENTING YANG DITAMBAHKAN]
            // KALAU GAGAL ORDER (Stok Habis / Saldo Kurang / Gangguan)
            $pesan_error = $res_ord['message'] ?? 'Unknown Error dari Pusat';
            
            // 1. Catat Log
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - GAGAL ORDER FIXED: $pesan_error\n", FILE_APPEND);
            
            // 2. UPDATE STATUS JADI 'FAILED' DI DATABASE
            // Simpan pesan error ke kolom account_data biar ketahuan kenapa gagal
            $error_db = mysqli_real_escape_string($conn, "GAGAL: " . $pesan_error);
            mysqli_query($conn, "UPDATE transactions SET status='FAILED', account_data='$error_db' WHERE id='{$d['id']}'");
            
            // 3. Return JSON FAILED biar frontend langsung bereaksi (Merah)
            echo json_encode(['status' => 'FAILED', 'msg' => $pesan_error]);
            exit;
        }
    }

    // ==========================================
    // 3. AMBIL DATA AKUN (Check Status Order Pusat)
    // ==========================================
    if(!empty($d['inv_order'])) {
        $res_stat = requestAPI('status', ['invoice' => $d['inv_order']]);

        if(isset($res_stat['success']) && $res_stat['success'] == true) {
            if(!empty($res_stat['accounts'])) {
                $akun_txt = "";
                foreach($res_stat['accounts'] as $acc) {
                    $akun_txt .= "Email: " . $acc['username'] . "\nPass: " . $acc['password'] . "\n\n";
                }

                $akun_clean = mysqli_real_escape_string($conn, $akun_txt);
                mysqli_query($conn, "UPDATE transactions SET status='COMPLETED', account_data='$akun_clean' WHERE id='{$d['id']}'");
                
                echo json_encode(['status' => 'COMPLETED', 'akun' => $akun_txt]);
                exit;
            }
        }
    }
}

echo json_encode(['status' => $d['status']]);
?>