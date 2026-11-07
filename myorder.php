<?php

session_start();



/* =========================================

   CHECK LOGIN

========================================= */



if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit;

}





/* =========================================

   DATABASE CONNECTION

========================================= */



$host = "localhost";

$dbname = "aavartan_db";

$username = "root";

$password = "";



$conn = new mysqli(

    $host,

    $username,

    $password,

    $dbname

);



if ($conn->connect_error) {

    die("Database connection failed.");

}



$conn->set_charset("utf8mb4");





/* =========================================

   HELPER

========================================= */



function e($value)

{

    return htmlspecialchars(

        (string)($value ?? ""),

        ENT_QUOTES,

        "UTF-8"

    );

}





/* =========================================

   CURRENT USER

========================================= */



$userId = (int)$_SESSION['user_id'];



$userName =

    $_SESSION['full_name']

    ?? 'User';



$userEmail =

    $_SESSION['email']

    ?? '';



/* PROFILE DATA */

$parts = preg_split(

    '/\s+/',

    trim($userName),

    -1,

    PREG_SPLIT_NO_EMPTY

);



$initials = count($parts) >= 2

    ? strtoupper($parts[0][0] . $parts[count($parts) - 1][0])

    : strtoupper(substr($userName, 0, 2));



$fullName = $userName;



$displayName = $fullName;

$displayEmail = $userEmail;

$displayCollege = $_SESSION['college'] ?? '';

$displayCourse = $_SESSION['course'] ?? '';

$displayYear = $_SESSION['year'] ?? '';

$displayInitials = $initials;



















/* =========================================

   FETCH USER ORDERS

========================================= */



$stmt = $conn->prepare("

    SELECT

        o.id,

        o.buyer_id,

        o.listing_id,

        o.seller_id,

        o.material_name,

        o.quantity,

        o.price,

        o.total_price,

        o.listing_type,

        o.handover_method,

        o.note,

        o.payment_method,

        o.payment_status,

        o.order_status,

        o.created_at,



        l.category,

        l.item_condition,

        l.image_path,

        l.location,

        l.seller_name,

        l.seller_college



    FROM orders o



    LEFT JOIN listings l

        ON o.listing_id = l.id



    WHERE o.buyer_id = ?



    ORDER BY o.created_at DESC

");



$stmt->bind_param(

    "i",

    $userId

);



$stmt->execute();



$result = $stmt->get_result();



$orders = [];



while ($row = $result->fetch_assoc()) {

    $orders[] = $row;

}



$stmt->close();





/* =========================================

   ORDER COUNTS

========================================= */



$totalOrders = count($orders);



$pendingOrders = 0;

$completedOrders = 0;



foreach ($orders as $order) {



    $status = strtolower(

        trim($order['order_status'] ?? '')

    );



    if ($status === 'pending') {

        $pendingOrders++;

    }



    if (

        $status === 'completed'

        || $status === 'delivered'

    ) {

        $completedOrders++;

    }

}



?>



<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Orders | Aavartan</title>



<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">



<style>

:root{

    --green:#16a34a;

    --green2:#0f8f43;

    --green-soft:#e8f8ef;

    --dark:#10221a;

    --muted:#718078;

    --line:#e1ebe5;

    --bg:#f5f9f6;

    --white:#fff;

    --amber:#a66a00;

    --red:#b42318;

}



*{box-sizing:border-box;margin:0;padding:0}



html{scroll-behavior:smooth}



body{

    font-family:"DM Sans",sans-serif;

    background:

        radial-gradient(circle at 10% 0%,rgba(22,163,74,.07),transparent 28%),

        var(--bg);

    color:var(--dark);

    min-height:100vh;

}



a{text-decoration:none;color:inherit}



/* =========================================================
   MASTER AAVARTAN NAVBAR — shared across all pages
   ========================================================= */
.navbar{
    height:76px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(16px);
    border-bottom:1px solid var(--line);
    position:sticky;
    top:0;
    z-index:1000;
}
.nav-inner{
    max-width:1320px;
    height:100%;
    margin:auto;
    padding:0 24px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
}
.logo{
    display:flex;
    align-items:center;
    gap:10px;
    font-family:"Plus Jakarta Sans",sans-serif;
    font-weight:800;
    font-size:21px;
    white-space:nowrap;
    color:var(--dark);
    text-decoration:none;
}
.logo-mark{
    width:43px;
    height:43px;
    display:grid;
    place-items:center;
    border-radius:14px;
    background:linear-gradient(145deg,#f1fff5,#dcfce7);
    box-shadow:0 7px 18px rgba(22,163,74,.14);
}
.logo-mark svg{width:34px;height:34px;display:block}
.main-nav{
    display:flex;
    align-items:center;
    gap:4px;
    flex:1;
    justify-content:center;
}
.nav-link{
    padding:10px 12px;
    border-radius:10px;
    color:#526059;
    font-size:13px;
    font-weight:700;
    display:flex;
    align-items:center;
    gap:7px;
    white-space:nowrap;
    text-decoration:none;
    transition:.2s ease;
}
.nav-link:hover,.nav-link.active{color:var(--green);background:#f0faf4}
.nav-icon{font-size:14px}
.nav-right{display:flex;align-items:center;gap:10px}
.profile-wrap{position:relative}
.profile{
    width:42px;
    height:42px;
    border:1px solid #c7ead5;
    border-radius:50%;
    background:#d9f8e6;
    color:#166534;
    font-weight:800;
    cursor:pointer;
}
.profile-menu{
    position:absolute;
    right:0;
    top:52px;
    width:300px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:17px;
    padding:15px;
    box-shadow:0 20px 50px rgba(16,34,26,.14);
    display:none;
    z-index:1200;
}
.profile-menu.show{display:block}
.profile-head{
    display:flex;
    align-items:center;
    gap:11px;
    padding-bottom:13px;
    border-bottom:1px solid var(--line);
}
.profile-large{
    width:44px;
    height:44px;
    flex:0 0 44px;
    border-radius:50%;
    background:#dcfce7;
    color:#166534;
    display:grid;
    place-items:center;
    font-weight:800;
}
.profile-head strong{display:block;font-size:13px}
.profile-head small{
    display:block;
    color:#718078;
    font-size:11px;
    margin-top:3px;
    max-width:210px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.profile-details{display:grid;gap:8px;padding:12px 0}
.profile-details div{display:flex;justify-content:space-between;gap:12px;font-size:11px}
.profile-details b{color:#718078}
.profile-details span{
    max-width:180px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.profile-nav{
    display:grid;
    gap:4px;
    border-top:1px solid var(--line);
    padding-top:10px;
}
.profile-nav a{
    padding:9px 10px;
    border-radius:9px;
    font-size:12px;
    font-weight:700;
    text-decoration:none;
    color:#405048;
}
.profile-nav a:hover{background:#f0faf4;color:var(--green)}
.profile-nav .logout{
    background:#122019;
    color:white;
    text-align:center;
    margin-top:4px;
}
.mobile-menu-btn{
    display:none;
    border:1px solid var(--line);
    background:white;
    width:40px;
    height:40px;
    border-radius:10px;
    font-size:18px;
    cursor:pointer;
}
@media(max-width:850px){
    .main-nav{
        display:none;
        position:absolute;
        left:15px;
        right:15px;
        top:86px;
        padding:10px;
        background:#fff;
        border:1px solid var(--line);
        border-radius:16px;
        box-shadow:0 18px 45px rgba(0,0,0,.12);
        flex-direction:column;
        align-items:stretch;
    }
    .main-nav.open{display:flex}
    .nav-link{width:100%;padding:12px}
    .mobile-menu-btn{display:flex;align-items:center;justify-content:center}
}
@media(max-width:650px){
    .nav-inner{padding:0 15px}
    .profile-menu{right:-4px;width:280px}
}

.container{

    width:min(1180px,92%);

    margin:0 auto;

    padding:42px 0 70px;

}



.page-head{

    display:flex;

    justify-content:space-between;

    align-items:flex-end;

    gap:24px;

    margin-bottom:26px;

}



.kicker{

    display:inline-flex;

    align-items:center;

    gap:8px;

    color:var(--green2);

    font-size:12px;

    font-weight:800;

    letter-spacing:.13em;

    text-transform:uppercase;

    margin-bottom:9px;

}



.kicker-dot{

    width:7px;height:7px;border-radius:50%;

    background:var(--green);

    box-shadow:0 0 0 5px var(--green-soft);

}



h1{

    font-family:"Plus Jakarta Sans",sans-serif;

    font-size:clamp(30px,4vw,46px);

    line-height:1.08;

    letter-spacing:-.04em;

}



.subtitle{

    color:var(--muted);

    margin-top:10px;

    line-height:1.6;

    max-width:600px;

}



.order-count{

    background:#fff;

    border:1px solid var(--line);

    border-radius:15px;

    padding:12px 15px;

    color:var(--muted);

    font-size:13px;

    white-space:nowrap;

}



.order-count strong{color:var(--dark);font-size:17px}



.stats{

    display:grid;

    grid-template-columns:repeat(3,1fr);

    gap:14px;

    margin-bottom:28px;

}



.stat{

    position:relative;

    overflow:hidden;

    background:#fff;

    border:1px solid var(--line);

    border-radius:18px;

    padding:19px 20px;

    box-shadow:0 8px 26px rgba(16,34,26,.045);

}



.stat:after{

    content:"";

    position:absolute;

    right:-24px;bottom:-30px;

    width:85px;height:85px;

    border-radius:50%;

    background:var(--green-soft);

}



.stat-label{

    color:var(--muted);

    font-size:12px;

    font-weight:700;

    margin-bottom:7px;

}



.stat strong{

    font-family:"Plus Jakarta Sans",sans-serif;

    font-size:27px;

}



.stat-icon{

    position:absolute;

    right:17px;top:17px;

    width:36px;height:36px;

    border-radius:11px;

    display:grid;place-items:center;

    background:var(--green-soft);

    color:var(--green2);

    font-size:17px;

    z-index:2;

}



.orders{display:grid;gap:17px}



.order-card{

    background:#fff;

    border:1px solid var(--line);

    border-radius:22px;

    padding:20px;

    box-shadow:0 10px 32px rgba(16,34,26,.05);

    transition:.22s ease;

}



.order-card:hover{

    transform:translateY(-2px);

    border-color:#c9dfd1;

    box-shadow:0 16px 38px rgba(16,34,26,.08);

}



.order-top{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding-bottom:15px;

    border-bottom:1px solid #edf2ef;

    margin-bottom:17px;

}



.order-meta{

    display:flex;

    align-items:center;

    gap:9px;

    flex-wrap:wrap;

}



.order-id{

    color:var(--green2);

    font-weight:800;

    font-size:13px;

}



.order-date{

    color:#8a958f;

    font-size:12px;

}



.status{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:6px 10px;

    border-radius:999px;

    font-size:11px;

    font-weight:800;

    border:1px solid transparent;

}



.status:before{

    content:"";

    width:6px;height:6px;border-radius:50%;

    background:currentColor;

}



.status.pending{background:#fff6df;color:var(--amber);border-color:#f2dfad}

.status.completed,.status.delivered{background:#eaf8ef;color:var(--green2);border-color:#cbe8d5}

.status.cancelled{background:#fff0ef;color:var(--red);border-color:#f1cdca}

.status.default{background:#f1f4f2;color:#5e6b64;border-color:#e0e6e2}



.product{

    display:grid;

    grid-template-columns:138px 1fr auto;

    gap:20px;

    align-items:center;

}



.image-wrap{

    position:relative;

    width:138px;height:138px;

}



.product-image,.no-image{

    width:100%;height:100%;

    border-radius:17px;

    object-fit:cover;

    background:linear-gradient(145deg,#eef7f1,#f7faf8);

    border:1px solid #e1ebe5;

}



.no-image{

    display:grid;

    place-items:center;

    font-size:38px;

}



.image-label{

    position:absolute;

    left:8px;bottom:8px;

    background:rgba(16,34,26,.82);

    color:#fff;

    border-radius:8px;

    padding:5px 8px;

    font-size:10px;

    font-weight:800;

}



.badges{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:9px}



.badge{

    padding:5px 9px;

    border-radius:999px;

    background:var(--green-soft);

    color:var(--green2);

    font-size:10px;

    font-weight:800;

}



.badge.type{background:#fff4d8;color:#916800}



.product-info h2{

    font-family:"Plus Jakarta Sans",sans-serif;

    font-size:22px;

    line-height:1.25;

    margin-bottom:7px;

    letter-spacing:-.025em;

}



.product-info p{

    color:#718078;

    font-size:13px;

    line-height:1.55;

}



.product-info .seller{

    margin-top:8px;

    color:#536159;

}



.product-info .seller strong{color:var(--dark)}



.order-price{

    min-width:145px;

    text-align:right;

    padding-left:12px;

}



.order-price small{

    display:block;

    color:#89948e;

    font-size:11px;

    font-weight:700;

    margin-bottom:3px;

}



.order-price strong{

    font-family:"Plus Jakarta Sans",sans-serif;

    color:var(--green2);

    font-size:24px;

    letter-spacing:-.03em;

}



.details{

    display:grid;

    grid-template-columns:repeat(4,1fr);

    gap:10px;

    margin-top:19px;

    padding-top:17px;

    border-top:1px solid #edf2ef;

}



.detail{

    background:#f7faf8;

    border:1px solid #edf2ef;

    border-radius:12px;

    padding:11px 12px;

}



.detail small{

    display:block;

    color:#8a958f;

    font-size:10px;

    font-weight:700;

    margin-bottom:5px;

    text-transform:uppercase;

    letter-spacing:.04em;

}



.detail strong{

    font-size:12px;

    word-break:break-word;

}



.seller-info{

    margin-top:14px;

    color:#718078;

    font-size:12px;

}



.seller-info strong{color:var(--dark)}



.order-note{

    margin-top:12px;

    padding:11px 13px;

    background:#f7faf8;

    border-left:3px solid #a9d6b9;

    border-radius:9px;

    color:#68766f;

    font-size:12px;

    line-height:1.6;

}



.order-note strong{color:var(--dark)}



.order-footer{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-top:15px;

    padding-top:14px;

    border-top:1px solid #edf2ef;

}



.order-status-line{

    color:#718078;

    font-size:12px;

    display:flex;

    align-items:center;

    gap:8px;

    flex-wrap:wrap;

}



.view-product{

    display:inline-flex;

    align-items:center;

    gap:7px;

    background:var(--dark);

    color:#fff;

    padding:10px 14px;

    border-radius:11px;

    font-size:12px;

    font-weight:800;

    transition:.2s;

}



.view-product:hover{

    background:var(--green2);

    transform:translateY(-1px);

}



.empty{

    background:#fff;

    border:1px solid var(--line);

    border-radius:24px;

    padding:72px 25px;

    text-align:center;

    box-shadow:0 10px 35px rgba(16,34,26,.045);

}



.empty-icon{

    width:78px;height:78px;

    margin:0 auto 18px;

    border-radius:23px;

    display:grid;place-items:center;

    background:var(--green-soft);

    font-size:37px;

}



.empty h2{

    font-family:"Plus Jakarta Sans",sans-serif;

    font-size:25px;

    margin-bottom:8px;

}



.empty p{

    color:var(--muted);

    line-height:1.6;

    max-width:500px;

    margin:0 auto 21px;

}



.shop-btn{

    display:inline-flex;

    align-items:center;

    gap:8px;

    background:linear-gradient(135deg,var(--green),var(--green2));

    color:#fff;

    padding:12px 18px;

    border-radius:12px;

    font-weight:800;

    box-shadow:0 9px 20px rgba(22,163,74,.18);

}



@media(max-width:850px){

    .product{grid-template-columns:110px 1fr}

    .image-wrap{width:110px;height:110px}

    .order-price{

        grid-column:2;

        text-align:left;

        padding-left:0;

    }

    .details{grid-template-columns:repeat(2,1fr)}

}











</style>

</head>



<body>



<nav class="navbar">
    <div class="nav-inner">
        <a href="dashboard.php" class="logo" aria-label="Aavartan Home">
            <div class="logo-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" role="img">
                    <defs>
                        <linearGradient id="aavartanLogo" x1="8" y1="6" x2="40" y2="42" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#22c55e"/>
                            <stop offset="1" stop-color="#0d8f43"/>
                        </linearGradient>
                    </defs>
                    <circle cx="24" cy="24" r="21" fill="#e9f9ef"/>
                    <path d="M14 20.5A11 11 0 0 1 32.8 13l2.5 2.5M35.3 10.5v5.8h-5.8M34 27.5A11 11 0 0 1 15.2 35l-2.5-2.5M12.7 37.5v-5.8h5.8" fill="none" stroke="url(#aavartanLogo)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 28.5h10L24 18z" fill="#10221a"/>
                </svg>
            </div>
            <span class="brand-name">Aavartan</span>
        </a>

        <div class="main-nav" id="mainNav">
            <a href="dashboard.php#market" class="nav-link">
                <span class="nav-icon">🛒</span>Marketplace
            </a>
            <a href="mylisting.php" class="nav-link">
                <span class="nav-icon">📦</span>My Listings
            </a>
            <a href="myorder.php" class="nav-link active" aria-current="page">
                <span class="nav-icon">🧾</span>My Orders
            </a>
            <a href="orderstatus.php" class="nav-link">
                <span class="nav-icon">🔔</span>Order Status
            </a>
        </div>

        <div class="nav-right">
            <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
            <div class="profile-wrap">
                <button class="profile" id="profileButton" title="<?php echo e($displayName); ?>" aria-label="Open profile menu" aria-expanded="false" type="button"><?php echo e($displayInitials); ?></button>
                <div class="profile-menu" id="profileMenu">
                    <div class="profile-head">
                        <div class="profile-large"><?php echo e($displayInitials); ?></div>
                        <div>
                            <strong><?php echo e($displayName); ?></strong>
                            <small><?php echo e($displayEmail); ?></small>
                        </div>
                    </div>
                    <div class="profile-details">
                        <div><b>College</b><span><?php echo e($displayCollege ?: 'Not added'); ?></span></div>
                        <div><b>Course</b><span><?php echo e($displayCourse ?: 'Not added'); ?></span></div>
                        <div><b>Year</b><span><?php echo e($displayYear ?: 'Not added'); ?></span></div>
                    </div>
                    <div class="profile-nav">
                        <a href="dashboard.php#market">🛒 &nbsp;Marketplace</a>
                        <a href="mylisting.php">📦 &nbsp;My Listings</a>
                        <a href="myorder.php">🧾 &nbsp;My Orders</a>
                        <a href="orderstatus.php">🔔 &nbsp;Order Status</a>
                        <a href="logout.php" class="logout">↪ &nbsp;Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>



<main class="container">



    <header class="page-head">

        <div>

            <div class="kicker">

                <span class="kicker-dot"></span>

                Purchase history

            </div>

            <h1>My Orders</h1>

            <p class="subtitle">

                Keep track of your materials, payment status and order progress in one place.

            </p>

        </div>



        <div class="order-count">

            <strong><?php echo $totalOrders; ?></strong> order<?php echo $totalOrders == 1 ? '' : 's'; ?>

        </div>

    </header>



    <section class="stats">

        <div class="stat">

            <div class="stat-icon">▣</div>

            <div class="stat-label">TOTAL ORDERS</div>

            <strong><?php echo $totalOrders; ?></strong>

        </div>



        <div class="stat">

            <div class="stat-icon">◷</div>

            <div class="stat-label">PENDING</div>

            <strong><?php echo $pendingOrders; ?></strong>

        </div>



        <div class="stat">

            <div class="stat-icon">✓</div>

            <div class="stat-label">COMPLETED</div>

            <strong><?php echo $completedOrders; ?></strong>

        </div>

    </section>



    <?php if (empty($orders)): ?>



        <section class="empty">

            <div class="empty-icon">📦</div>

            <h2>No Orders Yet</h2>

            <p>

                You haven't placed any orders yet. Explore the Aavartan marketplace

                and find something useful for your next college project.

            </p>

            <a href="dashboard.php" class="shop-btn">Explore Marketplace →</a>

        </section>



    <?php else: ?>



        <section class="orders">



        <?php foreach ($orders as $order): ?>



            <?php

                $orderStatus = trim($order['order_status'] ?? 'Pending');

                $statusClass = strtolower($orderStatus);



                if (!in_array(

                    $statusClass,

                    ['pending','completed','delivered','cancelled'],

                    true

                )) {

                    $statusClass = 'default';

                }



                $paymentStatus = trim($order['payment_status'] ?? 'Pending');

                $paymentClass = strtolower($paymentStatus);

            ?>



            <article class="order-card">



                <div class="order-top">

                    <div class="order-meta">

                        <span class="order-id">

                            ORDER #<?php echo (int)$order['id']; ?>

                        </span>

                        <span class="order-date">

                            <?php

                            echo date(

                                'd M Y, h:i A',

                                strtotime($order['created_at'])

                            );

                            ?>

                        </span>

                    </div>



                    <span class="status <?php echo e($statusClass); ?>">

                        <?php echo e($orderStatus); ?>

                    </span>

                </div>



                <div class="product">



                    <div class="image-wrap">

                        <?php

                        $image = trim((string)($order['image_path'] ?? ''));

                        ?>



                        <?php if ($image): ?>

                            <img

                                class="product-image"

                                src="<?php echo e($image); ?>"

                                alt="<?php echo e($order['material_name']); ?>"

                                onerror="

                                    this.style.display='none';

                                    this.nextElementSibling.style.display='grid';

                                "

                            >

                            <div class="no-image" style="display:none;">📦</div>

                        <?php else: ?>

                            <div class="no-image">📦</div>

                        <?php endif; ?>



                        <span class="image-label">ORDERED ITEM</span>

                    </div>



                    <div class="product-info">



                        <div class="badges">

                            <?php if (!empty($order['category'])): ?>

                                <span class="badge">

                                    <?php echo e($order['category']); ?>

                                </span>

                            <?php endif; ?>



                            <?php if (!empty($order['item_condition'])): ?>

                                <span class="badge">

                                    <?php echo e($order['item_condition']); ?>

                                </span>

                            <?php endif; ?>



                            <span class="badge type">

                                <?php echo e($order['listing_type']); ?>

                            </span>

                        </div>



                        <h2><?php echo e($order['material_name']); ?></h2>



                        <p class="seller">

                            Seller:

                            <strong>

                                <?php echo e($order['seller_name'] ?? 'Seller'); ?>

                            </strong>

                        </p>



                        <?php if (!empty($order['seller_college'])): ?>

                            <p><?php echo e($order['seller_college']); ?></p>

                        <?php endif; ?>



                    </div>



                    <div class="order-price">

                        <small>TOTAL VALUE</small>



                        <?php if (strtolower($order['listing_type']) === 'donate'): ?>



                            <strong>FREE</strong>



                        <?php elseif (strtolower($order['listing_type']) === 'exchange'): ?>



                            <strong>EXCHANGE</strong>



                        <?php else: ?>



                            <strong>

                                ₹<?php

                                echo number_format(

                                    (float)($order['total_price']),

                                    2

                                );

                                ?>

                            </strong>



                        <?php endif; ?>

                    </div>



                </div>



                <div class="details">



                    <div class="detail">

                        <small>Quantity</small>

                        <strong><?php echo (int)$order['quantity']; ?></strong>

                    </div>



                    <div class="detail">

                        <small>Handover</small>

                        <strong>

                            <?php echo e($order['handover_method'] ?? 'Not specified'); ?>

                        </strong>

                    </div>



                    <div class="detail">

                        <small>Payment</small>

                        <strong>

                            <?php echo e($order['payment_method'] ?? 'Not specified'); ?>

                        </strong>

                    </div>



                    <div class="detail">

                        <small>Payment Status</small>

                        <strong>

                            <span class="status <?php echo $paymentClass === 'paid' ? 'completed' : 'pending'; ?>">

                                <?php echo e($paymentStatus); ?>

                            </span>

                        </strong>

                    </div>



                </div>



                <div class="seller-info">

                    Seller:

                    <strong><?php echo e($order['seller_name'] ?? 'Seller'); ?></strong>



                    <?php if (!empty($order['location'])): ?>

                        &nbsp; • &nbsp; <?php echo e($order['location']); ?>

                    <?php endif; ?>

                </div>



                <?php if (!empty(trim($order['note'] ?? ''))): ?>

                    <div class="order-note">

                        <strong>Your message:</strong>

                        <?php echo nl2br(e($order['note'])); ?>

                    </div>

                <?php endif; ?>



                <div class="order-footer">

                    <div class="order-status-line">

                        <span>Order status</span>

                        <span class="status <?php echo e($statusClass); ?>">

                            <?php echo e($orderStatus); ?>

                        </span>

                    </div>



                    <?php if (!empty($order['listing_id'])): ?>

                        <a

                            class="view-product"

                            href="view-product.php?id=<?php echo (int)$order['listing_id']; ?>"

                        >

                            View Product →

                        </a>

                    <?php endif; ?>

                </div>



            </article>



        <?php endforeach; ?>



        </section>



    <?php endif; ?>



</main>





<script>
/* =========================================================
   MASTER NAVBAR JAVASCRIPT
   ========================================================= */
const mobileMenuBtn = document.getElementById("mobileMenuBtn");
const mainNav = document.getElementById("mainNav");

if (mobileMenuBtn && mainNav) {
    mobileMenuBtn.addEventListener("click", function(event) {
        event.stopPropagation();
        const isOpen = mainNav.classList.toggle("open");
        mobileMenuBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        mobileMenuBtn.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
        mobileMenuBtn.innerHTML = isOpen ? "✕" : "☰";
    });

    mainNav.querySelectorAll(".nav-link").forEach(function(link) {
        link.addEventListener("click", function() {
            mainNav.classList.remove("open");
            mobileMenuBtn.setAttribute("aria-expanded", "false");
            mobileMenuBtn.setAttribute("aria-label", "Open navigation");
            mobileMenuBtn.innerHTML = "☰";
        });
    });

    document.addEventListener("click", function(event) {
        if (!mainNav.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
            mainNav.classList.remove("open");
            mobileMenuBtn.setAttribute("aria-expanded", "false");
            mobileMenuBtn.setAttribute("aria-label", "Open navigation");
            mobileMenuBtn.innerHTML = "☰";
        }
    });
}

const profileButton = document.getElementById("profileButton");
const profileMenu = document.getElementById("profileMenu");

if (profileButton && profileMenu) {
    profileButton.addEventListener("click", function(event) {
        event.stopPropagation();
        const isOpen = profileMenu.classList.toggle("show");
        profileButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    profileMenu.addEventListener("click", function(event) {
        event.stopPropagation();
    });

    document.addEventListener("click", function() {
        profileMenu.classList.remove("show");
        profileButton.setAttribute("aria-expanded", "false");
    });
}
</script>



</body>

</html>



<?php

$conn->close();

?>
