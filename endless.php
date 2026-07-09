<?php
// Start the session so we can read and write session data throughout the page
session_start();

// Redirect unauthenticated users to the login page
if (empty($_SESSION['auth'])) {
    header('Location: auth.php');
    exit;
}

// Redirect users who haven't set a player name to the home page
if (empty($_SESSION['player_name'])) {
    header('Location: index.php');
    exit;
}

// Admins cannot play — redirect to admin panel
if (!empty($_SESSION['is_admin'])) {
    header('Location: admin/index.php');
    exit;
}

// Store the player's display name for use in the UI
$playerName = $_SESSION['player_name'];

// Load card helper functions (getDifficultyConfig, renderCardGrid, $allEmojis, etc.)
require 'includes/cards.php';

// Define the ordered sequence of rounds for endless mode.
// Each entry is [difficulty key, round number within that difficulty].
$sequence = [
    ['beginner',     1],
    ['beginner',     2],
    ['intermediate', 1],
    ['intermediate', 2],
    ['advanced',     1],
    ['advanced',     2],
    ['asian',        1],
];
$totalRounds = count($sequence);

// Check if the player is starting a fresh session via ?action=start
$action = $_GET['action'] ?? '';

// Initialize (or reset) the endless session data when starting fresh or if no session exists yet
if ($action === 'start' || empty($_SESSION['endless'])) {
    $_SESSION['endless'] = [
        'roundIndex' => 0,   // Which round the player is currently on (0-based)
        'totalScore' => 0,   // Cumulative score across all rounds
        'status'     => 'playing',
    ];
}

// Get a reference to the endless session so changes persist automatically
$endless = &$_SESSION['endless'];

// If the session is no longer active (finished or opted out), send the player home
if ($endless['status'] !== 'playing') {
    header('Location: index.php');
    exit;
}

// Read the current round index from the session
$roundIndex = $endless['roundIndex'];

// If all rounds have been completed, mark the session finished and redirect home
if ($roundIndex >= $totalRounds) {
    $endless['status'] = 'finished';
    header('Location: index.php');
    exit;
}

// Resolve the difficulty key and round number for the current round
$diffKey  = $sequence[$roundIndex][0];
$roundNum = $sequence[$roundIndex][1];
$config   = getDifficultyConfig($diffKey); // Returns grid size, time limit, points per card, etc.

// Calculate how many cards and pairs are on the board for this round
$totalCards = $config['cols'] * $config['rows'];
$pairCount  = $totalCards / 2;

// Build the shuffled card deck: take the required number of emojis, duplicate them for pairs, then shuffle
$emojis = array_slice($allEmojis, 0, $pairCount);
$cards  = [...$emojis, ...$emojis];
shuffle($cards);

// Save the current round's state to the session so action.php can validate flips and track progress
$_SESSION['endless_round'] = [
    'diffKey'    => $diffKey,
    'roundNum'   => $roundNum,
    'roundIndex' => $roundIndex,
    'cards'      => $cards,
    'matched'    => array_fill(0, $totalCards, false), // Tracks which card positions have been matched
    'ptsPerCard' => $config['pts'],
    'timeLimit'  => $config['time'],
    'startTime'  => time(),
    'status'     => 'playing',
];

// Map each difficulty to its Tailwind badge color classes for the UI label
$diffBadgeColors = [
    'beginner'     => 'bg-emerald-100 text-emerald-700',
    'intermediate' => 'bg-amber-100 text-amber-700',
    'advanced'     => 'bg-rose-100 text-rose-700',
    'asian'        => 'bg-red-900 text-yellow-300',
];
$diffBadge = $diffBadgeColors[$diffKey];

// Define the progress bar steps shown at the top of the page.
// Each step groups one or more rounds under a difficulty label.
$progressSteps = [
    ['key' => 'beginner',     'label' => 'Beginner',     'rounds' => 2],
    ['key' => 'intermediate', 'label' => 'Intermediate', 'rounds' => 2],
    ['key' => 'advanced',     'label' => 'Advanced',     'rounds' => 2],
    ['key' => 'asian',        'label' => 'Asian',        'rounds' => 1],
];

// Format the time limit as MM:SS for the on-screen countdown timer
$timerMinutes = floor($config['time'] / 60);
$timerSeconds = str_pad($config['time'] % 60, 2, '0', STR_PAD_LEFT);
$timerDisplay = "{$timerMinutes}:{$timerSeconds}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purble Pairs — Endless Mode</title>
    <!-- Tailwind CSS (local build) -->
    <script src="assets/tailwind.js"></script>
    <!-- Google Fonts: Fredoka (display headings) and Nunito (body text) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet"></noscript>
    <!-- Register custom font families with Tailwind -->
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
        /* 3-D card flip setup — perspective on the wrapper, preserve-3d on the inner element */
        .perspective { perspective: 600px; }
        .card-inner { transition: transform 0.5s; transform-style: preserve-3d; position: relative; }
        .card-inner.flipped { transform: rotateY(180deg); }

        /* Both card faces are stacked on top of each other; backface-visibility hides the rear face */
        .card-face { backface-visibility: hidden; position: absolute; inset: 0; }
        .card-back-face  { transform: rotateY(0deg); }   /* Visible by default (card face-down) */
        .card-front-face { transform: rotateY(180deg); } /* Revealed after flip */

        /* Green glow animation played once when a pair is successfully matched */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50%       { box-shadow: 0 0 20px 5px rgba(34,197,94,0.3); }
        }
        .matched-glow { animation: pulse-glow 1s ease-in-out 1; }

        /* Bounce-in animation used for overlay modals */
        @keyframes bounce-in {
            0%   { transform: scale(0.3); opacity: 0; }
            50%  { transform: scale(1.1); }
            70%  { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce-in { animation: bounce-in 0.5s ease-out; }
    </style>
</head>
<body class="bg-purple-50 font-body min-h-screen flex flex-col items-center px-4 py-6 gap-4">

    <!-- =====================================================================
         TOP BAR — mode label, difficulty badge, score, timer, move counter,
         and the "End Session" button
    ====================================================================== -->
    <div class="w-full max-w-2xl flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="font-display text-xl font-bold text-purple-700">♾️ Endless Mode</span>
                <!-- Difficulty badge: color changes per difficulty via $diffBadge -->
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $diffBadge; ?>">
                    <?php echo $config['label']; ?> · Round <?php echo $roundNum; ?>
                </span>
            </div>
            <!-- Sub-label showing the player name and overall round progress -->
            <p class="text-xs text-gray-400 mt-0.5">
                Playing as <span class="text-purple-600 font-semibold"><?php echo htmlspecialchars($playerName); ?></span>
                &nbsp;·&nbsp; Round <?php echo $roundIndex + 1; ?> of <?php echo $totalRounds; ?>
            </p>
        </div>
        <!-- Live stats: score, countdown timer, move counter, and opt-out button -->
        <div class="flex items-center gap-2 font-body text-sm font-semibold flex-wrap">
            <span id="scoreDisplay" class="px-3 py-1 rounded-lg bg-purple-100 text-purple-700">
                Score: <?php echo $endless['totalScore']; ?>
            </span>
            <span id="timer" class="px-3 py-1 rounded-lg bg-gray-200 text-gray-600"><?php echo $timerDisplay; ?></span>
            <span id="moveCounter" class="px-3 py-1 rounded-lg bg-gray-200 text-gray-600">Moves: 0</span>
            <!-- Opens the opt-out confirmation dialog -->
            <button onclick="confirmOptOut()"
                    class="px-3 py-1 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors">
                End Session
            </button>
        </div>
    </div>

    <!-- =====================================================================
         PROGRESS BAR — shows completion status for each difficulty group
    ====================================================================== -->
    <div class="w-full max-w-2xl">
        <div class="flex items-center gap-1 text-xs font-semibold font-body">
            <?php
            // Walk through each difficulty group and color-code its segment:
            //   - Fully completed group  → solid purple
            //   - Current active group   → light purple
            //   - Upcoming group         → gray
            $completedRounds = $roundIndex;
            $cursor = 0;
            foreach ($progressSteps as $si => $step):
                $stepRounds = $step['rounds'];
                $stepStart  = $cursor;
                $stepEnd    = $cursor + $stepRounds - 1;
                $cursor    += $stepRounds;
                $doneSoFar  = max(0, min($completedRounds - $stepStart, $stepRounds));
                $isCurrent  = ($roundIndex >= $stepStart && $roundIndex <= $stepEnd);
                $isDone     = ($completedRounds > $stepEnd);
                $barColor   = $isDone ? 'bg-purple-500 text-white' : ($isCurrent ? 'bg-purple-200 text-purple-700' : 'bg-gray-100 text-gray-400');
            ?>
            <div class="flex-1 rounded-lg px-2 py-1.5 text-center <?php echo $barColor; ?> transition-colors">
                <?php echo $step['label']; ?>
                <?php if ($step['rounds'] > 1): ?>
                    <!-- Show X/total sub-round count for multi-round difficulty groups -->
                    <span class="opacity-70">(<?php echo $doneSoFar; ?>/<?php echo $stepRounds; ?>)</span>
                <?php elseif ($isDone): ?>
                    <!-- Show a checkmark for single-round groups that are complete -->
                    <span class="opacity-70">✓</span>
                <?php endif; ?>
            </div>
            <?php if ($si < count($progressSteps) - 1): ?>
                <!-- Separator arrow between difficulty segments -->
                <span class="text-gray-300">›</span>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Render the card grid for the current round (output by renderCardGrid in cards.php) -->
    <?php renderCardGrid($cards, $config['cols']); ?>

    <!-- =====================================================================
         ROUND / SESSION END OVERLAY
         Shown when a round ends (won or timeout) or the full session completes.
         Content (icon, title, message, buttons) is injected dynamically by JS.
    ====================================================================== -->
    <div id="overlay" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm mx-4 space-y-4 animate-bounce-in">
            <div id="overlayIcon"    class="text-5xl"></div>
            <h3 id="overlayTitle"   class="font-display text-2xl font-bold text-gray-800"></h3>
            <p  id="overlayMessage" class="text-gray-500 font-body text-sm"></p>
            <div id="overlayButtons" class="flex gap-3 justify-center pt-2"></div>
        </div>
    </div>

    <!-- =====================================================================
         OPT-OUT CONFIRMATION OVERLAY
         Shown when the player clicks "End Session" mid-round.
         - optOutScoreMsg: displayed when the player has a score > 0
         - optOutZeroMsg:  displayed when the player has scored 0 points
    ====================================================================== -->
    <div id="optOutOverlay" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm mx-4 space-y-4 animate-bounce-in">
            <div class="text-5xl">🏳️</div>
            <h3 class="font-display text-2xl font-bold text-gray-800">End Session?</h3>
            <!-- Shown only when the player has points to keep -->
            <p id="optOutScoreMsg" class="text-gray-500 font-body text-sm">
                You'll keep all <strong id="optOutScore"><?php echo $endless['totalScore']; ?></strong> points
                without any deductions.<span id="optOutSaveNote"> Your score will be saved to the leaderboard.</span>
            </p>
            <!-- Shown only when the player has zero points -->
            <p id="optOutZeroMsg" class="text-gray-500 font-body text-sm hidden">
                You haven't scored any points yet. You'll be returned to the home screen.
            </p>
            <div class="flex gap-3 justify-center pt-2">
                <!-- Confirms the opt-out; behavior differs based on score (see doOptOut) -->
                <button onclick="doOptOut()"
                        class="px-5 py-2 rounded-xl bg-amber-500 text-white font-display font-semibold hover:bg-amber-600 transition-colors">
                    End It
                </button>
                <!-- Dismisses the dialog and lets the player continue -->
                <button onclick="closeOptOut()"
                        class="px-5 py-2 rounded-xl bg-gray-200 text-gray-600 font-display font-semibold hover:bg-gray-300 transition-colors">
                    Keep Playing
                </button>
            </div>
        </div>
    </div>

    <script>
        // ─── PHP → JS: inject server-side values needed by the game logic ───────
        var timeLimit   = <?php echo $config['time']; ?>;       // Round time limit in seconds
        var diffKey     = '<?php echo $diffKey; ?>';            // Current difficulty identifier
        var totalPairs  = <?php echo $pairCount; ?>;            // Number of pairs on the board
        var ptsPerCard  = <?php echo $config['pts']; ?>;        // Points awarded per matched pair
        var roundIndex  = <?php echo $roundIndex; ?>;           // Current round index (0-based)
        var totalRounds = <?php echo $totalRounds; ?>;          // Total number of rounds in the session
        // Asian difficulty uses a shorter flip-back delay to increase challenge
        var flipBackDelay = (diffKey === 'asian') ? 300 : 500;

        // ─── Runtime game state ──────────────────────────────────────────────────
        var timeLeft     = timeLimit;   // Seconds remaining on the countdown
        var moves        = 0;           // Total card flips made this round
        var flippedCards = [];          // Indices of currently face-up (unmatched) cards (max 2)
        var matchedCards = [];          // Indices of all successfully matched cards
        var isChecking   = false;       // Prevents new flips while a match check is in progress
        var gameOver     = false;       // Set to true when the round ends to lock all interactions
        var currentScore = <?php echo $endless['totalScore']; ?>; // Running total score across rounds

        // ─── COUNTDOWN TIMER ────────────────────────────────────────────────────
        // Ticks every second, updates the timer display, turns it red at ≤15 s,
        // and triggers endRound('timeout') when time runs out.
        var timerEl = document.getElementById('timer');
        var timerInterval = setInterval(function() {
            if (gameOver) return;
            timeLeft--;
            var minutes = Math.floor(timeLeft / 60);
            var seconds = String(timeLeft % 60).padStart(2, '0');
            timerEl.textContent = minutes + ':' + seconds;
            if (timeLeft <= 15) timerEl.className = 'px-3 py-1 rounded-lg bg-red-500 text-white';
            if (timeLeft <= 0) { clearInterval(timerInterval); endRound('timeout'); }
        }, 1000);

        // ─── flipCard(id) ────────────────────────────────────────────────────────
        // Called when the player clicks a card.
        // Flips the card visually, then — once two cards are face-up — sends both
        // indices to action.php for a server-side match check.
        function flipCard(id) {
            // Ignore clicks while the round is over or a check is already running
            if (gameOver || isChecking) return;
            // Ignore clicks on already-flipped or already-matched cards
            if (flippedCards.indexOf(id) !== -1 || matchedCards.indexOf(id) !== -1) return;
            // Only allow two cards face-up at a time
            if (flippedCards.length >= 2) return;

            // Flip the card visually
            document.getElementById('card-' + id).classList.add('flipped');
            flippedCards.push(id);

            // Once two cards are face-up, validate the pair with the server
            if (flippedCards.length === 2) {
                moves++;
                document.getElementById('moveCounter').textContent = 'Moves: ' + moves;
                isChecking = true;

                fetch('action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'endless_flip', card1: flippedCards[0], card2: flippedCards[1] })
                })
                .then(res => res.json())
                .then(function(data) {
                    if (data.match) {
                        // ── Match found ──────────────────────────────────────────
                        // Add both cards to the matched list and apply the green highlight
                        matchedCards.push(flippedCards[0], flippedCards[1]);
                        flippedCards.forEach(function(cardId) {
                            var inner = document.getElementById('card-' + cardId);
                            inner.classList.add('matched-glow');
                            // Swap the front-face style to the green "matched" appearance
                            inner.querySelector('.card-front-face').className =
                                'card-face card-front-face w-full h-full rounded-xl bg-emerald-50 flex items-center justify-center shadow-md border-2 border-emerald-400';
                        });
                        flippedCards = [];
                        isChecking   = false;
                        // Update the score display with the server-confirmed total
                        currentScore = data.totalScore;
                        document.getElementById('scoreDisplay').textContent = 'Score: ' + currentScore;
                        document.getElementById('optOutScore').textContent  = currentScore;
                        // If every pair is matched, end the round as a win
                        if (matchedCards.length === totalPairs * 2) {
                            clearInterval(timerInterval);
                            setTimeout(function() { endRound('won'); }, 400);
                        }
                    } else {
                        // ── No match ─────────────────────────────────────────────
                        // Flip both cards back after the delay defined by difficulty
                        var toFlipBack = flippedCards.slice();
                        setTimeout(function() {
                            toFlipBack.forEach(function(i) { document.getElementById('card-' + i).classList.remove('flipped'); });
                            flippedCards = [];
                            isChecking   = false;
                        }, flipBackDelay);
                    }
                })
                .catch(function() {
                    // On network error, flip cards back and reset state so the player can continue
                    flippedCards.forEach(function(i) { document.getElementById('card-' + i).classList.remove('flipped'); });
                    flippedCards = [];
                    isChecking   = false;
                });
            }
        }

        // ─── scoreMsg(data) ──────────────────────────────────────────────────────
        // Builds the score summary string shown in end-of-round/session overlays.
        // Highlights a new high score, shows the existing high score if not beaten,
        // or falls back to a plain score display.
        function scoreMsg(data) {
            if (data.isNewHighScore) {
                return 'Your new high score will be <strong>' + data.totalScore + '</strong>! 🎉';
            } else if (data.oldHighScore !== null && data.oldHighScore !== undefined) {
                return 'You haven\'t beaten your high score of <strong>' + data.oldHighScore + '</strong> yet.';
            } else {
                return 'Score: <strong>' + data.totalScore + '</strong>.';
            }
        }

        // ─── endRound(status) ────────────────────────────────────────────────────
        // Called when the round ends either by winning ('won') or running out of
        // time ('timeout'). Notifies the server, then shows the appropriate overlay
        // with options to continue to the next round or end the session.
        function endRound(status) {
            gameOver = true;
            fetch('action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'endless_end', status: status, moves: moves })
            })
            .then(res => res.json())
            .then(function(data) {
                currentScore = data.totalScore;
                document.getElementById('scoreDisplay').textContent = 'Score: ' + currentScore;
                var isLastRound = (roundIndex + 1 >= totalRounds);

                if (status === 'timeout') {
                    // Timeout: show deduction info; if the session is over go to leaderboard,
                    // otherwise offer next round or opt-out
                    var deductMsg = 'Unmatched cards deducted <strong>' + data.deduction + ' pts</strong>. ';
                    if (data.sessionOver) {
                        showOverlay("Time's Up!", deductMsg + scoreMsg(data), [leaderboardBtn()]);
                    } else {
                        showOverlay("Time's Up!", deductMsg + 'Score so far: <strong>' + currentScore + '</strong>', [nextRoundBtn(), optOutBtn()]);
                    }
                } else {
                    // Won: if this was the last round or the session is flagged over,
                    // show the completion screen; otherwise offer next round or opt-out
                    if (isLastRound || data.sessionOver) {
                        showOverlay('Endless Complete!', 'You finished all 7 rounds! ' + scoreMsg(data), [leaderboardBtn()]);
                    } else {
                        showOverlay('Round Complete!', 'Score so far: <strong>' + currentScore + '</strong>. Ready for the next round?', [nextRoundBtn(), optOutBtn()]);
                    }
                }
            });
        }

        // ─── confirmOptOut() ─────────────────────────────────────────────────────
        // Opens the opt-out dialog when the player clicks "End Session".
        // Shows the score-keeping message if score > 0, or the zero-score message
        // if the player hasn't earned any points yet.
        function confirmOptOut() {
            if (gameOver) return;
            document.getElementById('optOutScore').textContent = currentScore;
            // Toggle which message paragraph is visible based on current score
            document.getElementById('optOutSaveNote').style.display = currentScore > 0 ? '' : 'none';
            document.getElementById('optOutScoreMsg').classList.toggle('hidden', currentScore <= 0);
            document.getElementById('optOutZeroMsg').classList.toggle('hidden', currentScore > 0);
            var oo = document.getElementById('optOutOverlay');
            oo.classList.remove('hidden');
            oo.classList.add('flex');
        }

        // ─── closeOptOut() ───────────────────────────────────────────────────────
        // Dismisses the opt-out dialog without ending the session.
        function closeOptOut() {
            var oo = document.getElementById('optOutOverlay');
            oo.classList.add('hidden');
            oo.classList.remove('flex');
        }

        // ─── doOptOut() ──────────────────────────────────────────────────────────
        // Confirms the opt-out: cleans up the server-side session via action.php,
        // then either redirects straight to the home screen (score = 0) or shows
        // the session-ended overlay with leaderboard access (score > 0).
        function doOptOut() {
            closeOptOut();
            gameOver = true;
            clearInterval(timerInterval);

            if (currentScore <= 0) {
                // No points earned — clean up session and go straight home
                fetch('action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'endless_opt_out' })
                })
                .then(function() { window.location.href = 'index.php'; });
                return;
            }

            // Points earned — save the score and show the session-ended overlay
            fetch('action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'endless_opt_out' })
            })
            .then(res => res.json())
            .then(function(data) {
                showOverlay('Session Ended', scoreMsg(data), [leaderboardBtn()]);
            });
        }

        // ─── showOverlay(title, message, buttons) ────────────────────────────────
        // Generic helper that populates and displays the main overlay modal.
        // Accepts an array of pre-built button/anchor elements to append.
        function showOverlay(title, message, buttons) {
            document.getElementById('overlayTitle').textContent = title;
            document.getElementById('overlayMessage').innerHTML = message;
            var btnContainer = document.getElementById('overlayButtons');
            btnContainer.innerHTML = '';
            buttons.forEach(function(b) { btnContainer.appendChild(b); });
            var ov = document.getElementById('overlay');
            ov.classList.remove('hidden');
            ov.classList.add('flex');
        }

        // ─── Button factory functions ────────────────────────────────────────────
        // Each function creates and returns a styled button/anchor element
        // used as an action inside the overlay modal.

        // "Next Round ›" — navigates to the next round of endless mode
        function nextRoundBtn() {
            var a = document.createElement('a');
            a.href = 'endless.php';
            a.className = 'px-5 py-2 rounded-xl bg-purple-600 text-white font-display font-semibold hover:bg-purple-700 transition-colors';
            a.textContent = 'Next Round ›';
            return a;
        }

        // "Leaderboard" — navigates to the endless-mode leaderboard tab
        function leaderboardBtn() {
            var a = document.createElement('a');
            a.href = 'leaderboard.php?tab=endless';
            a.className = 'px-5 py-2 rounded-xl bg-purple-600 text-white font-display font-semibold hover:bg-purple-700 transition-colors';
            a.textContent = 'Leaderboard';
            return a;
        }

        // "End Session" — opens the opt-out confirmation dialog from within the overlay
        function optOutBtn() {
            var btn = document.createElement('button');
            btn.onclick = confirmOptOut;
            btn.className = 'px-5 py-2 rounded-xl bg-amber-500 text-white font-display font-semibold hover:bg-amber-600 transition-colors';
            btn.textContent = 'End Session';
            return btn;
        }
    </script>

</body>
</html>
