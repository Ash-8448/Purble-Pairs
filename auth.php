<?php
/**
 * Purble Pairs — Login / Signup Landing Page
 */
session_start();
require 'includes/db.php';
require 'includes/encrypt.php';

// Already logged in — skip straight to the right place
if (!empty($_SESSION['auth'])) {
    header('Location: ' . (!empty($_SESSION['is_admin']) ? 'admin/index.php' : 'index.php'));
    exit;
}

// --- Handle POST (PRG pattern: always redirect after POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = $_POST['type']     ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Signup
    if ($type === 'signup') {
        $confirm = $_POST['confirm_password'] ?? '';

        if ($username === '' || $password === '') {
            $_SESSION['flash'] = ['form' => 'signup', 'error' => 'Username and password are required.', 'username' => $username];
        } elseif (strlen($username) < 6) {
            $_SESSION['flash'] = ['form' => 'signup', 'error' => 'Username must be at least 6 characters.', 'username' => $username];
        } elseif (strlen($password) < 8) {
            $_SESSION['flash'] = ['form' => 'signup', 'error' => 'Password must be at least 8 characters.', 'username' => $username];
        } elseif ($password !== $confirm) {
            $_SESSION['flash'] = ['form' => 'signup', 'error' => 'Passwords do not match.', 'username' => $username];
        } else {
            $stmt = $conn->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->close();
                $_SESSION['flash'] = ['form' => 'signup', 'error' => 'Username already taken.', 'username' => $username];
            } else {
                $stmt->close();
                $encrypted = customEncrypt(strtoupper($password), $encryptionKey);

                $ins = $conn->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
                $ins->bind_param('ss', $username, $encrypted);
                $ins->execute();
                $ins->close();

                $_SESSION['auth']        = $username;
                $_SESSION['player_name'] = $username;
                header('Location: index.php');
                exit;
            }
        }
    }

    // Login
    elseif ($type === 'login') {
        if ($username === '' || $password === '') {
            $_SESSION['flash'] = ['form' => 'login', 'error' => 'Username and password are required.', 'username' => $username];
        } else {
            $stmt = $conn->prepare('SELECT password, is_admin FROM users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($storedPassword, $isAdmin);
            $found = $stmt->fetch();
            $stmt->close();

            if (!$found) {
                $_SESSION['flash'] = ['form' => 'login', 'error' => 'Incorrect username or password.', 'username' => $username];
            } else {
                $encrypted = customEncrypt(strtoupper($password), $encryptionKey);
                if ($encrypted === $storedPassword) {
                    $_SESSION['auth']        = $username;
                    $_SESSION['player_name'] = $username;
                    $_SESSION['is_admin']    = (bool) $isAdmin;
                    header('Location: ' . ($isAdmin ? 'admin/index.php' : 'index.php'));
                    exit;
                } else {
                    $_SESSION['flash'] = ['form' => 'login', 'error' => 'Incorrect username or password.', 'username' => $username];
                }
            }
        }
    }

    header('Location: auth.php');
    exit;
}

// --- GET: consume flash data if present ---
$flash       = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$error       = $flash['error']    ?? '';
$activeForm  = $flash['form']     ?? 'login';
$oldUsername = $flash['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purble Pairs — Welcome</title>
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
        .animate-bounce-in { animation: bounce-in 0.45s ease-out; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }
        .animate-float { animation: float 3s ease-in-out infinite; }

        .deco-card {
            width: 52px; height: 52px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
    </style>
</head>
<body class="bg-purple-50 font-body min-h-screen flex items-center justify-center px-4 py-10">

    <!-- Floating decorative cards -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none select-none" aria-hidden="true">
        <div class="deco-card bg-purple-200 absolute top-10 left-8  animate-float" style="animation-delay:0s">🎵</div>
        <div class="deco-card bg-amber-200  absolute top-24 right-10 animate-float" style="animation-delay:.6s">🌟</div>
        <div class="deco-card bg-emerald-200 absolute bottom-20 left-12 animate-float" style="animation-delay:1.2s">🚀</div>
        <div class="deco-card bg-rose-200   absolute bottom-10 right-8  animate-float" style="animation-delay:.3s">🦋</div>
        <div class="deco-card bg-sky-200    absolute top-1/2 left-4    animate-float" style="animation-delay:.9s">🍕</div>
        <div class="deco-card bg-violet-200 absolute top-1/3 right-4   animate-float" style="animation-delay:1.5s">🏆</div>
    </div>

    <!-- Auth card -->
    <div class="relative z-10 bg-white rounded-3xl shadow-2xl w-full max-w-md p-8 space-y-6 animate-bounce-in">

        <div class="text-center space-y-1">
            <div class="text-5xl mb-2">🃏</div>
            <h1 class="font-display text-4xl font-bold text-purple-700">Purble Pairs</h1>
            <p class="text-gray-400 text-sm">Flip cards. Find matches. Have fun.</p>
        </div>

        <!-- Tab switcher -->
        <div class="flex rounded-xl overflow-hidden border-2 border-purple-100">
            <button id="tabLogin"  onclick="switchTab('login')"
                    class="flex-1 py-2 font-display font-semibold text-base transition-colors">Login</button>
            <button id="tabSignup" onclick="switchTab('signup')"
                    class="flex-1 py-2 font-display font-semibold text-base transition-colors">Sign Up</button>
        </div>

        <!-- Error banner -->
        <?php if ($error !== ''): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl px-4 py-3 text-center">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Login form -->
        <form id="formLogin" action="auth.php" method="POST" class="space-y-4" novalidate>
            <input type="hidden" name="type" value="login">
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1" for="loginUsername">Username</label>
                <input id="loginUsername" name="username" type="text" required autocomplete="username"
                       value="<?php echo ($activeForm === 'login') ? htmlspecialchars($oldUsername) : ''; ?>"
                       placeholder="Enter your username"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none font-body text-sm transition-colors">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1" for="loginPassword">Password</label>
                <input id="loginPassword" name="password" type="password" required autocomplete="current-password"
                       placeholder="Enter your password"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none font-body text-sm transition-colors">
            </div>
            <button type="submit"
                    class="w-full py-3 rounded-xl bg-purple-600 text-white font-display font-semibold text-lg hover:bg-purple-700 active:scale-95 transition-all duration-150 shadow-md">
                Let's Play! 🎮
            </button>
        </form>

        <!-- Signup form -->
        <form id="formSignup" action="auth.php" method="POST" class="space-y-4 hidden" novalidate
              onsubmit="return validateSignup(event)">
            <input type="hidden" name="type" value="signup">
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1" for="signupUsername">Username</label>
                <input id="signupUsername" name="username" type="text" required autocomplete="username"
                       value="<?php echo ($activeForm === 'signup') ? htmlspecialchars($oldUsername) : ''; ?>"
                       placeholder="Choose a username"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none font-body text-sm transition-colors">
                <p id="usernameError" class="text-red-500 text-xs mt-1 hidden">Username must be at least 6 characters.</p>
                <p class="text-gray-400 text-xs mt-1">Minimum 6 characters.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1" for="signupPassword">Password</label>
                <input id="signupPassword" name="password" type="password" required autocomplete="new-password"
                       placeholder="Create a password"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none font-body text-sm transition-colors">
                <p id="passwordError" class="text-red-500 text-xs mt-1 hidden">Password must be at least 8 characters.</p>
                <p class="text-gray-400 text-xs mt-1">Minimum 8 characters.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-1" for="signupConfirm">Confirm Password</label>
                <input id="signupConfirm" name="confirm_password" type="password" required autocomplete="new-password"
                       placeholder="Repeat your password"
                       class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-purple-400 focus:outline-none font-body text-sm transition-colors">
                <p id="confirmError" class="text-red-500 text-xs mt-1 hidden">Passwords do not match.</p>
            </div>
            <button type="submit"
                    class="w-full py-3 rounded-xl bg-emerald-500 text-white font-display font-semibold text-lg hover:bg-emerald-600 active:scale-95 transition-all duration-150 shadow-md">
                Create Account 🚀
            </button>
        </form>

    </div>

    <script>
        const ACTIVE_TAB   = 'bg-purple-600 text-white';
        const INACTIVE_TAB = 'bg-white text-gray-400 hover:text-purple-600';

        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('formLogin').classList.toggle('hidden', !isLogin);
            document.getElementById('formSignup').classList.toggle('hidden', isLogin);
            document.getElementById('tabLogin').className  = 'flex-1 py-2 font-display font-semibold text-base transition-colors ' + (isLogin  ? ACTIVE_TAB : INACTIVE_TAB);
            document.getElementById('tabSignup').className = 'flex-1 py-2 font-display font-semibold text-base transition-colors ' + (!isLogin ? ACTIVE_TAB : INACTIVE_TAB);
        }

        switchTab('<?php echo $activeForm; ?>');

        function validateSignup(e) {
            const un    = document.getElementById('signupUsername').value;
            const pw    = document.getElementById('signupPassword').value;
            const cpw   = document.getElementById('signupConfirm').value;
            let valid   = true;

            if (un.length < 6) {
                e.preventDefault();
                document.getElementById('usernameError').classList.remove('hidden');
                document.getElementById('signupUsername').classList.add('border-red-400');
                valid = false;
            } else {
                document.getElementById('usernameError').classList.add('hidden');
                document.getElementById('signupUsername').classList.remove('border-red-400');
            }

            if (pw.length < 8) {
                e.preventDefault();
                document.getElementById('passwordError').classList.remove('hidden');
                document.getElementById('signupPassword').classList.add('border-red-400');
                valid = false;
            } else {
                document.getElementById('passwordError').classList.add('hidden');
                document.getElementById('signupPassword').classList.remove('border-red-400');
            }

            if (pw !== cpw) {
                e.preventDefault();
                document.getElementById('confirmError').classList.remove('hidden');
                document.getElementById('signupConfirm').classList.add('border-red-400');
                valid = false;
            } else {
                document.getElementById('confirmError').classList.add('hidden');
                document.getElementById('signupConfirm').classList.remove('border-red-400');
            }

            return valid;
        }

        document.getElementById('signupUsername').addEventListener('input', function () {
            document.getElementById('usernameError').classList.add('hidden');
            this.classList.remove('border-red-400');
        });
        document.getElementById('signupPassword').addEventListener('input', function () {
            document.getElementById('passwordError').classList.add('hidden');
            this.classList.remove('border-red-400');
        });
        document.getElementById('signupConfirm').addEventListener('input', function () {
            document.getElementById('confirmError').classList.add('hidden');
            this.classList.remove('border-red-400');
        });
    </script>

</body>
</html>
