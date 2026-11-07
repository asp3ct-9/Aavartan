<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| Aavartan Login
|--------------------------------------------------------------------------
| Local PHP + MySQL authentication.
| Database: aavartan_db
| Table: users
|
| Expected users columns:
| id, full_name, email, college, course, year, password_hash, role
|--------------------------------------------------------------------------
*/

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'aavartan_db';

$message = '';
$messageType = '';

/* Create a CSRF token for the login form. */
if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

/* If already logged in, don't show the login page again. */
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    /* CSRF validation */
    if (
        $csrf === '' ||
        !hash_equals((string)$_SESSION['login_csrf'], $csrf)
    ) {
        $message = 'Your session expired. Please refresh the page and try again.';
        $messageType = 'error';
    } elseif ($email === '' || $password === '') {
        $message = 'Please enter your email and password.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) > 255) {
        $message = 'The password is too long.';
        $messageType = 'error';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_errno) {
            error_log('Aavartan DB connection error: ' . $conn->connect_error);
            $message = 'Unable to connect to the database. Please try again later.';
            $messageType = 'error';
        } else {
            $conn->set_charset('utf8mb4');

            $sql = 'SELECT id, full_name, email, college, course, year, password_hash, role
                    FROM users
                    WHERE email = ?
                    LIMIT 1';

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                error_log('Aavartan login prepare error: ' . $conn->error);
                $message = 'Something went wrong. Please try again.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('s', $email);

                if (!$stmt->execute()) {
                    error_log('Aavartan login execute error: ' . $stmt->error);
                    $message = 'Something went wrong. Please try again.';
                    $messageType = 'error';
                } else {
                    $result = $stmt->get_result();
                    $user = $result ? $result->fetch_assoc() : null;

                    /*
                     * Keep the same generic message for an unknown email
                     * and an incorrect password.
                     */
                    if (
                        is_array($user) &&
                        isset($user['password_hash']) &&
                        password_verify($password, (string)$user['password_hash'])
                    ) {
                        session_regenerate_id(true);

                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['full_name'] = (string)$user['full_name'];
                        $_SESSION['email'] = (string)$user['email'];
                        $_SESSION['college'] = (string)($user['college'] ?? '');
                        $_SESSION['course'] = (string)($user['course'] ?? '');
                        $_SESSION['year'] = (string)($user['year'] ?? '');
                        $_SESSION['role'] = (string)($user['role'] ?? 'student');
                        $_SESSION['logged_in_at'] = time();

                        /*
                         * Upgrade old password hashes automatically when PHP
                         * recommends a stronger algorithm/cost.
                         */
                        if (password_needs_rehash(
                            (string)$user['password_hash'],
                            PASSWORD_DEFAULT
                        )) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);

                            if ($newHash !== false) {
                                $update = $conn->prepare(
                                    'UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1'
                                );

                                if ($update) {
                                    $update->bind_param(
                                        'si',
                                        $newHash,
                                        $user['id']
                                    );
                                    $update->execute();
                                    $update->close();
                                }
                            }
                        }

                        $stmt->close();
                        $conn->close();

                        header('Location: dashboard.php');
                        exit;
                    }

                    $message = 'Incorrect email or password.';
                    $messageType = 'error';
                }

                $stmt->close();
            }

            $conn->close();
        }
    }
}

/* Preserve the email after a failed login, but never preserve the password. */
$displayEmail = htmlspecialchars(
    (string)($_POST['email'] ?? ''),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Sign in to Aavartan, the student project marketplace.">
<meta name="theme-color" content="#0b7a4b">
<title>Aavartan | Login</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --green:#0b7a4b;
    --green-2:#13a66a;
    --green-light:#6ee7ae;
    --green-soft:#e8f8f0;
    --dark:#0d2118;
    --ink:#10221a;
    --muted:#687970;
    --line:#dfe9e4;
    --bg:#f7faf8;
    --white:#fff;
    --danger:#b91c1c;
    --danger-bg:#fef2f2;
    --danger-line:#fecaca;
    --shadow:0 24px 70px rgba(16,34,26,.12);
}

*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    min-height:100vh;
    font-family:"DM Sans",sans-serif;
    color:var(--ink);
    background:var(--bg);
}
a{text-decoration:none;color:inherit}
button,input{font:inherit}
button,a{-webkit-tap-highlight-color:transparent}

/* PAGE */
.page{
    min-height:100vh;
    display:grid;
    grid-template-columns:1.05fr .95fr;
    overflow:hidden;
}

/* LEFT */
.brand-panel{
    position:relative;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    padding:38px clamp(30px,6vw,86px);
    overflow:hidden;
    color:#fff;
    background:
        radial-gradient(circle at 78% 20%,rgba(19,166,106,.2),transparent 29%),
        radial-gradient(circle at 10% 92%,rgba(19,166,106,.08),transparent 28%),
        var(--dark);
}
.brand-panel:before,
.brand-panel:after{
    content:"";
    position:absolute;
    border:1px solid rgba(110,231,174,.1);
    border-radius:50%;
    pointer-events:none;
}
.brand-panel:before{
    width:550px;height:550px;
    right:-275px;top:-210px;
}
.brand-panel:after{
    width:420px;height:420px;
    left:-280px;bottom:-240px;
}

.brand{
    position:relative;
    z-index:2;
    display:flex;
    align-items:center;
    gap:10px;
    width:max-content;
    font:800 22px/1 "Plus Jakarta Sans",sans-serif;
    letter-spacing:-.7px;
}
.brand-icon{
    width:40px;height:40px;
    display:grid;place-items:center;
    border-radius:12px;
    color:#fff;
    background:linear-gradient(135deg,var(--green),var(--green-2));
    box-shadow:0 10px 24px rgba(19,166,106,.2);
    font-size:19px;
}
.brand span{color:var(--green-light)}

.panel-content{
    position:relative;
    z-index:2;
    max-width:610px;
    margin:auto 0;
    padding:70px 0;
}
.badge{
    width:max-content;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border:1px solid rgba(110,231,174,.2);
    border-radius:999px;
    color:#a7f3d0;
    background:rgba(110,231,174,.07);
    font-size:11px;
    font-weight:800;
}
.dot{
    width:7px;height:7px;
    border-radius:50%;
    background:#4ade80;
    box-shadow:0 0 10px rgba(74,222,128,.65);
}
.panel-content h1{
    margin-top:23px;
    max-width:580px;
    font:800 clamp(40px,4.3vw,61px)/1.04 "Plus Jakarta Sans",sans-serif;
    letter-spacing:-3px;
}
.panel-content h1 span{color:var(--green-light)}
.panel-description{
    max-width:530px;
    margin-top:23px;
    color:#aab8b0;
    font-size:15px;
    line-height:1.8;
}

.mini-features{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
    margin-top:34px;
}
.mini-feature{
    padding:15px;
    border:1px solid rgba(255,255,255,.08);
    border-radius:15px;
    background:rgba(255,255,255,.045);
    backdrop-filter:blur(10px);
}
.mini-feature strong{
    display:block;
    color:var(--green-light);
    font:800 18px "Plus Jakarta Sans",sans-serif;
}
.mini-feature span{
    display:block;
    margin-top:5px;
    color:#83928a;
    font-size:9px;
    line-height:1.4;
}

.panel-footer{
    position:relative;
    z-index:2;
    color:#708078;
    font-size:10px;
}

/* RIGHT */
.login-panel{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px clamp(22px,5vw,75px);
    background:
        radial-gradient(circle at 100% 0%,rgba(19,166,106,.07),transparent 27%),
        #f7faf8;
}
.login-box{
    width:100%;
    max-width:430px;
}
.mobile-brand{
    display:none;
    margin-bottom:30px;
}

.login-header{margin-bottom:26px}
.login-header h2{
    font:800 32px/1.15 "Plus Jakarta Sans",sans-serif;
    letter-spacing:-1.2px;
}
.login-header p{
    margin-top:9px;
    color:var(--muted);
    font-size:13px;
    line-height:1.65;
}

/* MESSAGE */
.message{
    display:none;
    align-items:flex-start;
    gap:9px;
    padding:12px 14px;
    margin-bottom:17px;
    border:1px solid var(--danger-line);
    border-radius:11px;
    color:var(--danger);
    background:var(--danger-bg);
    font-size:12px;
    line-height:1.5;
}
.message.error{display:flex}
.message.success{
    display:flex;
    color:#15803d;
    border-color:#bbf7d0;
    background:#f0fdf4;
}

/* FORM */
.form-group{margin-bottom:17px}
.form-group label{
    display:block;
    margin-bottom:8px;
    font-size:12px;
    font-weight:800;
}
.input-wrap{position:relative}
.input-icon{
    position:absolute;
    left:14px;
    top:50%;
    z-index:1;
    color:#89958f;
    font-size:15px;
    transform:translateY(-50%);
    pointer-events:none;
}
.input-wrap input{
    width:100%;
    height:51px;
    padding:0 45px 0 42px;
    border:1px solid var(--line);
    border-radius:11px;
    outline:none;
    color:var(--ink);
    background:#fff;
    font-size:13px;
    transition:.2s;
}
.input-wrap input::placeholder{color:#a2ada7}
.input-wrap input:hover{border-color:#cbd8d1}
.input-wrap input:focus{
    border-color:#35b879;
    box-shadow:0 0 0 3px rgba(53,184,121,.12);
}
.input-wrap input[aria-invalid="true"]{border-color:#fca5a5}

.eye{
    position:absolute;
    right:9px;
    top:50%;
    width:33px;height:33px;
    display:grid;place-items:center;
    border:0;
    border-radius:8px;
    color:#718078;
    background:transparent;
    cursor:pointer;
    transform:translateY(-50%);
}
.eye:hover{background:#f1f5f2;color:var(--ink)}

.options{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    margin:3px 0 21px;
    font-size:11px;
}
.remember{
    display:flex;
    align-items:center;
    gap:7px;
    color:#68766e;
    cursor:pointer;
}
.remember input{
    width:15px;height:15px;
    accent-color:var(--green);
}
.forgot{
    border:0;
    color:var(--green);
    background:transparent;
    font-size:11px;
    font-weight:800;
    cursor:pointer;
}
.forgot:hover{text-decoration:underline}

.login-btn{
    width:100%;
    height:51px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    border:0;
    border-radius:11px;
    color:#fff;
    background:linear-gradient(135deg,var(--green),var(--green-2));
    box-shadow:0 10px 24px rgba(11,122,75,.16);
    font-size:13px;
    font-weight:800;
    cursor:pointer;
    transition:.2s;
}
.login-btn:hover:not(:disabled){
    transform:translateY(-2px);
    box-shadow:0 14px 28px rgba(11,122,75,.22);
}
.login-btn:disabled{opacity:.68;cursor:not-allowed}

.spinner{
    width:16px;height:16px;
    border:2px solid rgba(255,255,255,.35);
    border-top-color:#fff;
    border-radius:50%;
    animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}

.divider{
    display:flex;
    align-items:center;
    gap:11px;
    margin:23px 0;
    color:#9aa59f;
    font-size:9px;
    font-weight:800;
}
.divider:before,.divider:after{
    content:"";
    flex:1;
    height:1px;
    background:#e2e9e5;
}

.register a{
    color:var(--green);
    font-weight:800;
}
.register a:hover{text-decoration:underline}

.security{
    margin-top:20px;
    padding:12px;
    border:1px solid #d8f2e2;
    border-radius:10px;
    color:#587063;
    background:#f0fbf5;
    font-size:10px;
    line-height:1.5;
    text-align:center;
}

/* RESPONSIVE */
@media(max-width:900px){
    .page{display:block}
    .brand-panel{
        min-height:auto;
        padding:28px 6%;
    }
    .panel-content{
        margin:55px auto 30px;
        padding:0;
        text-align:center;
    }
    .badge{margin:auto}
    .panel-content h1{margin-left:auto;margin-right:auto}
    .panel-description{margin-left:auto;margin-right:auto}
    .mini-features{max-width:600px;margin-left:auto;margin-right:auto}
    .panel-footer{display:none}
    .login-panel{
        min-height:auto;
        padding:55px 6%;
    }
}

@media(max-width:600px){
    .brand-panel{padding:22px 5%}
    .brand{font-size:20px}
    .brand-icon{width:37px;height:37px}
    .panel-content{margin-top:45px}
    .panel-content h1{
        font-size:37px;
        letter-spacing:-2px;
    }
    .panel-description{font-size:13px}
    .mini-features{grid-template-columns:1fr;gap:8px}
    .mini-feature{text-align:left}
    .login-panel{padding:42px 5%}
    .mobile-brand{display:flex}
    .login-header h2{font-size:27px}
    .options{font-size:10px}
}

@media(prefers-reduced-motion:reduce){
    *{scroll-behavior:auto!important;animation:none!important;transition:none!important}
}
</style>
</head>

<body>
<div class="page">

    <section class="brand-panel">
        <a class="brand" href="index.php" aria-label="Aavartan home">
            <span class="brand-icon">♻</span>
            Aav<span>artan</span>
        </a>

        <div class="panel-content">
            <div class="badge">
                <span class="dot"></span>
                Sustainable Student Marketplace
            </div>

            <h1>Your project ends.<br><span>Its materials don't.</span></h1>

            <p class="panel-description">
                Buy, sell and reuse college project materials in one simple
                student-focused marketplace.
            </p>

            <div class="mini-features">
                <div class="mini-feature">
                    <strong>Buy</strong>
                    <span>Find affordable project materials.</span>
                </div>
                <div class="mini-feature">
                    <strong>Sell</strong>
                    <span>Give unused materials a second life.</span>
                </div>
                <div class="mini-feature">
                    <strong>Reuse</strong>
                    <span>Keep useful components in circulation.</span>
                </div>
            </div>
        </div>

        <div class="panel-footer">
            © 2026 Aavartan · Built for students, by students.
        </div>
    </section>

    <section class="login-panel">
        <main class="login-box">

            <div class="mobile-brand">
                <a class="brand" href="index.php">
                    <span class="brand-icon">♻</span>
                    Aav<span>artan</span>
                </a>
            </div>

            <header class="login-header">
                <h2>Welcome back 👋</h2>
                <p>Sign in to continue to your Aavartan account.</p>
            </header>

            <?php if ($message !== ''): ?>
                <div
                    id="message"
                    class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"
                    role="alert"
                    aria-live="polite"
                >
                    <span><?= $messageType === 'error' ? '⚠' : '✓' ?></span>
                    <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php else: ?>
                <div id="message" class="message" role="alert" aria-live="polite"></div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="login.php" autocomplete="on" novalidate>
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars((string)$_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                >

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <span class="input-icon">✉</span>
                        <input
                            name="email"
                            id="email"
                            type="email"
                            value="<?= $displayEmail ?>"
                            placeholder="you@college.edu"
                            autocomplete="username"
                            inputmode="email"
                            autocapitalize="none"
                            spellcheck="false"
                            maxlength="254"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input
                            name="password"
                            id="password"
                            type="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            maxlength="255"
                            required
                        >
                        <button
                            type="button"
                            class="eye"
                            id="eyeButton"
                            aria-label="Show password"
                            aria-pressed="false"
                        >👁</button>
                    </div>
                </div>

                <div class="options">
                    <label class="remember">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        Remember me
                    </label>

                    <button type="button" class="forgot" id="forgotButton">
                        Forgot password?
                    </button>
                </div>

                <button type="submit" class="login-btn" id="loginButton">
                    <span id="loginButtonText">Sign In →</span>
                </button>
            </form>
<div class="register">
                Don't have an account?
                <a href="signup.php">Create account</a>
            </div>

            <div class="security">
                🔒 Your password is securely protected using PHP password hashing.
            </div>

        </main>
    </section>
</div>

<script>
(() => {
    'use strict';

    const form = document.getElementById('loginForm');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const eye = document.getElementById('eyeButton');
    const forgot = document.getElementById('forgotButton');
    const message = document.getElementById('message');
    const loginButton = document.getElementById('loginButton');
    const loginText = document.getElementById('loginButtonText');
    const remember = document.getElementById('remember');

    const EMAIL_KEY = 'aavartan_email';

    function showMessage(text, type = 'error') {
        message.textContent = text;
        message.className = 'message ' + type;
    }

    function clearError(input) {
        input.removeAttribute('aria-invalid');
    }

    function validate() {
        const value = email.value.trim();

        clearError(email);
        clearError(password);

        if (!value) {
            showMessage('Please enter your email address.');
            email.setAttribute('aria-invalid', 'true');
            email.focus();
            return false;
        }

        if (!email.validity.valid) {
            showMessage('Please enter a valid email address.');
            email.setAttribute('aria-invalid', 'true');
            email.focus();
            return false;
        }

        if (!password.value) {
            showMessage('Please enter your password.');
            password.setAttribute('aria-invalid', 'true');
            password.focus();
            return false;
        }

        return true;
    }

    eye.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        eye.textContent = show ? '🙈' : '👁';
        eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        eye.setAttribute('aria-pressed', show ? 'true' : 'false');
    });

    const savedEmail = localStorage.getItem(EMAIL_KEY);
    if (savedEmail && !email.value) {
        email.value = savedEmail;
        remember.checked = true;
    }

    forgot.addEventListener('click', () => {
        if (!email.value.trim()) {
            showMessage('Enter your email address first.');
            email.focus();
            return;
        }

        if (!email.validity.valid) {
            showMessage('Please enter a valid email address.');
            email.focus();
            return;
        }

        showMessage(
            'Password reset is not configured yet. Please contact the administrator to reset your password.',
            'error'
        );
    });
form.addEventListener('submit', (event) => {
        if (!validate()) {
            event.preventDefault();
            return;
        }

        if (remember.checked) {
            localStorage.setItem(EMAIL_KEY, email.value.trim());
        } else {
            localStorage.removeItem(EMAIL_KEY);
        }

        loginButton.disabled = true;
        loginButton.setAttribute('aria-busy', 'true');
        loginText.innerHTML =
            '<span class="spinner" aria-hidden="true"></span> Signing in...';
    });

    email.addEventListener('input', () => clearError(email));
    password.addEventListener('input', () => clearError(password));
})();
</script>
</body>
</html>
