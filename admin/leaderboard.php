<?php
/**
 * Admin — Classic Leaderboard Management
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
        $del = $conn->prepare('DELETE FROM leaderboard WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
        $_SESSION['admin_flash'] = ['success' => 'Entry deleted.'];
    }

    if ($postAction === 'clear_difficulty') {
        $diff    = $_POST['difficulty'] ?? '';
        $allowed = ['beginner', 'intermediate', 'advanced', 'asian'];
        if (in_array($diff, $allowed)) {
            $del = $conn->prepare('DELETE FROM leaderboard WHERE difficulty = ?');
            $del->bind_param('s', $diff);
            $del->execute();
            $del->close();
            $_SESSION['admin_flash'] = ['success' => "All {$diff} entries cleared."];
        }
    }

    header('Location: leaderboard.php' . (isset($_GET['difficulty']) ? '?difficulty=' . urlencode($_GET['difficulty']) : ''));
    exit;
}

// ── Fetch entries ─────────────────────────────────────────────────────────────
$allowed = ['beginner', 'intermediate', 'advanced', 'asian'];
$filter  = (isset($_GET['difficulty']) && in_array($_GET['difficulty'], $allowed))
           ? $_GET['difficulty'] : 'all';

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

if ($filter === 'all') {
    $totalCount = $conn->query('SELECT COUNT(*) FROM leaderboard')->fetch_row()[0];
    $stmt = $conn->prepare(
        'SELECT l.id, u.username, l.difficulty, l.moves, l.time, l.created_at
         FROM leaderboard l JOIN users u ON u.id = l.user_id
         ORDER BY l.difficulty, l.moves ASC, l.time ASC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('ii', $limit, $offset);
} else {
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM leaderboard WHERE difficulty = ?');
    $countStmt->bind_param('s', $filter);
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_row()[0];
    $countStmt->close();

    $stmt = $conn->prepare(
        'SELECT l.id, u.username, l.difficulty, l.moves, l.time, l.created_at
         FROM leaderboard l JOIN users u ON u.id = l.user_id
         WHERE l.difficulty = ?
         ORDER BY l.moves ASC, l.time ASC
         LIMIT ? OFFSET ?'
    );
    $stmt->bind_param('sii', $filter, $limit, $offset);
}

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

function fmtTime(int $s): string {
    return floor($s / 60) . ':' . str_pad($s % 60, 2, '0', STR_PAD_LEFT);
}

adminHeader('Classic Leaderboard', 'leaderboard');
?>

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold text-gray-800">Classic Leaderboard</h1>
            <p class="text-gray-400 text-sm mt-0.5"><?php echo number_format($totalCount); ?> entries</p>
        </div>

        <!-- Clear difficulty -->
        <?php if ($filter !== 'all'): ?>
        <form method="POST" action="leaderboard.php?difficulty=<?php echo $filter; ?>"
              onsubmit="return confirm('Clear ALL <?php echo $filter; ?> entries? This cannot be undone.')">
            <input type="hidden" name="action"     value="clear_difficulty">
            <input type="hidden" name="difficulty" value="<?php echo $filter; ?>">
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 text-sm font-semibold transition-colors">
                Clear All <?php echo ucfirst($filter); ?>
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

    <!-- Difficulty filter tabs -->
    <div class="flex rounded-xl overflow-hidden border-2 border-purple-100 bg-white">
        <?php
        $tabs = ['all' => 'All', 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced', 'asian' => 'Asian'];
        foreach ($tabs as $val => $label):
            $active = ($filter === $val);
        ?>
        <a href="leaderboard.php<?php echo $val === 'all' ? '' : '?difficulty=' . $val; ?>"
           class="flex-1 py-2 text-center font-display font-semibold text-sm transition-colors
                  <?php echo $active ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-purple-600'; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($entries)): ?>
        <p class="px-5 py-12 text-center text-gray-400">No entries found.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm font-body">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Player</th>
                    <?php if ($filter === 'all'): ?>
                    <th class="px-5 py-3 text-left">Difficulty</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 text-center">Moves</th>
                    <th class="px-5 py-3 text-center">Time</th>
                    <th class="px-5 py-3 text-right hidden sm:table-cell">Date</th>
                    <th class="px-5 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($entries as $i => $e): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 text-gray-400 text-xs"><?php echo $offset + $i + 1; ?></td>
                    <td class="px-5 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($e['username']); ?></td>
                    <?php if ($filter === 'all'): ?>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClass[$e['difficulty']] ?? ''; ?>">
                            <?php echo ucfirst($e['difficulty']); ?>
                        </span>
                    </td>
                    <?php endif; ?>
                    <td class="px-5 py-3 text-center text-gray-600"><?php echo $e['moves']; ?></td>
                    <td class="px-5 py-3 text-center text-gray-600"><?php echo fmtTime((int)$e['time']); ?></td>
                    <td class="px-5 py-3 text-right text-gray-400 text-xs hidden sm:table-cell">
                        <?php echo date('M j, Y', strtotime($e['created_at'])); ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <form method="POST" action="leaderboard.php<?php echo $filter !== 'all' ? '?difficulty=' . $filter : ''; ?>"
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
        <a href="leaderboard.php?page=<?php echo $p; ?><?php echo $filter !== 'all' ? '&difficulty=' . $filter : ''; ?>"
           class="px-3 py-1.5 rounded-lg font-semibold transition-colors
                  <?php echo $p === $page ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 hover:bg-purple-50 border border-gray-200'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

<?php adminFooter(); ?>
