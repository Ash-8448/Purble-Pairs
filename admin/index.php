<?php
/**
 * Admin — Dashboard
 */
require 'auth_guard.php';
require '../includes/db.php';
require 'layout.php';

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalUsers   = $conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
$totalGames   = $conn->query('SELECT COUNT(*) FROM leaderboard')->fetch_row()[0];
$totalEndless = $conn->query('SELECT COUNT(*) FROM endless_leaderboard')->fetch_row()[0];
$topScore     = $conn->query('SELECT MAX(score) FROM endless_leaderboard')->fetch_row()[0] ?? 0;

// ── Recent registrations (last 5) ─────────────────────────────────────────────
$recentUsers = $conn->query(
    'SELECT username, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

// ── Recent classic wins (last 5) ──────────────────────────────────────────────
$recentWins = $conn->query(
    'SELECT u.username, l.difficulty, l.moves, l.time, l.created_at
     FROM leaderboard l JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$badgeClass = [
    'beginner'     => 'bg-emerald-100 text-emerald-700',
    'intermediate' => 'bg-amber-100 text-amber-700',
    'advanced'     => 'bg-rose-100 text-rose-700',
    'asian'        => 'bg-red-900 text-yellow-300',
];

function fmtTime(int $s): string {
    return floor($s / 60) . ':' . str_pad($s % 60, 2, '0', STR_PAD_LEFT);
}

adminHeader('Dashboard', 'dashboard');
?>

<div class="space-y-6">

    <div>
        <h1 class="font-display text-2xl font-bold text-gray-800">Dashboard</h1>
        <p class="text-gray-400 text-sm mt-0.5">Overview of Purble Pairs activity.</p>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        statCard('', 'Total Users',      number_format($totalUsers),   'purple');
        statCard('', 'Classic Wins',     number_format($totalGames),   'emerald');
        statCard('', 'Endless Sessions', number_format($totalEndless), 'amber');
        statCard('', 'Top Endless Score',number_format($topScore),     'rose');
        ?>
    </div>

    <div class="grid md:grid-cols-2 gap-6">

        <!-- Recent registrations -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-display font-semibold text-gray-700">Recent Registrations</h2>
                <a href="users.php" class="text-xs text-purple-600 hover:underline">View all →</a>
            </div>
            <?php if (empty($recentUsers)): ?>
            <p class="px-5 py-8 text-center text-gray-400 text-sm">No users yet.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($recentUsers as $u): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 font-semibold text-gray-700">
                            <?php echo htmlspecialchars($u['username']); ?>
                            <?php if ($u['is_admin']): ?>
                            <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">admin</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-400 text-xs">
                            <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent classic wins -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-display font-semibold text-gray-700">Recent Classic Wins</h2>
                <a href="leaderboard.php" class="text-xs text-purple-600 hover:underline">View all →</a>
            </div>
            <?php if (empty($recentWins)): ?>
            <p class="px-5 py-8 text-center text-gray-400 text-sm">No wins recorded yet.</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($recentWins as $w): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($w['username']); ?></td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClass[$w['difficulty']] ?? ''; ?>">
                                <?php echo ucfirst($w['difficulty']); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs"><?php echo $w['moves']; ?> moves · <?php echo fmtTime((int)$w['time']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php adminFooter(); ?>
