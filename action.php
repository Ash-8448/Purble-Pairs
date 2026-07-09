<?php
/**
 * Purble Pairs — AJAX Action Handler
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['auth'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Admins have no game session — reject any game actions
if (!empty($_SESSION['is_admin'])) {
    echo json_encode(['error' => 'Admins cannot play']);
    exit;
}

require 'includes/db.php';

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true);
$action   = $input['action'] ?? '';


// ── Classic: flip ─────────────────────────────────────────────────────────────
if ($action === 'flip') {
    $game  = &$_SESSION['game'];
    $c1    = (int)$input['card1'];
    $c2    = (int)$input['card2'];
    $total = count($game['cards']);

    if ($c1 === $c2 || $c1 < 0 || $c1 >= $total || $c2 < 0 || $c2 >= $total
        || $game['matched'][$c1] || $game['matched'][$c2]) {
        echo json_encode(['error' => 'Invalid flip']);
        exit;
    }

    $elapsed = time() - $game['startTime'];
    if ($elapsed >= $game['timeLimit']) {
        $game['status'] = 'lost';
        echo json_encode(['error' => 'Time up', 'status' => 'lost']);
        exit;
    }

    $match = ($game['cards'][$c1] === $game['cards'][$c2]);

    if ($match) {
        $game['matched'][$c1] = true;
        $game['matched'][$c2] = true;
        if (!in_array(false, $game['matched'], true)) {
            $game['status'] = 'won';
        }
    }

    echo json_encode(['match' => $match, 'moves' => $game['moves'], 'status' => $game['status']]);
    exit;
}


// ── Classic: end ──────────────────────────────────────────────────────────────
if ($action === 'end') {
    $game = &$_SESSION['game'];

    $receivedStatus = $input['status'] ?? '';
    $game['status'] = in_array($receivedStatus, ['won', 'lost']) ? $receivedStatus : 'lost';

    if (isset($input['moves'])) {
        $game['moves'] = (int)$input['moves'];
    }

    if ($game['status'] === 'won') {
        $elapsed = time() - $game['startTime'];

        $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $_SESSION['auth']);
        $stmt->execute();
        $row    = $stmt->get_result()->fetch_assoc();
        $userId = $row['id'] ?? null;
        $stmt->close();

        if ($userId) {
            $best = $conn->prepare('SELECT moves, time FROM leaderboard WHERE user_id = ? AND difficulty = ? ORDER BY moves ASC, time ASC LIMIT 1');
            $best->bind_param('is', $userId, $game['difficulty']);
            $best->execute();
            $bestRow = $best->get_result()->fetch_assoc();
            $best->close();

            $isNewBest = !$bestRow
                || $game['moves'] < $bestRow['moves']
                || ($game['moves'] === $bestRow['moves'] && $elapsed < $bestRow['time']);

            if ($isNewBest) {
                $del = $conn->prepare('DELETE FROM leaderboard WHERE user_id = ? AND difficulty = ?');
                $del->bind_param('is', $userId, $game['difficulty']);
                $del->execute();
                $del->close();

                $ins = $conn->prepare('INSERT INTO leaderboard (user_id, difficulty, moves, time) VALUES (?, ?, ?, ?)');
                $ins->bind_param('isii', $userId, $game['difficulty'], $game['moves'], $elapsed);
                $ins->execute();
                $ins->close();
            }
        }
    }

    echo json_encode(['status' => $game['status'], 'moves' => $game['moves']]);
    exit;
}


// ── Endless: flip ─────────────────────────────────────────────────────────────
if ($action === 'endless_flip') {
    if (empty($_SESSION['endless_round']) || empty($_SESSION['endless'])) {
        echo json_encode(['error' => 'No endless round active']);
        exit;
    }

    $round   = &$_SESSION['endless_round'];
    $endless = &$_SESSION['endless'];

    $c1    = (int)$input['card1'];
    $c2    = (int)$input['card2'];
    $total = count($round['cards']);

    if ($c1 === $c2 || $c1 < 0 || $c1 >= $total || $c2 < 0 || $c2 >= $total
        || $round['matched'][$c1] || $round['matched'][$c2]) {
        echo json_encode(['error' => 'Invalid flip']);
        exit;
    }

    $elapsed = time() - $round['startTime'];
    if ($elapsed >= $round['timeLimit']) {
        $round['status'] = 'timeout';
        echo json_encode(['error' => 'Time up', 'status' => 'timeout']);
        exit;
    }

    $match = ($round['cards'][$c1] === $round['cards'][$c2]);

    if ($match) {
        $round['matched'][$c1] = true;
        $round['matched'][$c2] = true;
        $endless['totalScore'] += $round['ptsPerCard'] * 2;

        if (!in_array(false, $round['matched'], true)) {
            $round['status'] = 'won';
        }
    }

    echo json_encode(['match' => $match, 'totalScore' => $endless['totalScore'], 'status' => $round['status']]);
    exit;
}


// ── Endless: end round ────────────────────────────────────────────────────────
if ($action === 'endless_end') {
    if (empty($_SESSION['endless_round']) || empty($_SESSION['endless'])) {
        echo json_encode(['error' => 'No endless round active']);
        exit;
    }

    $round   = &$_SESSION['endless_round'];
    $endless = &$_SESSION['endless'];

    $receivedStatus = $input['status'] ?? '';
    $status = in_array($receivedStatus, ['won', 'timeout']) ? $receivedStatus : 'timeout';

    if (isset($input['moves'])) {
        $round['moves'] = (int)$input['moves'];
    }

    $deduction = 0;

    if ($status === 'timeout') {
        $unmatchedCount = count(array_filter($round['matched'], fn($m) => $m === false));
        $deduction      = $unmatchedCount * $round['ptsPerCard'];
        $endless['totalScore'] = max(0, $endless['totalScore'] - $deduction);
    }

    $totalRounds           = 7;
    $endless['roundIndex'] += 1;
    $sessionOver           = false;
    $reachedDiff           = $round['diffKey'];

    if ($endless['roundIndex'] >= $totalRounds) {
        $endless['status'] = 'finished';
        $sessionOver       = true;
        $saveResult        = saveEndlessScore($conn, $endless['totalScore'], $reachedDiff);
    }

    echo json_encode([
        'totalScore'     => $endless['totalScore'],
        'deduction'      => $deduction,
        'sessionOver'    => $sessionOver,
        'roundIndex'     => $endless['roundIndex'],
        'isNewHighScore' => $saveResult['isNewHighScore'] ?? false,
        'oldHighScore'   => $saveResult['oldHighScore']   ?? null,
    ]);
    exit;
}


// ── Endless: opt-out ──────────────────────────────────────────────────────────
if ($action === 'endless_opt_out') {
    if (empty($_SESSION['endless'])) {
        echo json_encode(['error' => 'No endless session']);
        exit;
    }

    $endless           = &$_SESSION['endless'];
    $endless['status'] = 'opted_out';

    $reachedDiff = $_SESSION['endless_round']['diffKey'] ?? 'beginner';
    $saveResult  = saveEndlessScore($conn, $endless['totalScore'], $reachedDiff);

    echo json_encode([
        'totalScore'     => $endless['totalScore'],
        'reachedDiff'    => $reachedDiff,
        'isNewHighScore' => $saveResult['isNewHighScore'],
        'oldHighScore'   => $saveResult['oldHighScore'],
    ]);
    exit;
}


// ── Helper: save endless score ────────────────────────────────────────────────
// Returns ['saved' => bool, 'isNewHighScore' => bool, 'oldHighScore' => int|null]
function saveEndlessScore($conn, $score, $reachedDiff): array {
    if (empty($_SESSION['auth'])) return ['saved' => false, 'isNewHighScore' => false, 'oldHighScore' => null];

    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->bind_param('s', $_SESSION['auth']);
    $stmt->execute();
    $row    = $stmt->get_result()->fetch_assoc();
    $userId = $row['id'] ?? null;
    $stmt->close();

    if (!$userId) return ['saved' => false, 'isNewHighScore' => false, 'oldHighScore' => null];

    $best    = $conn->prepare('SELECT score FROM endless_leaderboard WHERE user_id = ? ORDER BY score DESC LIMIT 1');
    $best->bind_param('i', $userId);
    $best->execute();
    $bestRow = $best->get_result()->fetch_assoc();
    $best->close();

    $oldHighScore   = $bestRow ? (int)$bestRow['score'] : null;
    $isNewHighScore = $score > 0 && (!$bestRow || $score > $bestRow['score']);

    if ($isNewHighScore) {
        $del = $conn->prepare('DELETE FROM endless_leaderboard WHERE user_id = ?');
        $del->bind_param('i', $userId);
        $del->execute();
        $del->close();

        $ins = $conn->prepare('INSERT INTO endless_leaderboard (user_id, score, reached_difficulty) VALUES (?, ?, ?)');
        $ins->bind_param('iis', $userId, $score, $reachedDiff);
        $ins->execute();
        $ins->close();
    }

    return ['saved' => $isNewHighScore, 'isNewHighScore' => $isNewHighScore, 'oldHighScore' => $oldHighScore];
}

echo json_encode(['error' => 'Unknown action']);
