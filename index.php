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
    <meta name="description" content="Aavartan — a student marketplace for buying, selling and reusing project materials.">
    <meta name="theme-color" content="#0b7a4b">
    <title>Aavartan | Student Project Marketplace</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --green:#0b7a4b;
            --green-2:#13a66a;
            --green-soft:#e8f8f0;
            --ink:#10221a;
            --muted:#687970;
            --line:#dfe9e4;
            --bg:#f7faf8;
            --white:#fff;
            --shadow:0 24px 70px rgba(16,34,26,.12);
            --radius:24px;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{
            font-family:"DM Sans",sans-serif;
            color:var(--ink);
            background:var(--bg);
            overflow-x:hidden;
        }
        a{text-decoration:none;color:inherit}
        button{font:inherit}
        .container{width:min(1160px,calc(100% - 40px));margin:auto}

        /* NAVBAR */
        .site-header{
            position:sticky;
            top:0;
            z-index:100;
            border-bottom:1px solid rgba(223,233,228,.75);
            background:rgba(247,250,248,.86);
            backdrop-filter:blur(16px);
        }
        .nav{
            min-height:74px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:24px;
        }
        .brand{
            display:flex;
            align-items:center;
            gap:10px;
            font:800 22px/1 "Plus Jakarta Sans",sans-serif;
            letter-spacing:-.7px;
            white-space:nowrap;
        }
        .brand-mark{
            width:40px;height:40px;
            display:grid;place-items:center;
            border-radius:12px;
            color:white;
            background:linear-gradient(135deg,var(--green),var(--green-2));
            box-shadow:0 9px 22px rgba(11,122,75,.2);
            font-size:19px;
        }
        .brand em{font-style:normal;color:var(--green)}
        .nav-links{
            display:flex;
            align-items:center;
            gap:30px;
            color:#56665e;
            font-size:13px;
            font-weight:700;
        }
        .nav-links a{transition:.2s}
        .nav-links a:hover{color:var(--green)}
        .nav-actions{display:flex;gap:9px;align-items:center}

        .btn{
            min-height:44px;
            padding:0 18px;
            border-radius:12px;
            border:1px solid transparent;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            font-size:13px;
            font-weight:800;
            cursor:pointer;
            transition:transform .2s,box-shadow .2s,background .2s,border-color .2s;
        }
        .btn:hover{transform:translateY(-2px)}
        .btn-primary{
            color:#fff;
            background:linear-gradient(135deg,var(--green),var(--green-2));
            box-shadow:0 10px 24px rgba(11,122,75,.18);
        }
        .btn-primary:hover{box-shadow:0 14px 28px rgba(11,122,75,.24)}
        .btn-secondary{
            color:#26372f;
            border-color:var(--line);
            background:rgba(255,255,255,.8);
        }
        .btn-secondary:hover{background:#fff;border-color:#c7d6cf}

        /* HERO */
        .hero{
            position:relative;
            padding:76px 0 92px;
            overflow:hidden;
            background:
                radial-gradient(circle at 82% 22%,rgba(19,166,106,.13),transparent 28%),
                linear-gradient(135deg,#f8fcfa 0%,#edf7f1 100%);
        }
        .hero:before{
            content:"";
            position:absolute;
            width:480px;height:480px;
            right:-180px;top:-180px;
            border:1px solid rgba(11,122,75,.08);
            border-radius:50%;
        }
        .hero-grid{
            position:relative;
            z-index:1;
            display:grid;
            grid-template-columns:1.05fr .95fr;
            align-items:center;
            gap:72px;
        }
        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 12px;
            border:1px solid #cdebdc;
            border-radius:999px;
            color:var(--green);
            background:rgba(232,248,240,.86);
            font-size:11px;
            font-weight:800;
            letter-spacing:.3px;
        }
        .pulse{
            width:7px;height:7px;border-radius:50%;
            background:#19b875;
            box-shadow:0 0 0 5px rgba(25,184,117,.12);
        }
        .hero h1{
            margin-top:22px;
            max-width:700px;
            font:800 clamp(43px,5.5vw,70px)/1.03 "Plus Jakarta Sans",sans-serif;
            letter-spacing:-3.8px;
        }
        .hero h1 span{color:var(--green)}
        .hero-copy>p{
            max-width:610px;
            margin-top:24px;
            color:var(--muted);
            font-size:16px;
            line-height:1.8;
        }
        .hero-actions{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:30px;
        }
        .hero-actions .btn{min-height:51px;padding:0 22px}
        .proofs{
            display:flex;
            flex-wrap:wrap;
            gap:18px;
            margin-top:28px;
            color:#728078;
            font-size:11px;
            font-weight:700;
        }
        .proofs span{display:flex;align-items:center;gap:7px}
        .tick{
            width:19px;height:19px;
            display:grid;place-items:center;
            border-radius:50%;
            color:var(--green);
            background:#d9f5e6;
            font-size:10px;
        }

        /* MARKETPLACE PREVIEW */
        .hero-visual{position:relative;min-height:480px;display:grid;place-items:center}
        .orb{
            position:absolute;
            width:410px;height:410px;
            border-radius:50%;
            border:1px solid rgba(11,122,75,.12);
        }
        .orb:after{
            content:"";
            position:absolute;inset:45px;
            border:1px dashed rgba(11,122,75,.13);
            border-radius:50%;
        }
        .market-card{
            position:relative;
            z-index:2;
            width:min(420px,92%);
            padding:22px;
            border:1px solid rgba(255,255,255,.9);
            border-radius:25px;
            background:rgba(255,255,255,.88);
            box-shadow:var(--shadow);
            backdrop-filter:blur(18px);
        }
        .market-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:10px;
        }
        .market-title{font:800 17px "Plus Jakarta Sans",sans-serif}
        .live{
            padding:6px 9px;
            border-radius:999px;
            color:var(--green);
            background:var(--green-soft);
            font-size:9px;
            font-weight:900;
        }
        .market-search{
            height:39px;
            display:flex;
            align-items:center;
            gap:8px;
            padding:0 12px;
            margin-bottom:9px;
            border:1px solid #e6eee9;
            border-radius:10px;
            color:#9aa69f;
            background:#f8fbf9;
            font-size:11px;
        }
        .product{
            display:flex;
            align-items:center;
            gap:12px;
            padding:14px 0;
            border-bottom:1px solid #edf2ef;
        }
        .product:last-child{border-bottom:0}
        .product-icon{
            width:50px;height:50px;
            flex:0 0 50px;
            display:grid;place-items:center;
            border-radius:14px;
            background:#eefaf4;
            font-size:22px;
        }
        .product-info{min-width:0;flex:1}
        .product-info h3{
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            font-size:12px;
            font-weight:800;
        }
        .product-info p{
            margin-top:4px;
            color:#87948d;
            font-size:9px;
        }
        .price{color:var(--green);font-size:13px;font-weight:900;white-space:nowrap}
        .float{
            position:absolute;
            z-index:3;
            padding:12px 14px;
            border:1px solid rgba(255,255,255,.9);
            border-radius:14px;
            background:rgba(255,255,255,.9);
            box-shadow:0 15px 38px rgba(16,34,26,.11);
            backdrop-filter:blur(10px);
        }
        .float strong{
            display:block;
            color:var(--green);
            font:800 17px "Plus Jakarta Sans",sans-serif;
        }
        .float small{color:#7a8981;font-size:9px}
        .float.a{left:0;top:45px}
        .float.b{right:-2px;bottom:42px}

        /* STATS */
        .stats{
            background:#fff;
            border-bottom:1px solid var(--line);
        }
        .stats-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
        }
        .stat{
            padding:28px 24px;
            text-align:center;
            border-right:1px solid var(--line);
        }
        .stat:last-child{border-right:0}
        .stat strong{
            display:block;
            font:800 25px "Plus Jakarta Sans",sans-serif;
            color:var(--green);
        }
        .stat span{display:block;margin-top:5px;color:#77847d;font-size:11px;font-weight:600}

        /* FEATURES */
        .section{padding:90px 0}
        .section-head{max-width:680px;margin-bottom:42px}
        .section-label{
            color:var(--green);
            font-size:11px;
            font-weight:900;
            letter-spacing:1.3px;
            text-transform:uppercase;
        }
        .section-head h2{
            margin-top:10px;
            font:800 clamp(30px,4vw,45px)/1.1 "Plus Jakarta Sans",sans-serif;
            letter-spacing:-2px;
        }
        .section-head p{margin-top:13px;color:var(--muted);font-size:14px;line-height:1.7}
        .feature-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:16px;
        }
        .feature{
            padding:27px;
            border:1px solid var(--line);
            border-radius:19px;
            background:#fff;
            box-shadow:0 10px 35px rgba(16,34,26,.04);
            transition:.2s;
        }
        .feature:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(16,34,26,.08)}
        .feature-icon{
            width:45px;height:45px;
            display:grid;place-items:center;
            border-radius:13px;
            background:var(--green-soft);
            font-size:19px;
        }
        .feature h3{margin-top:20px;font:800 16px "Plus Jakarta Sans",sans-serif}
        .feature p{margin-top:9px;color:#7a8780;font-size:12px;line-height:1.75}

        /* DARK CTA */
        .dark-section{padding:0 0 90px}
        .cta{
            position:relative;
            overflow:hidden;
            padding:65px 50px;
            border-radius:28px;
            color:#fff;
            text-align:center;
            background:
                radial-gradient(circle at 15% 0%,rgba(38,194,127,.18),transparent 28%),
                radial-gradient(circle at 90% 100%,rgba(38,194,127,.12),transparent 30%),
                #0d2118;
        }
        .cta .section-label{color:#6ee7ae}
        .cta h2{margin-top:10px;font:800 clamp(29px,4vw,44px) "Plus Jakarta Sans",sans-serif;letter-spacing:-1.8px}
        .cta p{max-width:590px;margin:13px auto 25px;color:#a8b6ae;font-size:13px;line-height:1.7}

        /* FOOTER */
        footer{
            padding:24px 0;
            border-top:1px solid var(--line);
            background:#fff;
            color:#7b8781;
            font-size:11px;
        }
        .footer{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
        }
        .footer-links{display:flex;gap:14px}
        .footer a:hover{color:var(--green)}

        /* MOBILE MENU */
        .menu-btn{
            display:none;
            width:42px;height:42px;
            border:1px solid var(--line);
            border-radius:11px;
            background:#fff;
            cursor:pointer;
            color:var(--ink);
        }
        .mobile-menu{
            display:none;
            padding:10px 20px 18px;
            border-top:1px solid var(--line);
            background:rgba(255,255,255,.96);
        }
        .mobile-menu.open{display:grid;gap:4px}
        .mobile-menu a{padding:11px 0;font-size:13px;font-weight:700;color:#536159}

        /* RESPONSIVE */
        @media(max-width:980px){
            .nav-links{display:none}
            .menu-btn{display:block}
            .nav-actions .btn-secondary{display:none}
            .hero{padding:58px 0 70px}
            .hero-grid{grid-template-columns:1fr;gap:35px}
            .hero-copy{text-align:center;margin:auto}
            .hero-copy>p{margin-left:auto;margin-right:auto}
            .hero-actions,.proofs{justify-content:center}
            .hero-visual{min-height:420px}
            .feature-grid{grid-template-columns:1fr}
            .section{padding:75px 0}
        }

        @media(max-width:620px){
            .container{width:min(100% - 28px,1160px)}
            .nav{min-height:66px}
            .brand{font-size:19px}
            .brand-mark{width:37px;height:37px}
            .nav-actions{margin-left:auto}
            .nav-actions .btn-primary{min-height:40px;padding:0 13px;font-size:12px}
            .hero{padding:48px 0 58px}
            .hero h1{font-size:42px;letter-spacing:-2.5px}
            .hero-copy>p{font-size:14px;line-height:1.75}
            .hero-actions{flex-direction:column}
            .hero-actions .btn{width:100%}
            .proofs{display:grid;gap:10px}
            .hero-visual{min-height:350px}
            .orb{width:285px;height:285px}
            .market-card{width:100%;padding:17px;border-radius:20px}
            .float{padding:10px 11px}
            .float.a{left:-4px;top:15px}
            .float.b{right:-4px;bottom:14px}
            .stats-grid{grid-template-columns:1fr}
            .stat{padding:20px;border-right:0;border-bottom:1px solid var(--line)}
            .stat:last-child{border-bottom:0}
            .section{padding:60px 0}
            .section-head{margin-bottom:28px}
            .feature{padding:22px}
            .dark-section{padding-bottom:60px}
            .cta{padding:42px 20px;border-radius:21px}
            .footer{flex-direction:column;text-align:center}
        }

        @media(prefers-reduced-motion:reduce){
            html{scroll-behavior:auto}
            *,*:before,*:after{transition:none!important}
        }
    </style>
</head>

<body>
<header class="site-header">
    <nav class="nav container" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="Aavartan home">
            <span class="brand-mark">♻</span>
            Aav<em>artan</em>
        </a>

        <div class="nav-links">
            <a href="#features">Why Aavartan</a>
            <a href="#how-it-works">How it works</a>
            <a href="#about">About</a>
        </div>

        <div class="nav-actions">
            <a class="btn btn-secondary" href="login.php">Log in</a>
            <a class="btn btn-primary" href="signup.php">Get started <span>→</span></a>
            <button class="menu-btn" id="menuBtn" type="button" aria-label="Open menu" aria-expanded="false">☰</button>
        </div>
    </nav>

    <div class="mobile-menu" id="mobileMenu">
        <a href="#features">Why Aavartan</a>
        <a href="#how-it-works">How it works</a>
        <a href="#about">About</a>
        <a href="login.php">Log in</a>
        <a href="signup.php">Create account</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <div class="eyebrow"><span class="pulse"></span> Student project marketplace</div>

                <h1>Build more.<br><span>Waste less.</span></h1>

                <p>
                    Aavartan helps students buy, sell and reuse college project
                    materials — so useful components can move from one project
                    to the next instead of sitting unused.
                </p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="signup.php">Start exploring <span>→</span></a>
                    <a class="btn btn-secondary" href="login.php">I already have an account</a>
                </div>

                <div class="proofs">
                    <span><i class="tick">✓</i> Student focused</span>
                    <span><i class="tick">✓</i> Affordable materials</span>
                    <span><i class="tick">✓</i> Reuse first</span>
                </div>
            </div>

            <div class="hero-visual" aria-label="Marketplace preview">
                <div class="orb"></div>

                <div class="float a">
                    <strong>2,340+</strong>
                    <small>students connected</small>
                </div>

                <div class="market-card">
                    <div class="market-head">
                        <div class="market-title">Marketplace</div>
                        <div class="live">● LIVE</div>
                    </div>

                    <div class="market-search">⌕ &nbsp; Search project materials...</div>

                    <div class="product">
                        <div class="product-icon">🤖</div>
                        <div class="product-info">
                            <h3>Arduino UNO Project Kit</h3>
                            <p>Electronics · Used · Good condition</p>
                        </div>
                        <div class="price">₹450</div>
                    </div>

                    <div class="product">
                        <div class="product-icon">📡</div>
                        <div class="product-info">
                            <h3>IoT Sensor Pack</h3>
                            <p>IoT · Like new · Complete</p>
                        </div>
                        <div class="price">₹280</div>
                    </div>

                    <div class="product">
                        <div class="product-icon">⚙️</div>
                        <div class="product-info">
                            <h3>Mechanical Project Parts</h3>
                            <p>Mechanical · Used · Set</p>
                        </div>
                        <div class="price">₹650</div>
                    </div>
                </div>

                <div class="float b">
                    <strong>1,248+</strong>
                    <small>materials reused</small>
                </div>
            </div>
        </div>
    </section>

    <section class="stats" aria-label="Aavartan highlights">
        <div class="container stats-grid">
            <div class="stat"><strong>Buy</strong><span>Find materials at student-friendly prices</span></div>
            <div class="stat"><strong>Sell</strong><span>List unused project materials easily</span></div>
            <div class="stat"><strong>Reuse</strong><span>Give completed-project materials another life</span></div>
        </div>
    </section>

    <section class="section" id="features">
        <div class="container">
            <div class="section-head">
                <div class="section-label">Built for students</div>
                <h2>Everything you need for the next project.</h2>
                <p>Discover useful components, connect with student sellers and keep project materials moving around campus.</p>
            </div>

            <div class="feature-grid">
                <article class="feature">
                    <div class="feature-icon">🛒</div>
                    <h3>Buy what you need</h3>
                    <p>Browse project materials listed by students and find affordable alternatives for your next build.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon">♻</div>
                    <h3>Give materials a second life</h3>
                    <p>Sell or exchange components you no longer need after completing your project.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon">🎓</div>
                    <h3>Made for campus life</h3>
                    <p>A focused marketplace designed around student projects, budgets and practical needs.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="dark-section" id="how-it-works">
        <div class="container">
            <div class="cta">
                <div class="section-label">Simple from start to finish</div>
                <h2>Find it. Buy it. Build it.</h2>
                <p>Create an account, explore available materials, connect with sellers and get your next project moving.</p>
                <a class="btn btn-primary" href="signup.php">Create your account →</a>
            </div>
        </div>
    </section>

    <section class="section" id="about" style="padding-top:0">
        <div class="container">
            <div class="section-head" style="margin-bottom:0">
                <div class="section-label">About Aavartan</div>
                <h2>Less waste. More projects. More possibilities.</h2>
                <p>
                    Aavartan is built around a simple idea: a material that is
                    finished with one project can still be useful for another.
                    The platform makes that exchange easier for students.
                </p>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container footer">
        <span>© 2026 Aavartan · Built for students, by students.</span>
        <div class="footer-links">
            <a href="login.php">Log in</a>
            <a href="signup.php">Create account</a>
        </div>
    </div>
</footer>

<script>
    const menuBtn = document.getElementById('menuBtn');
    const mobileMenu = document.getElementById('mobileMenu');

    menuBtn?.addEventListener('click', () => {
        const open = mobileMenu.classList.toggle('open');
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menuBtn.textContent = open ? '×' : '☰';
    });

    document.querySelectorAll('#mobileMenu a').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
            menuBtn.setAttribute('aria-expanded', 'false');
            menuBtn.textContent = '☰';
        });
    });
</script>
</body>
</html>
