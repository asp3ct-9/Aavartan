<?php
session_start();

/*
|--------------------------------------------------------------------------
| AAVARTAN / PROJECTWASTE DASHBOARD
|--------------------------------------------------------------------------
| PHP + MySQL
| Database : aavartan_db
| Users    : users
| Products : listings
|--------------------------------------------------------------------------
*/

/* =========================================================
   DATABASE CONFIGURATION
   ========================================================= */

$host = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "aavartan_db";


/* =========================================================
   REQUIRE LOGIN
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}


/* =========================================================
   DATABASE CONNECTION
   ========================================================= */

$conn = new mysqli(
    $host,
    $dbUser,
    $dbPassword,
    $dbName
);

if ($conn->connect_error) {
    die(
        "Database connection failed: " .
        htmlspecialchars(
            $conn->connect_error,
            ENT_QUOTES,
            "UTF-8"
        )
    );
}

$conn->set_charset("utf8mb4");

$userId = (int) $_SESSION["user_id"];


/* =========================================================
   GET LOGGED-IN USER
   ========================================================= */

$stmt = $conn->prepare(
    "SELECT
        id,
        full_name,
        email,
        college,
        course,
        year,
        role
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to prepare user request.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   INVALID USER / SESSION
   ========================================================= */

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}


/* =========================================================
   SYNCHRONIZE SESSION
   ========================================================= */

$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"]     = $user["email"];
$_SESSION["college"]   = $user["college"];
$_SESSION["course"]    = $user["course"];
$_SESSION["year"]      = $user["year"];
$_SESSION["role"]      = $user["role"];


/* =========================================================
   USER INITIALS
   ========================================================= */

$fullName = trim($user["full_name"]);

$nameParts = preg_split(
    '/\s+/',
    $fullName,
    -1,
    PREG_SPLIT_NO_EMPTY
);

if (count($nameParts) >= 2) {

    $initials = strtoupper(
        mb_substr($nameParts[0], 0, 1) .
        mb_substr(
            $nameParts[count($nameParts) - 1],
            0,
            1
        )
    );

} elseif (count($nameParts) === 1) {

    $initials = strtoupper(
        mb_substr($nameParts[0], 0, 2)
    );

} else {

    $initials = "U";
}


/* =========================================================
   ESCAPED USER DATA
   ========================================================= */

$displayName = htmlspecialchars(
    $fullName,
    ENT_QUOTES,
    "UTF-8"
);

$displayInitials = htmlspecialchars(
    $initials,
    ENT_QUOTES,
    "UTF-8"
);

$displayEmail = htmlspecialchars(
    $user["email"],
    ENT_QUOTES,
    "UTF-8"
);

$displayCollege = htmlspecialchars(
    $user["college"],
    ENT_QUOTES,
    "UTF-8"
);

$displayCourse = htmlspecialchars(
    $user["course"],
    ENT_QUOTES,
    "UTF-8"
);

$displayYear = htmlspecialchars(
    $user["year"],
    ENT_QUOTES,
    "UTF-8"
);


/* =========================================================
   HELPER FUNCTION
   ========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   PRODUCT PRICE
   ========================================================= */

function getListingPrice($item)
{
    $type = $item["listing_type"] ?? "Sell";

    if ($type === "Donate") {
        return "FREE";
    }

    if ($type === "Exchange") {
        return "EXCHANGE";
    }

    return "₹" . number_format(
        (float)($item["price"] ?? 0),
        0
    );
}


/* =========================================================
   PRODUCT ICON
   ========================================================= */

function getListingIcon($category)
{
    $category = strtolower(
        trim((string)$category)
    );

    if (strpos($category, "arduino") !== false) {
        return "🔌";
    }

    if (strpos($category, "sensor") !== false) {
        return "📡";
    }

    if (strpos($category, "robot") !== false) {
        return "🤖";
    }

    if (strpos($category, "mechanical") !== false) {
        return "⚙️";
    }

    if (
        strpos($category, "cable") !== false ||
        strpos($category, "wire") !== false
    ) {
        return "🔗";
    }

    if (
        strpos($category, "power") !== false ||
        strpos($category, "battery") !== false
    ) {
        return "🔋";
    }

    if (
        strpos($category, "electronics") !== false ||
        strpos($category, "electronic") !== false
    ) {
        return "💡";
    }

    if (strpos($category, "iot") !== false) {
        return "📶";
    }

    return "📦";
}


/* =========================================================
   FETCH ACTIVE MARKETPLACE LISTINGS
   ========================================================= */

$listings = [];

$listingStmt = $conn->prepare(
    "SELECT
        id,
        seller_id,
        seller_name,
        seller_email,
        seller_college,
        material_name,
        category,
        item_condition,
        listing_type,
        price,
        original_price,
        quantity,
        location,
        description,
        contact_preference,
        handover_method,
        image_path,
        image_name,
        status,
        created_at
     FROM listings
     WHERE status = 'active'
     ORDER BY created_at DESC"
);

if ($listingStmt) {

    $listingStmt->execute();

    $listingResult = $listingStmt->get_result();

    while ($row = $listingResult->fetch_assoc()) {
        $listings[] = $row;
    }

    $listingStmt->close();
}


/* =========================================================
   FIRST THREE LISTINGS FOR HERO CARD
   ========================================================= */

$heroListings = array_slice(
    $listings,
    0,
    3
);


/* =========================================================
   MARKETPLACE STATISTICS
   ========================================================= */

$totalListings = count($listings);

$totalQuantity = 0;

foreach ($listings as $listing) {
    $totalQuantity += (int)$listing["quantity"];
}


/* =========================================================
   CLOSE DATABASE
   ========================================================= */

$conn->close();

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Aavartan | Student Marketplace</title>


<style>

/* =========================================================
   GLOBAL
   ========================================================= */

@import url(
    'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap'
);

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

:root{
    --green:#16a34a;
    --dark:#122019;
    --muted:#66736c;
    --light:#f5f8f6;
    --border:#e4ebe7;
    --white:#ffffff;
}

body{
    font-family:"DM Sans",sans-serif;
    background:#f7f9f8;
    color:var(--dark);
}


/* =========================================================
   MASTER AAVARTAN NAVBAR
   ========================================================= */
.navbar{height:76px;background:rgba(255,255,255,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1000}
.nav-inner{max-width:1320px;height:100%;margin:auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.logo{display:flex;align-items:center;gap:10px;font-family:"Plus Jakarta Sans";font-weight:800;font-size:21px;white-space:nowrap;color:var(--dark);text-decoration:none}
.logo-mark{width:43px;height:43px;display:grid;place-items:center;border-radius:14px;background:linear-gradient(145deg,#f1fff5,#dcfce7);box-shadow:0 7px 18px rgba(22,163,74,.14)}
.logo-mark svg{width:34px;height:34px;display:block}
.main-nav{display:flex;align-items:center;gap:4px;flex:1;justify-content:center}
.nav-link{padding:10px 12px;border-radius:10px;color:#526059;font-size:13px;font-weight:700;display:flex;align-items:center;gap:7px;white-space:nowrap;text-decoration:none}
.nav-link:hover,.nav-link.active{color:var(--green);background:#f0faf4}
.nav-icon{font-size:14px}
.nav-right{display:flex;align-items:center;gap:10px}
.profile-wrap{position:relative}
.profile{width:42px;height:42px;border:1px solid #c7ead5;border-radius:50%;background:#d9f8e6;color:#166534;font-weight:800;cursor:pointer}
.profile-menu{position:absolute;right:0;top:52px;width:300px;background:#fff;border:1px solid var(--border);border-radius:17px;padding:15px;box-shadow:0 20px 50px rgba(16,34,26,.14);display:none;z-index:1200}
.profile-menu.show{display:block}
.profile-head{display:flex;align-items:center;gap:11px;padding-bottom:13px;border-bottom:1px solid var(--border)}
.profile-large{width:44px;height:44px;border-radius:50%;background:#dcfce7;color:#166534;display:grid;place-items:center;font-weight:800}
.profile-head strong{display:block;font-size:13px}
.profile-head small{display:block;color:#718078;font-size:11px;margin-top:3px;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.profile-details{display:grid;gap:8px;padding:12px 0}
.profile-details div{display:flex;justify-content:space-between;gap:12px;font-size:11px}
.profile-details b{color:#718078}
.profile-details span{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.profile-nav{display:grid;gap:4px;border-top:1px solid var(--border);padding-top:10px}
.profile-nav a{padding:9px 10px;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;color:#405048}
.profile-nav a:hover{background:#f0faf4;color:var(--green)}
.profile-nav .logout{background:#122019;color:white;text-align:center;margin-top:4px}
.mobile-menu-btn{display:none;border:1px solid var(--border);background:white;width:40px;height:40px;border-radius:10px;font-size:18px;cursor:pointer}
@media(max-width:850px){
 .main-nav{display:none;position:absolute;left:15px;right:15px;top:86px;padding:10px;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 18px 45px rgba(0,0,0,.12);flex-direction:column;align-items:stretch}
 .main-nav.open{display:flex}.nav-link{width:100%;padding:12px}.mobile-menu-btn{display:flex;align-items:center;justify-content:center}
}
@media(max-width:650px){.nav-inner{padding:0 15px}.profile-menu{right:-4px;width:280px}}
/* =========================================================
   HERO
   ========================================================= */

.hero{
    padding:75px 6% 65px;

    background:
        radial-gradient(
            circle at 80% 20%,
            #dcfce7 0,
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #f4fbf6,
            #ffffff
        );

    display:grid;

    grid-template-columns:
        1.1fr .9fr;

    align-items:center;

    gap:50px;
}

.hero h1{
    font-family:"Plus Jakarta Sans";

    font-size:54px;

    line-height:1.08;

    letter-spacing:-2px;

    max-width:650px;
}

.hero h1 span{
    color:var(--green);
}

.hero p{
    margin-top:22px;

    max-width:580px;

    color:#637068;

    font-size:17px;

    line-height:1.7;
}

.hero-buttons{
    margin-top:30px;

    display:flex;

    gap:14px;

    flex-wrap:wrap;
}

.btn-large{
    min-width:165px;

    padding:14px 22px;

    font-size:15px;

    text-align:center;
}

.btn-outline{
    background:white;

    border:1px solid #dce5df;

    color:#34433b;
}

.btn-outline:hover{
    border-color:var(--green);

    color:var(--green);

    transform:translateY(-2px);
}


/* HERO CARD */

.hero-card{
    background:white;

    border:1px solid var(--border);

    border-radius:25px;

    padding:25px;

    box-shadow:
        0 20px 50px rgba(0,0,0,.07);
}

.hero-card-top{
    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-bottom:20px;
}

.hero-card-title{
    font-family:"Plus Jakarta Sans";

    font-weight:700;
}

.live{
    background:#dcfce7;

    color:#15803d;

    padding:6px 10px;

    border-radius:20px;

    font-size:11px;

    font-weight:700;
}


/* HERO PRODUCT */

.hero-product-link{
    display:block;

    text-decoration:none;

    color:inherit;
}

.material-mini{
    display:flex;

    gap:15px;

    padding:14px;

    border:1px solid var(--border);

    border-radius:15px;

    margin-top:10px;

    transition:.2s;
}

.material-mini:hover{
    border-color:#bbf7d0;

    background:#fbfefc;

    transform:translateY(-2px);
}

.material-image{
    width:65px;
    height:65px;

    flex-shrink:0;

    border-radius:12px;

    background:#eef5f1;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:31px;

    overflow:hidden;
}

.material-image img{
    width:100%;
    height:100%;

    object-fit:cover;
}

.material-mini h4{
    font-size:14px;

    overflow:hidden;

    text-overflow:ellipsis;

    white-space:nowrap;
}

.material-mini p{
    font-size:12px;

    margin:5px 0;

    color:#78847e;
}

.price{
    font-weight:800;

    color:var(--green);
}


/* =========================================================
   STATS
   ========================================================= */

.stats{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:18px;

    padding:35px 6%;

    background:white;

    border-bottom:1px solid var(--border);
}

.stat{
    text-align:center;
}

.stat h2{
    font-family:"Plus Jakarta Sans";

    font-size:28px;
}

.stat p{
    color:#78847e;

    margin-top:4px;

    font-size:13px;
}


/* =========================================================
   MARKETPLACE
   ========================================================= */

.market{
    padding:65px 6%;
}

.section-head{
    display:flex;

    justify-content:space-between;

    align-items:end;

    margin-bottom:25px;
}

.section-head h2{
    font-family:"Plus Jakarta Sans";

    font-size:29px;
}

.section-head p{
    color:#758078;

    margin-top:7px;
}

.view{
    color:var(--green);

    font-weight:700;

    font-size:14px;
}


/* =========================================================
   SEARCH
   ========================================================= */

.search-box{
    display:flex;

    gap:12px;

    margin-bottom:22px;
}

.search{
    flex:1;

    height:50px;

    border:1px solid var(--border);

    border-radius:12px;

    padding:0 17px;

    font-family:inherit;

    font-size:14px;

    outline:none;

    background:white;
}

.search:focus{
    border-color:#86efac;
}

.filter{
    height:50px;

    border:1px solid var(--border);

    border-radius:12px;

    padding:0 17px;

    background:white;

    font-family:inherit;

    color:#526059;

    cursor:pointer;
}


/* =========================================================
   CATEGORIES
   ========================================================= */

.categories{
    display:flex;

    gap:10px;

    overflow:auto;

    margin-bottom:28px;
}

.category{
    white-space:nowrap;

    padding:9px 15px;

    border:1px solid var(--border);

    border-radius:30px;

    background:white;

    font-size:13px;

    font-weight:600;

    cursor:pointer;
}

.category.active,
.category:hover{
    background:#dcfce7;

    border-color:#bbf7d0;

    color:#15803d;
}


/* =========================================================
   PRODUCTS
   ========================================================= */

.products{
    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:20px;
}

.product{
    background:white;

    border:1px solid var(--border);

    border-radius:18px;

    overflow:hidden;

    transition:.2s;
}

.product:hover{
    transform:translateY(-4px);

    box-shadow:
        0 15px 35px rgba(0,0,0,.08);
}

.product-img{
    height:185px;

    background:#f1f6f3;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:65px;

    position:relative;

    overflow:hidden;
}

.product-img > img{
    width:100%;
    height:100%;

    object-fit:cover;
}

.wishlist{
    position:absolute;

    right:13px;

    top:13px;

    width:34px;

    height:34px;

    background:white;

    border-radius:50%;

    border:none;

    cursor:pointer;

    font-size:17px;

    display:flex;

    align-items:center;

    justify-content:center;
}

.product-info{
    padding:17px;
}

.tag{
    display:inline-block;

    padding:5px 8px;

    border-radius:6px;

    background:#ecfdf5;

    color:#15803d;

    font-size:10px;

    font-weight:800;

    text-transform:uppercase;
}

.product h3{
    margin-top:10px;

    font-size:15px;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}

.product-location{
    color:#78847e;

    font-size:12px;

    margin-top:7px;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}

.product-condition{
    color:#78847e;

    font-size:12px;

    margin-top:5px;
}

.product-bottom{
    margin-top:16px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;
}

.product-price{
    font-size:18px;

    font-weight:800;
}

.buy{
    background:#122019;

    color:white;

    border:none;

    padding:8px 12px;

    border-radius:8px;

    font-weight:700;

    cursor:pointer;

    text-decoration:none;

    font-size:13px;

    white-space:nowrap;
}

.buy:hover{
    background:#1d3026;
}


/* =========================================================
   NO PRODUCTS
   ========================================================= */

.no-products{
    grid-column:1/-1;

    background:white;

    border:1px solid var(--border);

    border-radius:18px;

    padding:55px 25px;

    text-align:center;
}

.no-products-icon{
    font-size:55px;

    margin-bottom:15px;
}

.no-products h3{
    font-size:20px;
}

.no-products p{
    color:#718078;

    margin:8px 0 20px;
}



/* =========================================================
   FOOTER
   ========================================================= */



/* =========================================================
   RESPONSIVE
   ========================================================= */

@media(max-width:1000px){

    .hero{
        grid-template-columns:1fr;
    }

    .products{
        grid-template-columns:
            repeat(2,1fr);
    }

    .stats{
        grid-template-columns:
            repeat(2,1fr);
    }
}


@media(max-width:650px){

    .profile-menu{
        right:-5px;

        width:270px;
    }

    .hero{
        padding:50px 5%;
    }

    .hero h1{
        font-size:38px;
    }

    .stats{
        grid-template-columns:
            1fr 1fr;
    }

    .products{
        grid-template-columns:1fr;
    }
.search-box{
        flex-direction:column;
    }
.impact-stats{
        flex-wrap:wrap;

        gap:25px;
    }

    footer{
        flex-direction:column;

        gap:20px;
    }

    .footer-links{
        flex-wrap:wrap;
    }
}


</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
     ===================================================== -->
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
                    <path d="M14 20.5A11 11 0 0 1 32.8 13l2.5 2.5M35.3 10.5v5.8h-5.8M34 27.5A11 11 0 0 1 15.2 35l-2.5-2.5M12.7 37.5v-5.8h5.8"
                          fill="none" stroke="url(#aavartanLogo)" stroke-width="4"
                          stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 28.5h10L24 18z" fill="#10221a"/>
                </svg>
            </div>
            <span>Aavartan</span>
        </a>

        <div class="main-nav" id="mainNav">
            <a href="dashboard.php#market" class="nav-link">
                <span class="nav-icon">🛒</span>Marketplace
            </a>
            <a href="mylisting.php" class="nav-link active">
                <span class="nav-icon">📦</span>My Listings
            </a>
            <a href="myorder.php" class="nav-link">
                <span class="nav-icon">🧾</span>My Orders
            </a>
            <a href="orderstatus.php" class="nav-link">
                <span class="nav-icon">🔔</span>Order Status
            </a>
        </div>

        <div class="nav-right">
            <button class="mobile-menu-btn" id="mobileMenuBtn"
                    type="button" aria-label="Open navigation"
                    aria-expanded="false">☰</button>

            <div class="profile-wrap">
                <button class="profile" id="profileButton"
                        title="<?php echo $displayName; ?>"
                        aria-label="Open profile menu"
                        aria-expanded="false"
                        type="button">
                    <?php echo $displayInitials; ?>
                </button>

                <div class="profile-menu" id="profileMenu">

                    <div class="profile-head">
                        <div class="profile-large">
                            <?php echo $displayInitials; ?>
                        </div>
                        <div>
                            <strong><?php echo $displayName; ?></strong>
                            <small><?php echo $displayEmail; ?></small>
                        </div>
                    </div>

                    <div class="profile-details">
                        <div>
                            <b>College</b>
                            <span><?php echo $displayCollege; ?></span>
                        </div>
                        <div>
                            <b>Course</b>
                            <span><?php echo $displayCourse; ?></span>
                        </div>
                        <div>
                            <b>Year</b>
                            <span><?php echo $displayYear; ?></span>
                        </div>
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

<!-- =====================================================
     HERO
     ===================================================== -->

<section class="hero">

    <div>

        <div
            style="
                font-size:14px;
                color:#16a34a;
                font-weight:700;
                margin-bottom:10px;
            "
        >
            Welcome back,
            <?php echo $displayName; ?>
            👋
        </div>


        <h1>
            Welcome back,<br>
            <span><?php echo $displayName; ?></span>
        </h1>

        <p>
            Manage your listings, check your orders and discover
            project materials shared by students.
        </p>


        <div class="hero-buttons">
            <a href="#market" class="btn btn-green btn-large">🛒 Browse Materials</a>
        </div>
    </div>

    <!-- =================================================
         HERO DATABASE PRODUCTS
         ================================================= -->

    <div class="hero-card">

        <div class="hero-card-top">

            <div class="hero-card-title">
                🔥 Latest Materials
            </div>

            <div class="live">
                LIVE
            </div>

        </div>


        <?php if (!empty($heroListings)): ?>


            <?php foreach ($heroListings as $item): ?>

                <a
                    href="view-product.php?id=<?php echo (int)$item["id"]; ?>"
                    class="hero-product-link"
                >

                    <div class="material-mini">


                        <div class="material-image">

                            <?php if (!empty($item["image_path"])): ?>

                                <img
                                    src="<?php echo e($item["image_path"]); ?>"
                                    alt="<?php echo e($item["material_name"]); ?>"
                                    onerror="
                                        this.style.display='none';
                                        this.parentElement.innerHTML='
                                        <?php echo getListingIcon($item["category"]); ?>';
                                    "
                                >

                            <?php else: ?>

                                <?php echo getListingIcon($item["category"]); ?>

                            <?php endif; ?>

                        </div>


                        <div style="min-width:0;">

                            <h4>
                                <?php echo e($item["material_name"]); ?>
                            </h4>


                            <p>

                                <?php echo e($item["item_condition"]); ?>

                                ·

                                <?php echo (int)$item["quantity"]; ?>
                                available

                            </p>


                            <div class="price">

                                <?php echo e(
                                    getListingPrice($item)
                                ); ?>

                            </div>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>


        <?php else: ?>

            <div
                style="
                    padding:25px 10px;
                    text-align:center;
                    color:#718078;
                    font-size:13px;
                "
            >

                📦

                <div style="margin-top:8px;">
                    No materials listed yet.
                </div>

            </div>

        <?php endif; ?>

    </div>

</section>



<!-- =====================================================
     DATABASE STATS
     ===================================================== -->

<section class="stats">

    <div class="stat">

        <h2>
            <?php echo number_format($totalListings); ?>
        </h2>

        <p>
            Active Listings
        </p>

    </div>


    <div class="stat">

        <h2>
            <?php echo number_format($totalQuantity); ?>+
        </h2>

        <p>
            Materials Available
        </p>

    </div>


    <div class="stat">

        <h2>
            <?php echo number_format($totalListings); ?>
        </h2>

        <p>
            Student Listings
        </p>

    </div>


    <div class="stat">

        <h2>
            ♻️
        </h2>

        <p>
            Materials Reused
        </p>

    </div>

</section>



<!-- =====================================================
     MARKETPLACE
     ===================================================== -->

<section
    class="market"
    id="market"
>


    <div class="section-head">

        <div>

            <h2>
                Explore Materials
            </h2>

            <p>
                Find affordable project components from students.
            </p>

        </div>


        <a
            href="selling.php"
            class="view"
            style="text-decoration:none;"
        >
            + List yours →
        </a>

    </div>



    <!-- SEARCH -->

    <div class="search-box">

        <input
            class="search"
            id="search"
            type="text"
            placeholder="🔎 Search Arduino, sensors, motors, wires..."
            onkeyup="filterProducts()"
        >


        <select
            class="filter"
            id="collegeFilter"
            onchange="filterProducts()"
        >

            <option value="">
                All Colleges
            </option>

            <option value="DY Patil Akurdi">
                DY Patil Akurdi
            </option>

            <option value="PICT">
                PICT
            </option>

            <option value="PCCOE">
                PCCOE
            </option>

            <option value="VIT Pune">
                VIT Pune
            </option>

        </select>


        <select
            class="filter"
            id="priceFilter"
            onchange="filterProducts()"
        >

            <option value="">
                All Prices
            </option>

            <option value="under100">
                Under ₹100
            </option>

            <option value="100to500">
                ₹100 - ₹500
            </option>

            <option value="500plus">
                ₹500+
            </option>

        </select>

    </div>



    <!-- CATEGORIES -->

    <div class="categories">

        <div
            class="category active"
            data-category=""
        >
            All
        </div>

        <div
            class="category"
            data-category="Arduino"
        >
            Arduino
        </div>

        <div
            class="category"
            data-category="Sensors"
        >
            Sensors
        </div>

        <div
            class="category"
            data-category="Electronics"
        >
            Electronics
        </div>

        <div
            class="category"
            data-category="Mechanical"
        >
            Mechanical
        </div>

        <div
            class="category"
            data-category="Robotics"
        >
            Robotics
        </div>

        <div
            class="category"
            data-category="IoT"
        >
            IoT
        </div>

        <div
            class="category"
            data-category="Cables"
        >
            Cables & Wires
        </div>

    </div>



    <!-- =================================================
         DATABASE PRODUCTS
         ================================================= -->

    <div
        class="products"
        id="products"
    >


        <?php if (!empty($listings)): ?>


            <?php foreach ($listings as $item): ?>

                <div
                    class="product"

                    data-category="<?php echo e($item["category"]); ?>"

                    data-college="<?php echo e($item["seller_college"]); ?>"

                    data-price="<?php echo (float)$item["price"]; ?>"
                >


                    <!-- PRODUCT IMAGE -->

                    <div class="product-img">

                        <?php if (!empty($item["image_path"])): ?>

                            <img
                                src="<?php echo e($item["image_path"]); ?>"
                                alt="<?php echo e($item["material_name"]); ?>"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                style="
                                    display:none;
                                    width:100%;
                                    height:100%;
                                    align-items:center;
                                    justify-content:center;
                                    font-size:65px;
                                "
                            >
                                <?php
                                echo getListingIcon(
                                    $item["category"]
                                );
                                ?>
                            </div>


                        <?php else: ?>

                            <?php
                            echo getListingIcon(
                                $item["category"]
                            );
                            ?>

                        <?php endif; ?>


                        <button
                            class="wishlist"
                            type="button"
                            onclick="
                                event.preventDefault();
                                event.stopPropagation();
                                toggleWishlist(this);
                            "
                        >
                            ♡
                        </button>

                    </div>



                    <!-- PRODUCT INFO -->

                    <div class="product-info">


                        <span class="tag">

                            <?php echo e(
                                $item["category"]
                            ); ?>

                        </span>



                        <h3>

                            <?php echo e(
                                $item["material_name"]
                            ); ?>

                        </h3>



                        <div class="product-location">

                            📍

                            <?php echo e(
                                $item["location"]
                            ); ?>

                        </div>



                        <div class="product-condition">

                            <?php echo e(
                                $item["item_condition"]
                            ); ?>

                            ·

                            <?php echo (int)$item["quantity"]; ?>

                            available

                        </div>



                        <div class="product-bottom">


                            <div class="product-price">

                                <?php echo e(
                                    getListingPrice($item)
                                ); ?>

                            </div>



                            <!-- IMPORTANT:
                                 REAL DATABASE LISTING ID -->

                            <a
                                class="buy"
                                href="view-product.php?id=<?php echo (int)$item["id"]; ?>"
                            >
                                View
                            </a>


                        </div>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <!-- NO LISTINGS -->

            <div class="no-products">

                <div class="no-products-icon">
                    📦
                </div>


                <h3>
                    No materials available yet
                </h3>


                <p>
                    Be the first student to list a project material.
                </p>


                <a
                    href="selling.php"
                    class="btn btn-green"
                >
                    + List Your Material
                </a>

            </div>


        <?php endif; ?>


    </div>

</section>




<script>
/* =========================================================
   MASTER NAVBAR JAVASCRIPT
   ========================================================= */
const mobileMenuBtn = document.getElementById("mobileMenuBtn");
const mainNav = document.getElementById("mainNav");

if (mobileMenuBtn && mainNav) {
    mobileMenuBtn.addEventListener("click", function (event) {
        event.stopPropagation();

        const isOpen = mainNav.classList.toggle("open");

        mobileMenuBtn.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );

        mobileMenuBtn.innerHTML = isOpen ? "✕" : "☰";
    });

    mainNav.querySelectorAll(".nav-link").forEach(function (link) {
        link.addEventListener("click", function () {
            mainNav.classList.remove("open");
            mobileMenuBtn.setAttribute("aria-expanded", "false");
            mobileMenuBtn.innerHTML = "☰";
        });
    });

    document.addEventListener("click", function (event) {
        if (
            !mainNav.contains(event.target) &&
            !mobileMenuBtn.contains(event.target)
        ) {
            mainNav.classList.remove("open");
            mobileMenuBtn.setAttribute("aria-expanded", "false");
            mobileMenuBtn.innerHTML = "☰";
        }
    });
}


/* =========================================================
   PROFILE DROPDOWN
   ========================================================= */
const profileButton = document.getElementById("profileButton");
const profileMenu = document.getElementById("profileMenu");

if (profileButton && profileMenu) {

    profileButton.addEventListener("click", function (event) {
        event.stopPropagation();

        const isOpen = profileMenu.classList.toggle("show");

        profileButton.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );
    });

    profileMenu.addEventListener("click", function (event) {
        event.stopPropagation();
    });

    document.addEventListener("click", function () {
        profileMenu.classList.remove("show");
        profileButton.setAttribute("aria-expanded", "false");
    });
}


/* =========================================================
   STATUS FILTER
   ========================================================= */
const statusFilter = document.getElementById("statusFilter");
const cards = document.querySelectorAll(".card");
const resultCount = document.getElementById("resultCount");

if (statusFilter && resultCount) {

    statusFilter.addEventListener("change", function () {

        const selected = this.value;
        let visible = 0;

        cards.forEach(function (card) {

            const status = card.dataset.status;

            if (selected === "all" || status === selected) {
                card.style.display = "";
                visible++;
            } else {
                card.style.display = "none";
            }

        });

        resultCount.textContent =
            visible + (visible === 1
                ? " listing shown"
                : " listings shown");
    });
}
</script>


</body>

</html>