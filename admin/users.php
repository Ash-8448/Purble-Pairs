<?php
/**
 * Admin — Users (read-only view)
 */
require 'auth_guard.php';
require '../includes/db.php';
require 'layout.php';

// ── Fetch users ───────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

if ($search !== '') {
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ?');
    $like = '%' . $search . '%';
    $countStmt->bind_param('s', $like);
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $stmt = $conn->prepare(
        'SELECT u.id, u.username, u.is_admin, u.created_at,
                (SELECT COUNT(*) FROM leaderboard WHERE user_id = u.id) AS classic_wins,
                (SELECT COUNT(*) FROM endless_leaderboard WHERE user_id = u.id) AS endless_sessions
         FROM users u
         WHERE u.username LIKE ?
         ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('sii', $like, $limit, $offset);
} else {
    $totalCount = $conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];

    $stmt = $conn->prepare(
        'SELECT u.id, u.username, u.is_admin, u.created_at,
                (SELECT COUNT(*) FROM leaderboard WHERE user_id = u.id) AS classic_wins,
                (SELECT COUNT(*) FROM endless_leaderboard WHERE user_id = u.id) AS endless_sessions
         FROM users u
         ORDER BY u.created_at DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ii', $limit, $offset);
}

$stmt->execute();
$users      = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = (int)ceil($totalCount / $limit);

adminHeader('Users', 'users');
?>

<div class="space-y-5">

    <div>
        <h1 class="font-display text-2xl font-bold text-gray-800">Users</h1>
        <p class="text-gray-400 text-sm mt-0.5"><?php echo number_format($totalCount); ?> registered players</p>
    </div>

    <!-- Search -->
    <form method="GET" action="users.php" class="flex gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
               placeholder="Search by username…"
               class="flex-1 px-4 py-2 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none text-sm font-body">
        <button type="submit"
                class="px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition-colors">
            Search
        </button>
        <?php if ($search !== ''): ?>
        <a href="users.php"
           class="px-4 py-2 rounded-xl bg-gray-200 text-gray-600 text-sm font-semibold hover:bg-gray-300 transition-colors">
            Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- Users table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($users)): ?>
        <p class="px-5 py-12 text-center text-gray-400">No users found.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm font-body">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3 text-left">Username</th>
                    <th class="px-5 py-3 text-center">Role</th>
                    <th class="px-5 py-3 text-center">Classic Wins</th>
                    <th class="px-5 py-3 text-center">Endless</th>
                    <th class="px-5 py-3 text-right">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-semibold text-gray-800">
                        <?php echo htmlspecialchars($u['username']); ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <?php if ($u['is_admin']): ?>
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Admin</span>
                        <?php else: ?>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold">Player</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center text-gray-600"><?php echo $u['classic_wins']; ?></td>
                    <td class="px-5 py-3 text-center text-gray-600"><?php echo $u['endless_sessions']; ?></td>
                    <td class="px-5 py-3 text-right text-gray-400 text-xs">
                        <?php echo date('M j, Y', strtotime($u['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="users.php?page=<?php echo $p; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>"
           class="px-3 py-1.5 rounded-lg font-semibold transition-colors
                  <?php echo $p === $page ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 hover:bg-purple-50 border border-gray-200'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

<?php adminFooter(); ?>
