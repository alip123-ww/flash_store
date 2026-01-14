<?php
require 'config.php';

// 1. CEK LOGIN
if(!isset($_SESSION['admin_logged'])) {
    if(isset($_POST['pass']) && $_POST['pass'] == ADMIN_PASSWORD) { 
        $_SESSION['admin_logged'] = true;
    } else {
        echo "<body class='bg-gray-900 text-white flex items-center justify-center h-screen font-sans'>
                <form method='post' class='bg-gray-800 p-8 rounded-xl shadow-2xl border border-gray-700 text-center w-80'>
                    <div class='mb-6'><i class='fa-solid fa-lock text-4xl text-indigo-500'></i></div>
                    <h3 class='text-xl font-bold mb-6'>Admin Access</h3>
                    <input type='password' name='pass' placeholder='Password' class='w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:border-indigo-500 outline-none mb-4 transition'>
                    <button class='w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg transition'>Masuk</button>
                </form>
                <script src='https://cdn.tailwindcss.com'></script>
                <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
              </body>";
        exit;
    }
}

// 2. LOGIC UPDATE HARGA MANUAL
if(isset($_POST['update_price'])) {
    $id = $_POST['id'];
    $price_jual = $_POST['price_jual'];
    mysqli_query($conn, "UPDATE services SET price_jual = '$price_jual' WHERE id = '$id'");
    echo "<script>alert('Harga Updated!'); window.location='admin.php?tab=products';</script>";
}

// 3. UPDATE SETTINGS
if(isset($_POST['save_settings'])) {
    $wa = mysqli_real_escape_string($conn, $_POST['wa']);
    $api = mysqli_real_escape_string($conn, $_POST['api']);
    $pass = mysqli_real_escape_string($conn, $_POST['pass']);
    $markup = mysqli_real_escape_string($conn, $_POST['markup']); 

    mysqli_query($conn, "UPDATE settings SET setting_value='$wa' WHERE setting_key='nomor_admin'");
    mysqli_query($conn, "UPDATE settings SET setting_value='$api' WHERE setting_key='api_key'");
    mysqli_query($conn, "UPDATE settings SET setting_value='$pass' WHERE setting_key='admin_password'");
    
    $cek = mysqli_query($conn, "SELECT * FROM settings WHERE setting_key='profit_markup'");
    if(mysqli_num_rows($cek) > 0) {
        mysqli_query($conn, "UPDATE settings SET setting_value='$markup' WHERE setting_key='profit_markup'");
    } else {
        mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('profit_markup', '$markup')");
    }

    echo "<script>alert('Pengaturan Disimpan!'); window.location='admin.php?tab=settings';</script>";
}

// 4. SYNC PRODUK (AUTO CALCULATE + AUTO CLEAN)
if(isset($_POST['sync'])) {
    $res = requestAPI('products', []);
    if(isset($res['success']) && $res['success']) {
        
        $active_ids = []; // Array buat nampung ID yang ada di pusat

        foreach($res['products'] as $p) {
            $id = (int)$p['id'];
            $active_ids[] = $id; // Masukin ID ke list aktif

            $name = mysqli_real_escape_string($conn, $p['name']);
            $price = (double)$p['price'];
            $stat = mysqli_real_escape_string($conn, $p['status']);
            
            // Auto Category Logic
            $cat = 'General';
            $n_lower = strtolower($name);
            if(strpos($n_lower, 'netflix') !== false) $cat = 'Netflix';
            elseif(strpos($n_lower, 'spotify') !== false) $cat = 'Musik & Video';
            elseif(strpos($n_lower, 'youtube') !== false) $cat = 'Musik & Video';
            elseif(strpos($n_lower, 'canva') !== false) $cat = 'Editing';
            elseif(strpos($n_lower, 'capcut') !== false) $cat = 'Editing';
            elseif(strpos($n_lower, 'am') !== false) $cat = 'Editing';
            elseif(strpos($n_lower, 'alight') !== false) $cat = 'Editing';
            elseif(strpos($n_lower, 'gemini') !== false) $cat = 'AI';
            elseif(strpos($n_lower, 'gpt') !== false) $cat = 'AI';
            elseif(strpos($n_lower, 'discord') !== false) $cat = 'Musik & Video';
            elseif(strpos($n_lower, 'nitro') !== false) $cat = 'Musik & Video';
            elseif(strpos($n_lower, 'viu') !== false) $cat = 'Musik & Video';
            elseif(strpos($n_lower, 'vidio') !== false) $cat = 'Musik & Video';
            
            // Hitung Harga Jual
            $default_jual = $price + ($price * PROFIT_MARKUP); 

            // Insert atau Update Data
            $sql = "INSERT INTO services (id, category, name, price_modal, price_jual, status) 
                    VALUES ('$id', '$cat', '$name', '$price', '$default_jual', '$stat')
                    ON DUPLICATE KEY UPDATE price_modal='$price', status='$stat', name='$name', category='$cat'"; 
            mysqli_query($conn, $sql);
        }

        // --- FITUR AUTO CLEAN (HAPUS YANG GAK ADA DI PUSAT) ---
        if(count($active_ids) > 0) {
            // Ubah array jadi string: 1,2,5,8
            $ids_string = implode(',', $active_ids);
            
            // Update status jadi 'unavailable' untuk produk lokal yang ID-nya GAK ADA di list pusat
            $q_clean = "UPDATE services SET status='unavailable' WHERE id NOT IN ($ids_string)";
            mysqli_query($conn, $q_clean);
        }

        echo "<script>alert('Sync Sukses! Produk update & produk hilang disembunyikan.'); window.location='admin.php';</script>";
    } else {
        echo "<script>alert('Gagal mengambil data dari API Pusat.');</script>";
    }
}

// 5. STATISTIK
$q_profit = mysqli_query($conn, "SELECT SUM(t.amount_pay - s.price_modal) as total_cuan FROM transactions t JOIN services s ON t.service_id = s.id WHERE t.status = 'COMPLETED'");
$total_cuan = mysqli_fetch_assoc($q_profit)['total_cuan'] ?? 0;
$q_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM transactions WHERE status = 'COMPLETED'");
$total_trx = mysqli_fetch_assoc($q_count)['total'];

// 6. PAGINATION & TAB
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'orders';
?>

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { dark: '#0f172a', surface: '#1e293b', primary: '#6366f1' } 
                } 
            }
        }
    </script>
</head>
<body class="bg-dark text-gray-200 font-sans min-h-screen">

    <nav class="bg-surface/80 backdrop-blur-md border-b border-gray-700 px-6 py-4 flex justify-between items-center sticky top-0 z-50">
        <h1 class="text-xl font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-bolt text-primary"></i> Admin<span class="text-gray-400 font-light">Panel</span>
        </h1>
        <div class="flex gap-4 text-sm">
            <a href="index.php" target="_blank" class="flex items-center gap-2 text-gray-400 hover:text-white transition"><i class="fa-solid fa-store"></i> <span class="hidden sm:inline">Lihat Toko</span></a>
            <a href="logout.php" class="flex items-center gap-2 text-red-400 hover:text-red-300 transition"><i class="fa-solid fa-right-from-bracket"></i> <span class="hidden sm:inline">Keluar</span></a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-4 sm:p-6">
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-surface p-6 rounded-2xl border border-gray-700 shadow-xl relative overflow-hidden group hover:border-primary/50 transition">
                <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition"><i class="fa-solid fa-wallet text-6xl text-green-500"></i></div>
                <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Profit</p>
                <h3 class="text-3xl sm:text-4xl font-bold text-green-400 mt-2">Rp <?= number_format($total_cuan) ?></h3>
                <p class="text-xs text-gray-500 mt-2">Bersih (Setelah modal)</p>
            </div>
            <div class="bg-surface p-6 rounded-2xl border border-gray-700 shadow-xl relative overflow-hidden group hover:border-blue-500/50 transition">
                <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition"><i class="fa-solid fa-bag-shopping text-6xl text-blue-500"></i></div>
                <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Total Penjualan</p>
                <h3 class="text-3xl sm:text-4xl font-bold text-blue-400 mt-2"><?= $total_trx ?> <span class="text-lg text-gray-500">Trx</span></h3>
                <p class="text-xs text-gray-500 mt-2">Status Completed</p>
            </div>
            <div class="bg-surface p-6 rounded-2xl border border-gray-700 shadow-xl flex flex-col justify-between">
                <div>
                    <p class="text-gray-400 text-sm font-medium uppercase tracking-wider">Markup Aktif</p>
                    <h3 class="text-2xl font-bold text-white mt-1"><?= PROFIT_MARKUP * 100 ?>% <span class="text-sm font-normal text-gray-500">dari modal</span></h3>
                </div>
                <form method="post" class="mt-4">
                    <button name="sync" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-900/50">
                        <i class="fa-solid fa-arrows-rotate"></i> Sync Produk Baru
                    </button>
                </form>
            </div>
        </div>

        <div class="mb-6 border-b border-gray-700 overflow-x-auto no-scrollbar">
            <nav class="flex space-x-2" aria-label="Tabs">
                <button onclick="switchTab('orders')" id="tab-orders" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors <?= $active_tab == 'orders' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">
                    <i class="fa-solid fa-list-ul mr-2"></i> Riwayat Transaksi
                </button>
                <button onclick="switchTab('products')" id="tab-products" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors <?= $active_tab == 'products' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">
                    <i class="fa-solid fa-box mr-2"></i> Produk & Harga
                </button>
                <button onclick="switchTab('settings')" id="tab-settings" class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors <?= $active_tab == 'settings' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">
                    <i class="fa-solid fa-gears mr-2"></i> Pengaturan
                </button>
            </nav>
        </div>

        <div id="orders" class="tab-content <?= $active_tab == 'orders' ? '' : 'hidden' ?>">
            <div class="bg-surface rounded-xl border border-gray-700 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-400">
                        <thead class="bg-gray-900 text-gray-200 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4">Waktu</th>
                                <th class="px-6 py-4">Produk</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Profit</th>
                                <th class="px-6 py-4">Akun / Ket</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php 
                            $total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transactions"))['total'];
                            $total_pages = ceil($total_rows / $limit);
                            
                            $q_trx = mysqli_query($conn, "SELECT t.*, s.name, s.price_modal FROM transactions t LEFT JOIN services s ON t.service_id = s.id ORDER BY t.id DESC LIMIT $start, $limit");
                            
                            while($r = mysqli_fetch_assoc($q_trx)): 
                                $profit = $r['amount_pay'] - $r['price_modal'];
                                $status_badge = match($r['status']) {
                                    'COMPLETED' => '<span class="px-2 py-1 bg-green-500/10 text-green-400 rounded text-xs font-bold border border-green-500/20">SUKSES</span>',
                                    'FAILED' => '<span class="px-2 py-1 bg-red-500/10 text-red-400 rounded text-xs font-bold border border-red-500/20">GAGAL</span>',
                                    'PAID', 'PROCESSING' => '<span class="px-2 py-1 bg-blue-500/10 text-blue-400 rounded text-xs font-bold border border-blue-500/20">PROSES</span>',
                                    default => '<span class="px-2 py-1 bg-yellow-500/10 text-yellow-400 rounded text-xs font-bold border border-yellow-500/20">PENDING</span>'
                                };
                            ?>
                            <tr class="hover:bg-gray-800/50 transition">
                                <td class="px-6 py-4 whitespace-nowrap"><?= date('d/m H:i', strtotime($r['created_at'])) ?></td>
                                <td class="px-6 py-4 font-medium text-white"><?= $r['name'] ?? 'Produk Dihapus' ?></td>
                                <td class="px-6 py-4"><?= $status_badge ?></td>
                                <td class="px-6 py-4">
                                    <?php if($r['status'] == 'COMPLETED') echo '<span class="text-green-400 font-bold">+Rp '.number_format($profit).'</span>'; else echo '-'; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <textarea readonly class="bg-gray-900 border border-gray-700 text-xs text-gray-300 p-2 rounded w-48 h-10 focus:h-24 transition-all resize-none outline-none focus:border-primary"><?= $r['account_data'] ?? '-' ?></textarea>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-700 flex justify-between items-center bg-gray-800/50">
                    <span class="text-xs text-gray-500">Hal <?= $page ?> dari <?= $total_pages ?></span>
                    <div class="flex gap-2">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page-1 ?>&tab=orders" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white transition">Prev</a>
                        <?php endif; ?>
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page+1 ?>&tab=orders" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 rounded text-xs text-white transition">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="products" class="tab-content <?= $active_tab == 'products' ? '' : 'hidden' ?>">
            <div class="bg-surface rounded-xl border border-gray-700 overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-400">
                        <thead class="bg-gray-900 text-gray-200 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4">Nama Produk</th>
                                <th class="px-6 py-4">Modal</th>
                                <th class="px-6 py-4">Jual (Edit)</th>
                                <th class="px-6 py-4">Cuan</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php 
                            $q_serv = mysqli_query($conn, "SELECT * FROM services ORDER BY category, name ASC");
                            while($s = mysqli_fetch_assoc($q_serv)): 
                                $est_profit = $s['price_jual'] - $s['price_modal'];
                            ?>
                            <tr class="hover:bg-gray-800/50 transition group">
                                <td class="px-6 py-4">
                                    <span class="block text-white font-medium"><?= $s['name'] ?></span>
                                    <span class="text-[10px] bg-gray-700 text-gray-300 px-1.5 py-0.5 rounded mt-1 inline-block"><?= $s['category'] ?></span>
                                </td>
                                <td class="px-6 py-4">Rp <?= number_format($s['price_modal']) ?></td>
                                <form method="post">
                                <td class="px-6 py-4">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <input type="number" name="price_jual" value="<?= $s['price_jual'] ?>" class="bg-gray-900 border border-gray-600 text-white rounded p-2 w-28 text-center focus:border-primary outline-none text-sm font-bold">
                                </td>
                                <td class="px-6 py-4 text-green-400 font-bold">Rp <?= number_format($est_profit) ?></td>
                                <td class="px-6 py-4">
                                    <?php if($s['status'] == 'available'): ?>
                                        <span class="text-green-400 text-xs font-bold">Aktif</span>
                                    <?php else: ?>
                                        <span class="text-red-400 text-xs font-bold">Mati/Ghoib</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <button name="update_price" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded text-xs font-bold transition shadow-lg opacity-0 group-hover:opacity-100">Simpan</button>
                                </td>
                                </form>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="settings" class="tab-content <?= $active_tab == 'settings' ? '' : 'hidden' ?>">
            <div class="bg-surface rounded-xl border border-gray-700 p-6 max-w-2xl mx-auto shadow-xl">
                <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2 border-b border-gray-700 pb-4">
                    <i class="fa-solid fa-sliders"></i> Pengaturan Toko
                </h3>
                <form method="post" class="space-y-5">
                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-medium">Nomor WhatsApp Admin</label>
                        <input type="text" name="wa" value="<?= NOMOR_ADMIN ?>" class="w-full bg-dark border border-gray-600 rounded-lg p-3 text-white focus:border-primary outline-none transition">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-medium">API Key Premiumku</label>
                        <input type="text" name="api" value="<?= API_KEY ?>" class="w-full bg-dark border border-gray-600 rounded-lg p-3 text-white focus:border-primary outline-none transition font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-medium">Markup Profit Otomatis (Format: 0.3 = 30%)</label>
                        <div class="relative">
                            <input type="text" name="markup" value="<?= PROFIT_MARKUP ?>" class="w-full bg-dark border border-gray-600 rounded-lg p-3 text-white focus:border-primary outline-none transition">
                            <span class="absolute right-4 top-3 text-gray-500 text-sm">Default: 0.30</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">*Berlaku saat tombol Sync ditekan.</p>
                    </div>
                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-medium">Ganti Password Admin</label>
                        <input type="text" name="pass" value="<?= ADMIN_PASSWORD ?>" class="w-full bg-dark border border-gray-600 rounded-lg p-3 text-white focus:border-primary outline-none transition">
                    </div>
                    <button name="save_settings" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition shadow-lg shadow-green-900/50 mt-4">
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            const buttons = ['orders', 'products', 'settings'];
            buttons.forEach(id => {
                const btn = document.getElementById('tab-' + id);
                btn.className = "px-4 py-2 text-sm font-medium rounded-t-lg transition-colors text-gray-400 hover:text-white hover:bg-gray-800";
            });
            document.getElementById(tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-' + tabId);
            activeBtn.className = "px-4 py-2 text-sm font-medium rounded-t-lg transition-colors bg-surface text-primary border-b-2 border-primary";
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }
    </script>
</body>
</html>