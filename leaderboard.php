<?php
/**
 * Purble Pairs — Leaderboard
 */
session_start();
require 'includes/db.php';

if (empty($_SESSION['auth'])) {
    header('Location: auth.php');
    exit;
}

// Admins cannot access the player leaderboard — redirect to admin panel
if (!empty($_SESSION['is_admin'])) {
    header('Location: admin/index.php');
    exit;
}

$tab     = (isset($_GET['tab']) && $_GET['tab'] === 'endless') ? 'endless' : 'normal';
$allowed = ['beginner', 'intermediate', 'advanced', 'asian'];
$filter  = (isset($_GET['difficulty']) && in_array($_GET['difficulty'], $allowed))
           ? $_GET['difficulty'] : 'all';

if ($tab === 'endless') {
    $sql    = 'SELECT u.username, el.score, el.reached_difficulty, el.created_at
               FROM endless_leaderboard el
               JOIN users u ON u.id = el.user_id
               ORDER BY el.score DESC LIMIT 20';
    $result = $conn->query($sql);
    $rows   = $result->fetch_all(MYSQLI_ASSOC);
} else {
    if ($filter === 'all') {
        $sql    = 'SELECT u.username, l.difficulty, l.moves, l.time, l.created_at
                   FROM leaderboard l
                   JOIN users u ON u.id = l.user_id
                   ORDER BY l.difficulty, l.moves ASC, l.time ASC LIMIT 20';
        $result = $conn->query($sql);
    } else {
        $stmt = $conn->prepare('SELECT u.username, l.difficulty, l.moves, l.time, l.created_at
                                FROM leaderboard l
                                JOIN users u ON u.id = l.user_id
                                WHERE l.difficulty = ?
                                ORDER BY l.moves ASC, l.time ASC LIMIT 20');
        $stmt->bind_param('s', $filter);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    $rows = $result->fetch_all(MYSQLI_ASSOC);
}

function fmtTime(int $secs): string {
    return floor($secs / 60) . ':' . str_pad($secs % 60, 2, '0', STR_PAD_LEFT);
}

$badgeClass = [
    'beginner'     => 'bg-emerald-100 text-emerald-700',
    'intermediate' => 'bg-amber-100 text-amber-700',
    'advanced'     => 'bg-rose-100 text-rose-700',
    'asian'        => 'bg-red-900 text-yellow-300',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purble Pairs — Leaderboard</title>
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
                        body:    ['Nunito', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes bounce-in {
            0%   { transform: scale(0.3); opacity: 0; }
            50%  { transform: scale(1.1); }
            70%  { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce-in { animation: bounce-in 0.4s ease-out; }
    </style>
</head>
<body class="bg-purple-50 font-body min-h-screen px-4 py-10">

    <div class="max-w-2xl mx-auto flex items-center justify-between mb-8">
        <a href="index.php" class="text-sm text-gray-400 hover:text-purple-600 transition-colors">Back to Home</a>
    </div>

    <div class="max-w-2xl mx-auto space-y-6 animate-bounce-in">

        <div class="text-center space-y-1">
            <div class="text-4xl">🏆</div>
            <h1 class="font-display text-4xl font-bold text-purple-700">Leaderboard</h1>
            <p class="text-gray-400 text-sm">
                <?php echo $tab === 'endless'
                    ? 'Endless Mode — top scores.'
                    : 'Top 20 scores — fewest moves wins, then fastest time.'; ?>
            </p>
        </div>

        <!-- Normal / Endless tabs -->
        <div class="flex rounded-xl overflow-hidden border-2 border-purple-100 bg-white">
            <a href="leaderboard.php"
               class="flex-1 py-2 text-center font-display font-semibold text-sm transition-colors
                      <?php echo $tab === 'normal' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-purple-600'; ?>">
                🎮 Classic
            </a>
            <a href="leaderboard.php?tab=endless"
               class="flex-1 py-2 text-center font-display font-semibold text-sm transition-colors
                      <?php echo $tab === 'endless' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-purple-600'; ?>">
                ♾️ Endless
            </a>
        </div>

        <?php if ($tab === 'normal'): ?>
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
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
            <?php if (empty($rows)): ?>
                <div class="py-16 text-center text-gray-400 font-body">
                    <p>No scores yet. Be the first to win!</p>
                </div>
            <?php elseif ($tab === 'endless'): ?>
            <table class="w-full text-sm font-body">
                <thead>
                    <tr class="bg-purple-50 text-gray-500 text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left w-10">#</th>
                        <th class="px-4 py-3 text-left">Player</th>
                        <th class="px-4 py-3 text-center">Score</th>
                        <th class="px-4 py-3 text-center">Reached</th>
                        <th class="px-4 py-3 text-right hidden sm:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $i => $row):
                        $rank  = $i + 1;
                        $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
                        $isMe  = ($row['username'] === $_SESSION['auth']);
                    ?>
                    <tr class="<?php echo $isMe ? 'bg-purple-50' : 'hover:bg-gray-50'; ?> transition-colors">
                        <td class="px-4 py-3 font-semibold text-gray-500 text-center"><?php echo $medal; ?></td>
                        <td class="px-4 py-3 font-semibold <?php echo $isMe ? 'text-purple-700' : 'text-gray-700'; ?>">
                            <?php echo htmlspecialchars($row['username']); ?>
                            <?php if ($isMe): ?><span class="ml-1 text-xs text-purple-400">(you)</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-purple-700"><?php echo number_format($row['score']); ?></td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClass[$row['reached_difficulty']] ?? ''; ?>">
                                <?php echo ucfirst($row['reached_difficulty']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-400 text-xs hidden sm:table-cell">
                            <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <table class="w-full text-sm font-body">
                <thead>
                    <tr class="bg-purple-50 text-gray-500 text-xs uppercase tracking-wide">
                        <th class="px-4 py-3 text-left w-10">#</th>
                        <th class="px-4 py-3 text-left">Player</th>
                        <?php if ($filter === 'all'): ?>
                        <th class="px-4 py-3 text-left">Difficulty</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-center">Moves</th>
                        <th class="px-4 py-3 text-center">Time</th>
                        <th class="px-4 py-3 text-right hidden sm:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($rows as $i => $row):
                        $rank  = $i + 1;
                        $medal = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
                        $isMe  = ($row['username'] === $_SESSION['auth']);
                    ?>
                    <tr class="<?php echo $isMe ? 'bg-purple-50' : 'hover:bg-gray-50'; ?> transition-colors">
                        <td class="px-4 py-3 font-semibold text-gray-500 text-center"><?php echo $medal; ?></td>
                        <td class="px-4 py-3 font-semibold <?php echo $isMe ? 'text-purple-700' : 'text-gray-700'; ?>">
                            <?php echo htmlspecialchars($row['username']); ?>
                            <?php if ($isMe): ?><span class="ml-1 text-xs text-purple-400">(you)</span><?php endif; ?>
                        </td>
                        <?php if ($filter === 'all'): ?>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeClass[$row['difficulty']] ?? ''; ?>">
                                <?php echo ucfirst($row['difficulty']); ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-center text-gray-600"><?php echo $row['moves']; ?></td>
                        <td class="px-4 py-3 text-center text-gray-600"><?php echo fmtTime((int)$row['time']); ?></td>
                        <td class="px-4 py-3 text-right text-gray-400 text-xs hidden sm:table-cell">
                            <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>
