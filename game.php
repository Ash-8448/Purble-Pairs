<?php
/**
 * Purble Pairs - Main Game Page
 */
session_start();

if (empty($_SESSION['auth'])) {
    header('Location: auth.php');
    exit;
}

if (empty($_SESSION['player_name'])) {
    header('Location: index.php');
    exit;
}

// Admins cannot play — redirect to admin panel
if (!empty($_SESSION['is_admin'])) {
    header('Location: admin/index.php');
    exit;
}

$playerName = $_SESSION['player_name'];

require 'includes/cards.php';

$difficulty = $_GET['difficulty'] ?? 'beginner';

if (!isValidDifficulty($difficulty)) {
    $difficulty = 'beginner';
}

$config     = getDifficultyConfig($difficulty);
$totalCards = $config['cols'] * $config['rows'];
$pairCount  = $totalCards / 2;

$emojis = array_slice($allEmojis, 0, $pairCount);
$cards  = [...$emojis, ...$emojis];
shuffle($cards);

$_SESSION['game'] = [
    'difficulty' => $difficulty,
    'cards'      => $cards,
    'matched'    => array_fill(0, $totalCards, false),
    'moves'      => 0,
    'timeLimit'  => $config['time'],
    'startTime'  => time(),
    'status'     => 'playing',
];

$timerMinutes = floor($config['time'] / 60);
$timerSeconds = str_pad($config['time'] % 60, 2, '0', STR_PAD_LEFT);
$timerDisplay = "{$timerMinutes}:{$timerSeconds}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purble Pairs - <?php echo $config['label']; ?></title>
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
        .perspective { perspective: 600px; }
        .card-inner {
            transition: transform 0.5s;
            transform-style: preserve-3d;
            position: relative;
        }
        .card-inner.flipped { transform: rotateY(180deg); }
        .card-face {
            backface-visibility: hidden;
            position: absolute;
            inset: 0;
        }
        .card-back-face  { transform: rotateY(0deg); }
        .card-front-face { transform: rotateY(180deg); }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50%       { box-shadow: 0 0 20px 5px rgba(34,197,94,0.3); }
        }
        .matched-glow { animation: pulse-glow 1s ease-in-out 1; }

        @keyframes bounce-in {
            0%   { transform: scale(0.3); opacity: 0; }
            50%  { transform: scale(1.1); }
            70%  { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-bounce-in { animation: bounce-in 0.5s ease-out; }
    </style>
</head>
<body class="bg-purple-50 font-body min-h-screen flex flex-col items-center px-4 py-6 gap-5">

    <div class="w-full max-w-2xl flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-bold text-purple-700"><?php echo $config['label']; ?></h2>
            <p class="text-xs text-gray-400 font-body">
                Playing as <span class="text-purple-600 font-semibold"><?php echo htmlspecialchars($playerName); ?></span>
            </p>
        </div>
        <div class="flex items-center gap-3 font-body text-sm font-semibold">
            <span id="timer" class="px-3 py-1 rounded-lg bg-gray-200 text-gray-600">
                ⏱ <?php echo $timerDisplay; ?>
            </span>
            <span id="moveCounter" class="px-3 py-1 rounded-lg bg-gray-200 text-gray-600">Moves: 0</span>
            <a href="game.php?difficulty=<?php echo $difficulty; ?>"
               class="px-3 py-1 rounded-lg bg-purple-600 text-white hover:bg-purple-700 transition-colors">
                🔄 Restart
            </a>
            <a href="index.php"
               class="px-3 py-1 rounded-lg bg-gray-200 text-gray-600 hover:bg-gray-300 transition-colors">
                🏠 Home
            </a>
        </div>
    </div>

    <?php renderCardGrid($cards, $config['cols']); ?>

    <!-- Game Over Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl p-8 text-center shadow-2xl max-w-sm mx-4 space-y-4 animate-bounce-in">
            <div id="overlayIcon" class="text-5xl"></div>
            <h3 id="overlayTitle" class="font-display text-2xl font-bold text-gray-800"></h3>
            <p id="overlayMessage" class="text-gray-500 font-body"></p>
            <div class="flex gap-3 justify-center pt-2">
                <a href="game.php?difficulty=<?php echo $difficulty; ?>"
                   class="px-5 py-2 rounded-xl bg-purple-600 text-white font-display font-semibold hover:bg-purple-700 transition-colors"
                   id="overlayPlayBtn">Play Again</a>
                <a href="index.php"
                   class="px-5 py-2 rounded-xl bg-gray-200 text-gray-600 font-display font-semibold hover:bg-gray-300 transition-colors">Home</a>
            </div>
        </div>
    </div>

    <script>
        var timeLimit  = <?php echo $config['time']; ?>;
        var difficulty = '<?php echo $difficulty; ?>';
        var totalPairs = <?php echo $pairCount; ?>;
        var flipBackDelay = (difficulty === 'asian') ? 300 : 500;

        var timeLeft     = timeLimit;
        var moves        = 0;
        var flippedCards = [];
        var matchedCards = [];
        var isChecking   = false;
        var gameOver     = false;

        var timerEl = document.getElementById('timer');
        var timerInterval = setInterval(function() {
            if (gameOver) return;
            timeLeft--;
            var minutes = Math.floor(timeLeft / 60);
            var seconds = String(timeLeft % 60).padStart(2, '0');
            timerEl.textContent = '⏱ ' + minutes + ':' + seconds;
            if (timeLeft <= 15) timerEl.className = 'px-3 py-1 rounded-lg bg-red-500 text-white';
            if (timeLeft <= 0) { clearInterval(timerInterval); endGame('lost'); }
        }, 1000);

        function flipCard(id) {
            if (gameOver || isChecking) return;
            if (flippedCards.indexOf(id) !== -1 || matchedCards.indexOf(id) !== -1) return;
            if (flippedCards.length >= 2) return;

            document.getElementById('card-' + id).classList.add('flipped');
            flippedCards.push(id);

            if (flippedCards.length === 2) {
                moves++;
                document.getElementById('moveCounter').textContent = 'Moves: ' + moves;
                isChecking = true;

                fetch('action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'flip', card1: flippedCards[0], card2: flippedCards[1] })
                })
                .then(res => res.json())
                .then(function(data) {
                    if (data.match) {
                        matchedCards.push(flippedCards[0], flippedCards[1]);
                        flippedCards.forEach(function(cardId) {
                            var inner = document.getElementById('card-' + cardId);
                            inner.classList.add('matched-glow');
                            inner.querySelector('.card-front-face').className =
                                'card-face card-front-face w-full h-full rounded-xl bg-emerald-50 flex items-center justify-center shadow-md border-2 border-emerald-400';
                        });
                        flippedCards = [];
                        isChecking   = false;
                        if (matchedCards.length === totalPairs * 2) {
                            clearInterval(timerInterval);
                            setTimeout(function() { endGame('won'); }, 400);
                        }
                    } else {
                        var toFlipBack = flippedCards.slice();
                        setTimeout(function() {
                            toFlipBack.forEach(function(i) { document.getElementById('card-' + i).classList.remove('flipped'); });
                            flippedCards = [];
                            isChecking   = false;
                        }, flipBackDelay);
                    }
                })
                .catch(function() {
                    flippedCards.forEach(function(i) { document.getElementById('card-' + i).classList.remove('flipped'); });
                    flippedCards = [];
                    isChecking   = false;
                });
            }
        }

        function endGame(status) {
            gameOver = true;
            var overlay = document.getElementById('overlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');

            if (status === 'won') {
                document.getElementById('overlayIcon').textContent  = '🎉';
                document.getElementById('overlayTitle').textContent = 'You Win!';
                var elapsed = timeLimit - timeLeft;
                var m = Math.floor(elapsed / 60);
                var s = String(elapsed % 60).padStart(2, '0');
                document.getElementById('overlayMessage').innerHTML =
                    'Completed in <strong>' + moves + '</strong> moves in <strong>' + m + ':' + s + '</strong>!';
                document.getElementById('overlayPlayBtn').textContent = 'Play Again';
            } else {
                document.getElementById('overlayIcon').textContent  = '⏰';
                document.getElementById('overlayTitle').textContent = "Time's Up!";
                document.getElementById('overlayMessage').textContent = 'You ran out of time. Better luck next time!';
                document.getElementById('overlayPlayBtn').textContent = 'Try Again';
            }

            fetch('action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'end', status: status, moves: moves })
            });
        }
    </script>

</body>
</html>
