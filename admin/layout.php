<?php
/**
 * Admin shared layout helpers.
 * Provides adminHeader() and adminFooter() for consistent page chrome.
 *
 * @param string $pageTitle  Title shown in <title> and the page heading area
 * @param string $activePage Slug matching one of the nav items (dashboard|users|leaderboard|endless)
 */

function adminHeader(string $pageTitle, string $activePage = 'dashboard'): void {
    $admin = $_SESSION['auth'] ?? 'Admin';
    $nav = [
        'dashboard'   => ['label' => 'Dashboard',   'href' => 'index.php'],
        'users'       => ['label' => 'Users',        'href' => 'users.php'],
        'leaderboard' => ['label' => 'Leaderboard',  'href' => 'leaderboard.php'],
        'endless'     => ['label' => 'Endless',      'href' => 'endless.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?php echo htmlspecialchars($pageTitle); ?> · Purble Pairs</title>
    <script src="../assets/tailwind.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet"></noscript>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Fredoka', 'sans-serif'],
                        body:    ['Nunito', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fade-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .animate-fade-in { animation: fade-in 0.3s ease-out; }
        @keyframes bounce-in {
            0%   { transform: scale(0.3); opacity: 0; }
            50%  { transform: scale(1.1); }
            70%  { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce-in { animation: bounce-in 0.35s ease-out; }
    </style>
</head>
<body class="bg-gray-100 font-body min-h-screen">

    <!-- Logout confirmation dialog -->
    <div id="logoutModal" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm mx-4 space-y-4 animate-bounce-in">
            <div class="text-5xl">👋</div>
            <h3 class="font-display text-2xl font-bold text-gray-800">Log out?</h3>
            <p class="text-gray-500 font-body text-sm">You'll be returned to the login screen.</p>
            <div class="flex gap-3 justify-center pt-2">
                <a href="../logout.php"
                   class="px-5 py-2 rounded-xl bg-purple-600 text-white font-display font-semibold hover:bg-purple-700 transition-colors">
                    Log Out
                </a>
                <button onclick="document.getElementById('logoutModal').classList.replace('flex','hidden')"
                        class="px-5 py-2 rounded-xl bg-gray-200 text-gray-600 font-display font-semibold hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    <script>
        function confirmLogout() {
            var m = document.getElementById('logoutModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
    </script>

    <!-- Top bar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="font-display text-xl font-bold text-purple-700">Purble Admin</span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <span class="text-gray-500 hidden sm:block">
                    Logged in as <strong class="text-gray-700"><?php echo htmlspecialchars($admin); ?></strong>
                </span>
                <button onclick="confirmLogout()"
                        class="px-3 py-1.5 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold transition-colors text-xs">
                    Logout
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-6 flex gap-6">

        <!-- Sidebar nav -->
        <aside class="w-48 shrink-0 hidden md:block">
            <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <?php foreach ($nav as $slug => $item): ?>
                <a href="<?php echo $item['href']; ?>"
                   class="flex items-center gap-2 px-4 py-3 text-sm font-semibold transition-colors
                          <?php echo $activePage === $slug
                              ? 'bg-purple-600 text-white'
                              : 'text-gray-600 hover:bg-purple-50 hover:text-purple-700'; ?>">
                    <?php echo $item['label']; ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <!-- Mobile nav -->
        <div class="md:hidden w-full mb-2">
            <div class="flex gap-2 overflow-x-auto pb-1">
                <?php foreach ($nav as $slug => $item): ?>
                <a href="<?php echo $item['href']; ?>"
                   class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors
                          <?php echo $activePage === $slug
                              ? 'bg-purple-600 text-white'
                              : 'bg-white text-gray-600 hover:bg-purple-50 border border-gray-200'; ?>">
                    <?php echo $item['label']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Main content -->
        <main class="flex-1 min-w-0 animate-fade-in">
    <?php
}

function adminFooter(): void {
    ?>
        </main>
    </div><!-- /layout -->

</body>
</html>
    <?php
}

/**
 * Renders a stat card for the dashboard.
 */
function statCard(string $icon, string $label, string $value, string $color = 'purple'): void {
    $colors = [
        'purple' => 'bg-purple-50 text-purple-700 border-purple-100',
        'emerald'=> 'bg-emerald-50 text-emerald-700 border-emerald-100',
        'amber'  => 'bg-amber-50 text-amber-700 border-amber-100',
        'rose'   => 'bg-rose-50 text-rose-700 border-rose-100',
    ];
    $cls = $colors[$color] ?? $colors['purple'];
    echo "<div class=\"rounded-2xl border p-5 {$cls}\">";
    echo "<div class=\"text-2xl font-display font-bold\">{$value}</div>";
    echo "<div class=\"text-sm font-semibold opacity-70 mt-0.5\">{$label}</div>";
    echo "</div>";
}
