<?php
/**
 * Admin — Endless Leaderboard Management
 */
require 'auth_guard.php';
require '../includes/db.php';
require 'layout.php';

$flash = $_SESSION['admin_flash'] ?? [];
unset($_SESSION['admin_flash']);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete_entry') {
        $id = (int)($_POST['entry_id'] ?? 0);
        $del = $conn->prepare('DELETE FROM endless_leaderboard WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        $_SESSION['admin_flash'] = ['success' => 'Entry deleted.'];
    }

    if ($postAction === 'clear_all') {
        $conn->query('DELETE FROM endless_leaderboard');
        $_SESSION['admin_flash'] = ['success' => 'All endless entries cleared.'];
    }

    header('Location: endless.php');
    exit;
}

// ── Fetch entries ─────────────────────────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$totalCount = $conn->query('SELECT COUNT(*) FROM endless_leaderboard')->fetch_row()[0];

$stmt = $conn->prepare(
    'SELECT el.id, u.username, el.score, el.reached_difficulty, el.created_at
     FROM endless_leaderboard el JOIN users u ON u.id = el.user_id
     ORDER BY el.score DESC
     LIMIT ? OFFSET ?'
);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$entries    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = (int)ceil($totalCount / $limit);

$badgeClass = [
    'beginner'     => 'bg-emerald-100 text-emerald-700',
    'intermediate' => 'bg-amber-100 text-amber-700',
    'advanced'     => 'bg-rose-100 text-rose-700',
    'asian'        => 'bg-red-900 text-yellow-300',
];

adminHeader('Endless Leaderboard', 'endless');
?>

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold text-gray-800">Endless Leaderboard</h1>
            <p class="text-gray-400 text-sm mt-0.5"><?php echo number_format($totalCount); ?> entries</p>
        </div>

        <?php if ($totalCount > 0): ?>
        <form method="POST" action="endless.php"
              onsubmit="return confirm('Clear ALL endless leaderboard entries? This cannot be undone.')">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 text-sm font-semibold transition-colors">
                Clear All Entries
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Flash -->
    <?php if (!empty($flash['success'])): ?>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
        <?php echo htmlspecialchars($flash['success']); ?>
    </div>
    <?php elseif (!empty($flash['error'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm">
        <?php echo htmlspecialchars($flash['error']); ?>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($entries)): ?>
        <p class="px-5 py-12 text-center text-gray-400">No endless entries yet.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm font-body">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Player</th>
                    <th class="px-5 py-3 text-center">Score</th>
                    <th class="px-5 py-3 text-center">Reached</th>
                    <th class="px-5 py-3 text-right hidden sm:table-cell">Date</th>
                    <th class="px-5 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($entries as $i => $e):
                    $rank  = $offset + $i + 1;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-center text-gray-500 font-semibold"><?php echo $rank; ?></td>
                    <td class="px-5 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($e['username']); ?></td>
                    <td class="px-5 py-3 text-center font-bold text-purple-700"><?php echo number_format($e['score']); ?></td>
                    <td class="px-5 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClass[$e['reached_difficulty']] ?? ''; ?>">
                            <?php echo ucfirst($e['reached_difficulty']); ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-gray-400 text-xs hidden sm:table-cell">
                        <?php echo date('M j, Y', strtotime($e['created_at'])); ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <form method="POST" action="endless.php"
                              onsubmit="return confirm('Delete this entry?')">
                            <input type="hidden" name="action"   value="delete_entry">
                            <input type="hidden" name="entry_id" value="<?php echo $e['id']; ?>">
                            <button type="submit"
                                    class="px-2 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 text-xs font-semibold transition-colors">
                                Delete
                            </button>
                        </form>
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
        <a href="endless.php?page=<?php echo $p; ?>"
           class="px-3 py-1.5 rounded-lg font-semibold transition-colors
                  <?php echo $p === $page ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 hover:bg-purple-50 border border-gray-200'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

<?php adminFooter(); ?>
