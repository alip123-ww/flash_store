<?php
require 'config.php';

// Ambil data produk available
$query = "SELECT * FROM services WHERE status='available' ORDER BY category ASC, price_jual ASC";
$result = mysqli_query($conn, $query);

$products = [];
$categories = [];
while($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
    if(!in_array($row['category'], $categories)) $categories[] = $row['category'];
}

// Logic Lacak (Redirect Mode)
$error_msg = null;
if(isset($_GET['search_invoice'])) {
    $inv = mysqli_real_escape_string($conn, $_GET['search_invoice']);
    $q_inv = mysqli_query($conn, "SELECT trx_local FROM transactions WHERE trx_local = '$inv' OR target_wa = '$inv' ORDER BY id DESC LIMIT 1");
    $data = mysqli_fetch_assoc($q_inv);
    
    if($data) {
        header("Location: payment.php?trx=" . $data['trx_local']);
        exit;
    } else {
        $error_msg = "Data tidak ditemukan. Cek kembali ID/WA Anda.";
    }
}
?>

<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <title>FlashStore - Premium</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Inter"', 'sans-serif'] },
                    colors: {
                        bg: '#09090b',      // Zinc 950 (Hitam Elegan)
                        surface: '#18181b', // Zinc 900
                        border: '#27272a',  // Zinc 800
                        primary: '#6366f1', // Indigo
                    }
                }
            }
        }
    </script>
    <style>
        /* Sembunyikan Scrollbar tapi tetap bisa scroll */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Smooth Transition */
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-4px); border-color: #6366f1; }
    </style>
</head>
<body class="bg-bg text-gray-400 font-sans min-h-screen selection:bg-primary selection:text-white">

    <div class="fixed top-0 left-0 right-0 h-[500px] bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-primary/20 via-bg to-bg pointer-events-none -z-10"></div>

    <nav class="sticky top-0 z-50 bg-bg/80 backdrop-blur-md border-b border-border">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 cursor-pointer" onclick="window.location='index.php'">
                <div class="w-8 h-8 bg-white text-black rounded-lg flex items-center justify-center font-bold shadow-[0_0_15px_rgba(255,255,255,0.3)]">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <span class="text-white font-semibold tracking-tight text-lg">FlashStore</span>
            </div>

            <form action="" method="GET" class="hidden md:block w-80">
                <div class="relative group">
                    <input type="text" name="search_invoice" placeholder="Cek Pesanan (TRX / WA)..." 
                           class="w-full bg-surface border border-border rounded-full py-2 pl-4 pr-10 text-sm text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all placeholder-gray-600">
                    <button class="absolute right-3 top-2.5 text-gray-500 hover:text-white transition">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="md:hidden px-4 pb-3 border-t border-border bg-bg/95">
             <form action="" method="GET" class="mt-3">
                <input type="text" name="search_invoice" placeholder="Lacak Pesanan..." class="w-full bg-surface border border-border rounded-lg px-4 py-2 text-sm text-white focus:border-primary outline-none">
             </form>
        </div>
    </nav>

    <?php if($error_msg): ?>
        <script>
            Swal.fire({ 
                icon: 'error', 
                title: 'Maaf', 
                text: '<?= $error_msg ?>', 
                background: '#18181b', color: '#fff', confirmButtonColor: '#6366f1' 
            });
        </script>
    <?php endif; ?>

    <div class="pt-16 pb-10 px-4 text-center max-w-4xl mx-auto">
        <h1 class="text-4xl md:text-6xl font-bold text-white tracking-tight mb-4 leading-tight">
            Premium Digital.
        </h1>
        <p class="text-gray-500 text-lg mb-8 max-w-xl mx-auto">
            Upgrade produktivitasmu dengan akun premium legal. Proses otomatis, harga pelajar, garansi aktif.
        </p>

        <div class="relative max-w-lg mx-auto">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <i class="fa-solid fa-search text-gray-500"></i>
            </div>
            <input type="text" id="filterProduct" placeholder="Cari layanan (contoh: Netflix)..." 
                   class="block w-full pl-11 pr-4 py-4 bg-surface border border-border rounded-xl text-white placeholder-gray-600 focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-all shadow-lg">
        </div>
    </div>

    <div class="sticky top-16 z-40 bg-bg/95 backdrop-blur border-b border-border py-3">
        <div class="max-w-7xl mx-auto px-4 overflow-x-auto no-scrollbar flex gap-2">
            <button onclick="filterCat('all')" data-cat="all" 
                    class="cat-btn active px-4 py-1.5 rounded-full text-sm font-medium border border-transparent transition-colors whitespace-nowrap bg-white text-black hover:bg-gray-200">
                Semua
            </button>
            <?php foreach($categories as $cat): ?>
            <button onclick="filterCat('<?= $cat ?>')" data-cat="<?= $cat ?>" 
                    class="cat-btn px-4 py-1.5 rounded-full text-sm font-medium border border-border transition-colors whitespace-nowrap text-gray-400 hover:text-white hover:border-gray-500">
                <?= $cat ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-10 pb-24">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" id="gridProduk">
            
            <?php foreach($products as $p): ?>
            <div class="card-item bg-surface border border-border rounded-xl p-5 flex flex-col h-full card-hover group" 
                 data-cat="<?= $p['category'] ?>" data-name="<?= strtolower($p['name']) ?>">
                
                <div class="flex justify-between items-start mb-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 border border-border px-2 py-1 rounded bg-bg">
                        <?= $p['category'] ?>
                    </span>
                    <div class="flex items-center gap-1.5">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                    </div>
                </div>

                <h3 class="text-white font-semibold text-lg leading-snug mb-2 group-hover:text-primary transition-colors">
                    <?= $p['name'] ?>
                </h3>

                <div class="mt-auto pt-4 border-t border-border/50">
                    <div class="flex justify-between items-end mb-3">
                        <span class="text-xs text-gray-500">Harga Satuan</span>
                        <span class="text-xl font-bold text-white">Rp <?= number_format($p['price_jual']) ?></span>
                    </div>

                    <form action="buy.php" method="POST" class="buy-form flex gap-2">
                        <input type="hidden" name="sid" value="<?= $p['id'] ?>">
                        
                        <input type="number" name="wa" required placeholder="Nomor WA (628..)" 
                               class="w-full bg-bg border border-border rounded-lg px-3 py-2 text-sm text-white focus:border-primary outline-none transition-colors">
                        
                        <button type="submit" class="btn-submit bg-white text-black hover:bg-gray-200 px-4 py-2 rounded-lg font-medium text-sm transition-colors flex items-center justify-center">
                            <i class="fa-solid fa-arrow-right icon-arrow"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

        <div id="emptyState" class="hidden text-center py-20">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface border border-border mb-4">
                <i class="fa-solid fa-box-open text-gray-600"></i>
            </div>
            <p class="text-gray-500 text-sm">Produk tidak ditemukan.</p>
        </div>
    </div>

    <footer class="border-t border-border py-8 text-center bg-bg">
        <p class="text-gray-600 text-sm">© 2026 FlashStore. Secure & Automated.</p>
    </footer>

    <script>
        // 1. Logic Filter
        const searchInput = document.getElementById('filterProduct');
        const cards = document.querySelectorAll('.card-item');
        const catBtns = document.querySelectorAll('.cat-btn');

        function runFilter() {
            const query = searchInput.value.toLowerCase();
            const activeBtn = document.querySelector('.cat-btn.active');
            const currentCat = activeBtn ? activeBtn.getAttribute('data-cat') : 'all';

            let visibleCount = 0;
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const cat = card.getAttribute('data-cat');
                if(name.includes(query) && (currentCat === 'all' || cat === currentCat)) {
                    card.style.display = 'flex'; 
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            document.getElementById('emptyState').classList.toggle('hidden', visibleCount > 0);
        }

        function filterCat(catName) {
            catBtns.forEach(btn => {
                if (btn.getAttribute('data-cat') === catName) {
                    btn.classList.add('active', 'bg-white', 'text-black', 'hover:bg-gray-200');
                    btn.classList.remove('text-gray-400', 'hover:text-white', 'hover:border-gray-500', 'border-border');
                    btn.classList.add('border-transparent');
                } else {
                    btn.classList.remove('active', 'bg-white', 'text-black', 'hover:bg-gray-200', 'border-transparent');
                    btn.classList.add('text-gray-400', 'hover:text-white', 'hover:border-gray-500', 'border-border');
                }
            });
            runFilter();
        }
        searchInput.addEventListener('input', runFilter);

        // 2. Auto Format WA
        document.querySelectorAll('input[name="wa"]').forEach(i => {
            i.addEventListener('input', (e) => {
                if(e.target.value.startsWith('08')) e.target.value = '62' + e.target.value.substring(1);
            });
        });

        // ==========================================
        // 3. ANTI SPAM SUBMIT (FITUR BARU)
        // ==========================================
        document.querySelectorAll('.buy-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = this.querySelector('.btn-submit');
                const icon = this.querySelector('.icon-arrow');
                
                // Kalau tombol udah didisable (user klik kedua kali), stop
                if (btn.disabled) {
                    e.preventDefault();
                    return;
                }

                // 1. Disable Tombol
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');

                // 2. Ubah Icon jadi Spinner Loading
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';
                
                // Form tetap tersubmit secara normal ke buy.php
            });
        });
    </script>
</body>
</html>