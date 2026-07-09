<?php
/**
 * Purble Pairs - Home / Difficulty Selection Screen
 */
session_start();

if (empty($_SESSION['auth'])) {
    header('Location: auth.php');
    exit;
}

// Admins have no business on the game side — send them to their panel
if (!empty($_SESSION['is_admin'])) {
    header('Location: admin/index.php');
    exit;
}

unset($_SESSION['game'], $_SESSION['endless'], $_SESSION['endless_round']);

$playerName = $_SESSION['player_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purble Pairs</title>
    <script src="assets/tailwind.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet"></noscript>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Fredoka', 'sans-serif'],
                        body: ['Nunito', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes bounce-in {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce-in { animation: bounce-in 0.4s ease-out; }
    </style>
</head>
<body class="bg-purple-50 font-body min-h-screen flex items-center justify-center px-4">

    <!-- Logout confirmation dialog -->
    <div id="logoutModal" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm mx-4 space-y-4 animate-bounce-in">
            <div class="text-5xl">👋</div>
            <h3 class="font-display text-2xl font-bold text-gray-800">Log out?</h3>
            <p class="text-gray-500 font-body text-sm">You'll be returned to the login screen.</p>
            <div class="flex gap-3 justify-center pt-2">
                <a href="logout.php"
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

    <button onclick="confirmLogout()"
       class="fixed top-4 right-4 text-xs text-gray-400 hover:text-gray-600 font-body transition-colors z-50">
        Logout
    </button>

    <a href="leaderboard.php"
       class="fixed top-4 left-4 text-xs text-gray-400 hover:text-purple-600 font-body transition-colors z-50">
        🏆 Leaderboard
    </a>

    <div class="flex flex-col items-center gap-8 max-w-lg w-full">

        <div class="text-center space-y-3">
            <h1 class="font-display text-4xl sm:text-5xl md:text-6xl font-bold text-purple-700">
                Purble Pairs
            </h1>
            <p class="text-gray-500 text-base sm:text-lg">
                Flip cards and find all matching pairs before time runs out!
            </p>
        </div>

        <?php if (!empty($playerName)): ?>
        <div class="text-gray-600 font-body text-sm">
            Playing as <strong class="text-purple-700"><?php echo htmlspecialchars($playerName); ?></strong>
        </div>
        <?php endif; ?>

        <!-- Difficulty Buttons -->
        <div class="grid grid-cols-2 gap-4 w-full">
            <a href="game.php?difficulty=beginner"
               class="rounded-xl px-6 py-5 text-center font-display font-semibold text-lg
                      bg-emerald-500 text-white shadow-lg
                      hover:bg-emerald-600 hover:scale-105 active:scale-95
                      transition-all duration-200">
                <div>Beginner</div>
                <div class="text-sm opacity-80 font-body mt-1">4×3 grid · 6 pairs · 3 min</div>
            </a>
            <a href="game.php?difficulty=intermediate"
               class="rounded-xl px-6 py-5 text-center font-display font-semibold text-lg
                      bg-amber-500 text-white shadow-lg
                      hover:bg-amber-600 hover:scale-105 active:scale-95
                      transition-all duration-200">
                <div>Intermediate</div>
                <div class="text-sm opacity-80 font-body mt-1">4×4 grid · 8 pairs · 2 min</div>
            </a>
            <a href="game.php?difficulty=advanced"
               class="rounded-xl px-6 py-5 text-center font-display font-semibold text-lg
                      bg-rose-500 text-white shadow-lg
                      hover:bg-rose-600 hover:scale-105 active:scale-95
                      transition-all duration-200">
                <div>Advanced</div>
                <div class="text-sm opacity-80 font-body mt-1">6×4 grid · 12 pairs · 1 min 30 sec</div>
            </a>
            <a href="game.php?difficulty=asian"
               class="rounded-xl px-6 py-5 text-center font-display font-semibold text-lg
                      bg-red-900 text-yellow-300 shadow-lg
                      hover:bg-red-950 hover:scale-105 active:scale-95
                      transition-all duration-200">
                <div>Asian</div>
                <div class="text-sm opacity-80 font-body mt-1">10×8 grid · 40 pairs · 1 min 30 sec</div>
            </a>
        </div>

        <a href="endless.php?action=start"
           class="w-full rounded-xl px-6 py-5 text-center font-display font-semibold text-lg
                  bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg
                  hover:from-purple-700 hover:to-indigo-700 hover:scale-105 active:scale-95
                  transition-all duration-200">
            <div>Endless Mode</div>
            <div class="text-sm opacity-80 font-body mt-1">7 rounds · Beginner → Asian · Score for points</div>
        </a>

        <p class="text-gray-400 text-sm text-center">Match all pairs to win. Good luck! 🍀</p>
    </div>

    <!-- Info Button -->
    <button onclick="document.getElementById('aboutModal').classList.remove('hidden')"
            class="fixed bottom-5 right-5 w-10 h-10 rounded-full bg-purple-600 text-white text-lg font-bold shadow-lg hover:bg-purple-700 hover:scale-110 active:scale-95 transition-all duration-200 flex items-center justify-center"
            aria-label="About">
        i
    </button>

    <!-- About Modal -->
    <div id="aboutModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 shadow-2xl max-w-sm w-full mx-4 space-y-4 animate-bounce-in">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl font-bold text-purple-700">About Purble Pairs</h2>
                <button onclick="document.getElementById('aboutModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <p class="text-gray-500 text-sm">A fun memory card matching game built by our team in compliance to ITEC 106 and ITEC 100A.</p>
            <div>
                <h3 class="font-display text-lg font-semibold text-gray-700 mb-2">👥 The Team</h3>
                <ul class="space-y-1 text-gray-600 text-sm list-disc list-inside">
                    <li>Austria, Xyzine G.</li>
                    <li>Candelaria, Jane Ashley R.</li>
                    <li>Ramirez, Lee Johnrich H.</li>
                </ul>
            </div>
        </div>
    </div>

</body>
</html>
