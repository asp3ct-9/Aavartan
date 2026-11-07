<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$host = "localhost";
$dbname = "aavartan_db";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

$userId = (int) $_SESSION['user_id'];
$listingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($listingId <= 0) {
    header("Location: dashboard.php");
    exit;
}

/* =========================
   FETCH PRODUCT
========================= */

$stmt = $conn->prepare("
    SELECT
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
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");

$stmt->bind_param("i", $listingId);
$stmt->execute();

$result = $stmt->get_result();
$product = $result->fetch_assoc();

$stmt->close();

if (!$product) {
    $conn->close();
    die("Product not found or no longer available.");
}

/* =========================
   HELPER
========================= */

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================
   PRODUCT DATA
========================= */

$name = trim($product['material_name'] ?? '') ?: 'Material';

$type = trim($product['listing_type'] ?? '') ?: 'Sell';

$price = (float)($product['price'] ?? 0);
$original = (float)($product['original_price'] ?? 0);

$qty = (int)($product['quantity'] ?? 0);

$image = trim((string)($product['image_path'] ?? ''));

$seller = trim($product['seller_name'] ?? '') ?: 'Seller';

$sellerCollege = trim($product['seller_college'] ?? '') ?: 'College not specified';

$initial = strtoupper(substr($seller, 0, 1));

if (!$initial) {
    $initial = "S";
}

/* =========================
   PRICE
========================= */

if ($type === "Donate") {

    $priceText = "FREE";

} elseif ($type === "Exchange") {

    $priceText = "EXCHANGE";

} else {

    $priceText = "₹" . number_format($price, 0);
}

/* =========================
   BUTTON TEXT
========================= */

if ($type === "Exchange") {

    $buttonText = "🔄 Request Exchange";
    $headingText = "Request Exchange";

} elseif ($type === "Donate") {

    $buttonText = "🎁 Request Material";
    $headingText = "Request Material";

} else {

    $buttonText = "🛒 Buy Now";
    $headingText = "Place Order";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($name); ?> | Aavartan</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

*{margin:0;padding:0;box-sizing:border-box}

:root{
    --green:#16a34a;
    --green-dark:#0d6f38;
    --green-soft:#e8f8ef;
    --green-pale:#f3fbf6;
    --ink:#10221a;
    --muted:#718078;
    --line:#dfe9e4;
    --bg:#f5f8f6;
    --white:#fff;
    --gold:#a46b00;
    --gold-soft:#fff7df;
    --shadow:0 20px 55px rgba(16,34,26,.07);
}

html{scroll-behavior:smooth}

body{
    font-family:"DM Sans",sans-serif;
    background:
        radial-gradient(circle at 4% 0%,rgba(22,163,74,.08),transparent 24%),
        radial-gradient(circle at 96% 18%,rgba(52,211,153,.07),transparent 22%),
        var(--bg);
    color:var(--ink);
    min-height:100vh;
}

/* ================= NAVBAR ================= */

.nav{
    height:74px;
    padding:0 5.5%;
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(16px);
    -webkit-backdrop-filter:blur(16px);
    border-bottom:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:100;
}

.logo{
    display:flex;
    align-items:center;
    gap:10px;
    font-family:"Plus Jakarta Sans";
    font-size:21px;
    font-weight:800;
    letter-spacing:-.5px;
    text-decoration:none;
    color:var(--ink);
}

.logo-icon{
    width:40px;
    height:40px;
    border-radius:13px;
    background:linear-gradient(145deg,#d9f8e6,#c2f1d3);
    color:var(--green);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
}

.logo span{color:var(--green)}

.nav-links{
    display:flex;
    align-items:center;
    gap:8px;
}

.nav-links a{
    text-decoration:none;
    color:#526059;
    font-size:13px;
    font-weight:700;
    padding:10px 13px;
    border-radius:10px;
    transition:.2s;
}

.nav-links a:hover{
    color:var(--green);
    background:var(--green-soft);
}

.nav-right{
    display:flex;
    align-items:center;
    gap:12px;
}

.list-btn{
    text-decoration:none;
    color:#fff;
    background:linear-gradient(135deg,#16a34a,#0f8f43);
    padding:11px 16px;
    border-radius:11px;
    font-size:12px;
    font-weight:800;
    box-shadow:0 8px 20px rgba(22,163,74,.2);
    transition:.2s;
}

.list-btn:hover{transform:translateY(-1px)}

.profile-wrap{position:relative}

.profile{
    width:42px;
    height:42px;
    border:1px solid #c7ead5;
    border-radius:50%;
    background:#d9f8e6;
    color:#166534;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
    cursor:pointer;
    transition:.2s;
}

.profile:hover{
    transform:translateY(-1px);
    box-shadow:0 8px 20px rgba(22,163,74,.15);
}

.profile-menu{
    position:absolute;
    top:53px;
    right:0;
    width:290px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:15px;
    box-shadow:0 25px 60px rgba(0,0,0,.14);
    display:none;
    z-index:300;
}

.profile-menu.show{display:block}

.profile-menu-head{
    display:flex;
    align-items:center;
    gap:11px;
    padding-bottom:13px;
    border-bottom:1px solid var(--line);
}

.profile-large{
    width:44px;
    height:44px;
    border-radius:50%;
    background:var(--green-soft);
    color:#166534;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:800;
}

.profile-menu-head strong{display:block;font-size:13px}

.profile-menu-head span{
    display:block;
    color:#7a8780;
    font-size:11px;
    margin-top:3px;
    max-width:190px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.profile-details{
    padding:11px 0;
    display:grid;
    gap:8px;
}

.profile-details div{
    display:flex;
    justify-content:space-between;
    gap:15px;
    font-size:11px;
}

.profile-details b{color:#718078;font-weight:600}

.profile-details span{
    color:#25352c;
    text-align:right;
    max-width:170px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.menu-link,.logout-btn{
    display:block;
    text-align:center;
    text-decoration:none;
    padding:10px;
    border-radius:10px;
    font-size:12px;
    font-weight:800;
}

.menu-link{
    color:#166534;
    background:#f0fdf4;
    border:1px solid #dcfce7;
    margin-bottom:8px;
}

.logout-btn{
    background:#122019;
    color:white;
}

/* ================= PAGE ================= */

.container{
    width:min(1240px,90%);
    margin:0 auto;
    padding:30px 0 70px;
}

.breadcrumb{
    display:flex;
    align-items:center;
    gap:8px;
    color:#87938d;
    font-size:11px;
    font-weight:600;
    margin-bottom:18px;
}

.breadcrumb a{
    color:var(--green);
    text-decoration:none;
    font-weight:800;
}

.breadcrumb .current{
    max-width:280px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.product-grid{
    display:grid;
    grid-template-columns:minmax(0,1.05fr) minmax(390px,.95fr);
    gap:24px;
    align-items:start;
}

/* ================= IMAGE CARD ================= */

.gallery-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    padding:14px;
    box-shadow:var(--shadow);
}

.photo-wrap{
    position:relative;
    height:570px;
    border-radius:18px;
    overflow:hidden;
    background:
        radial-gradient(circle at 50% 40%,#fff 0,#f3f8f5 55%,#edf4f0 100%);
    display:flex;
    align-items:center;
    justify-content:center;
}

.photo{
    width:100%;
    height:100%;
    object-fit:contain;
    display:block;
    padding:18px;
}

.noimg{
    width:100%;
    height:100%;
    display:grid;
    place-items:center;
    font-size:74px;
    color:#9aa79f;
}

.image-badge{
    position:absolute;
    top:15px;
    left:15px;
    padding:7px 10px;
    border-radius:30px;
    background:rgba(255,255,255,.9);
    backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.8);
    color:#166534;
    font-size:9px;
    font-weight:800;
    letter-spacing:.7px;
    text-transform:uppercase;
}

/* ================= DETAILS CARD ================= */

.details-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:24px;
    padding:27px;
    box-shadow:var(--shadow);
}

.badges{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-bottom:15px;
}

.badge{
    padding:7px 10px;
    border-radius:8px;
    background:var(--green-soft);
    color:#15803d;
    font-size:9px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.4px;
}

.badge.type{
    background:var(--gold-soft);
    color:var(--gold);
}

h1{
    font-family:"Plus Jakarta Sans";
    font-size:clamp(28px,3.4vw,43px);
    line-height:1.08;
    letter-spacing:-1.5px;
    margin-bottom:9px;
}

.subtitle{
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
}

.price-row{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap:15px;
    margin-top:17px;
    padding-bottom:18px;
    border-bottom:1px solid var(--line);
}

.price{
    color:var(--green-dark);
    font-size:31px;
    font-weight:800;
    letter-spacing:-.7px;
}

.old{
    color:#98a39d;
    font-size:11px;
    text-decoration:line-through;
    margin-top:4px;
}

.availability{
    padding:7px 10px;
    border-radius:9px;
    background:#f0fdf4;
    border:1px solid #dcfce7;
    color:#15803d;
    font-size:10px;
    font-weight:800;
    white-space:nowrap;
}

.desc{
    color:#58675f;
    font-size:12px;
    line-height:1.75;
    white-space:pre-line;
    margin:18px 0 20px;
}

/* ================= INFO ================= */

.info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:9px;
    margin-bottom:22px;
}

.info-item{
    padding:13px;
    border:1px solid #e8efeb;
    background:#f8fbf9;
    border-radius:12px;
}

.info-item small{
    display:block;
    color:#89958f;
    font-size:9px;
    font-weight:700;
    margin-bottom:5px;
    text-transform:uppercase;
    letter-spacing:.5px;
}

.info-item strong{
    display:block;
    font-size:12px;
    line-height:1.4;
}

/* ================= ORDER ================= */

.order{
    border-top:1px solid var(--line);
    padding-top:20px;
}

.order-heading{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:14px;
}

.order h2{
    font-family:"Plus Jakarta Sans";
    font-size:18px;
    letter-spacing:-.4px;
}

.order-step{
    font-size:9px;
    font-weight:800;
    color:var(--green);
    background:var(--green-soft);
    padding:6px 8px;
    border-radius:8px;
}

.row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}

label{
    display:block;
    color:#526159;
    font-size:10px;
    font-weight:800;
    margin-bottom:6px;
}

input,select,textarea{
    width:100%;
    padding:12px 13px;
    border:1px solid #dce5df;
    border-radius:10px;
    font:500 12px "DM Sans";
    color:var(--ink);
    background:#fbfdfc;
    outline:none;
    transition:.2s;
}

input:focus,select:focus,textarea:focus{
    border-color:#4ade80;
    background:#fff;
    box-shadow:0 0 0 4px rgba(34,197,94,.08);
}

textarea{
    margin-top:10px;
    resize:vertical;
    min-height:80px;
}

.buy-btn{
    width:100%;
    margin-top:12px;
    border:0;
    border-radius:12px;
    padding:14px;
    background:linear-gradient(135deg,#16a34a,#0f8f43);
    color:#fff;
    font-weight:800;
    font-size:13px;
    cursor:pointer;
    box-shadow:0 10px 24px rgba(22,163,74,.2);
    transition:.2s;
}

.buy-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 14px 28px rgba(22,163,74,.26);
}

.buy-btn:disabled{
    background:#aab7b0;
    cursor:not-allowed;
    transform:none;
    box-shadow:none;
}

/* ================= SELLER ================= */

.seller{
    margin-top:19px;
    padding:14px;
    border:1px solid #e5ebe7;
    border-radius:14px;
    display:flex;
    align-items:center;
    gap:11px;
    background:#fbfdfc;
}

.avatar{
    width:43px;
    height:43px;
    flex:0 0 43px;
    border-radius:50%;
    background:linear-gradient(145deg,#16a34a,#0d6f38);
    color:#fff;
    display:grid;
    place-items:center;
    font-weight:800;
    font-size:14px;
}

.seller small{
    display:block;
    color:#89958f;
    font-size:9px;
    margin-bottom:2px;
}

.seller strong{
    display:block;
    font-size:12px;
}

.own-listing{
    border-top:1px solid var(--line);
    padding-top:20px;
}

.own-listing h2{
    font-family:"Plus Jakarta Sans";
    font-size:18px;
    margin-bottom:7px;
}

.own-listing p{
    color:var(--muted);
    font-size:12px;
    line-height:1.6;
    background:#f5faf7;
    border:1px solid #e1eee6;
    padding:13px;
    border-radius:11px;
}

/* ================= RESPONSIVE ================= */

@media(max-width:950px){
    .product-grid{
        grid-template-columns:1fr;
    }

    .photo-wrap{
        height:500px;
    }
}

@media(max-width:650px){
    .nav{
        padding:0 5%;
    }

    .nav-links{
        display:none;
    }

    .list-btn{
        padding:10px 12px;
    }

    .container{
        width:90%;
        padding-top:22px;
    }

    .product-grid{
        gap:15px;
    }

    .gallery-card,.details-card{
        border-radius:19px;
        padding:12px;
    }

    .details-card{
        padding:20px;
    }

    .photo-wrap{
        height:330px;
        border-radius:14px;
    }

    .info,.row{
        grid-template-columns:1fr;
    }

    h1{
        font-size:30px;
    }

    .price{
        font-size:27px;
    }

    .profile-menu{
        width:min(290px,calc(100vw - 30px));
        right:-5px;
    }
}
</style>
</head>

<body>

<!-- ================= NAVBAR ================= -->

<nav class="nav">

    <a href="dashboard.php" class="logo">
        <div class="logo-icon">♻</div>
        Aav<span>artan</span>
    </a>

    <div class="nav-links">
        <a href="dashboard.php">Marketplace</a>
        <a href="myorder.php">My Orders</a>
    </div>

    <div class="nav-right">

        <a href="selling.php" class="list-btn">
            + List Material
        </a>

        <div class="profile-wrap">

            <button
                class="profile"
                id="profileButton"
                type="button"
                aria-label="Open profile menu"
            >
                <?php echo e($_SESSION["full_name"] ? strtoupper(substr(trim($_SESSION["full_name"]),0,1)) : "U"); ?>
            </button>

            <div class="profile-menu" id="profileMenu">

                <div class="profile-menu-head">
                    <div class="profile-large">
                        <?php echo e($_SESSION["full_name"] ? strtoupper(substr(trim($_SESSION["full_name"]),0,1)) : "U"); ?>
                    </div>

                    <div>
                        <strong><?php echo e($_SESSION["full_name"] ?? "User"); ?></strong>
                        <span><?php echo e($_SESSION["email"] ?? ""); ?></span>
                    </div>
                </div>

                <div class="profile-details">
                    <div>
                        <b>College</b>
                        <span><?php echo e($_SESSION["college"] ?? ""); ?></span>
                    </div>

                    <div>
                        <b>Course</b>
                        <span><?php echo e($_SESSION["course"] ?? ""); ?></span>
                    </div>

                    <div>
                        <b>Year</b>
                        <span><?php echo e($_SESSION["year"] ?? ""); ?></span>
                    </div>
                </div>

                <a href="mylisting.php" class="menu-link">My Listings</a>
                <a href="myorder.php" class="menu-link">My Orders</a>
                <a href="logout.php" class="logout-btn">Sign out</a>

            </div>

        </div>

    </div>

</nav>


<main class="container">

    <div class="breadcrumb">
        <a href="dashboard.php">Marketplace</a>
        <span>›</span>
        <span class="current"><?php echo e($name); ?></span>
    </div>


    <div class="product-grid">

        <!-- ================= IMAGE ================= -->

        <section class="gallery-card">

            <div class="photo-wrap">

                <div class="image-badge">
                    Aavartan Marketplace
                </div>

                <?php if($image): ?>

                    <img
                        class="photo"
                        src="<?php echo e($image); ?>"
                        alt="<?php echo e($name); ?>"
                        onerror="
                            this.style.display='none';
                            document.getElementById('noimg').style.display='grid';
                        "
                    >

                    <div id="noimg" class="noimg" style="display:none;">
                        📦
                    </div>

                <?php else: ?>

                    <div class="noimg">📦</div>

                <?php endif; ?>

            </div>

        </section>


        <!-- ================= DETAILS ================= -->

        <section class="details-card">

            <div class="badges">

                <span class="badge">
                    <?php echo e($product['category']); ?>
                </span>

                <span class="badge">
                    <?php echo e($product['item_condition']); ?>
                </span>

                <span class="badge type">
                    <?php echo e($type); ?>
                </span>

            </div>


            <h1><?php echo e($name); ?></h1>

            <p class="subtitle">
                Quality project material listed by a student in the Aavartan marketplace.
            </p>


            <div class="price-row">

                <div>
                    <div class="price">
                        <?php echo e($priceText); ?>
                    </div>

                    <?php if($original > $price && $type === "Sell"): ?>
                        <div class="old">
                            Original price: ₹<?php echo number_format($original,0); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="availability">
                    <?php echo $qty > 0 ? "● Available" : "Out of stock"; ?>
                </div>

            </div>


            <div class="desc">
                <?php
                    echo e(
                        $product['description']
                        ?: 'No description provided.'
                    );
                ?>
            </div>


            <div class="info">

                <div class="info-item">
                    <small>Quantity Available</small>
                    <strong><?php echo $qty; ?></strong>
                </div>

                <div class="info-item">
                    <small>Condition</small>
                    <strong><?php echo e($product['item_condition']); ?></strong>
                </div>

                <div class="info-item">
                    <small>Location</small>
                    <strong><?php echo e($product['location']); ?></strong>
                </div>

                <div class="info-item">
                    <small>College</small>
                    <strong><?php echo e($sellerCollege); ?></strong>
                </div>

            </div>


            <?php if((int)$product['seller_id'] !== $userId): ?>

                <div class="order">

                    <div class="order-heading">
                        <h2><?php echo e($headingText); ?></h2>
                        <span class="order-step">SECURE REQUEST</span>
                    </div>


                    <?php if($qty > 0): ?>

                        <form action="place-order.php" method="POST">

                            <input
                                type="hidden"
                                name="listing_id"
                                value="<?php echo $listingId; ?>"
                            >

                            <div class="row">

                                <div>
                                    <label for="quantity">Quantity</label>

                                    <input
                                        id="quantity"
                                        name="quantity"
                                        type="number"
                                        min="1"
                                        max="<?php echo $qty; ?>"
                                        value="1"
                                        required
                                    >
                                </div>

                                <div>
                                    <label for="handover_method">Handover</label>

                                    <select
                                        id="handover_method"
                                        name="handover_method"
                                        required
                                    >
                                        <option value="Meetup">Meetup</option>
                                        <option value="Pickup">Pickup</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                            </div>

                            <textarea
                                name="note"
                                rows="3"
                                placeholder="Message to seller..."
                            ></textarea>

                            <button type="submit" class="buy-btn">
                                <?php echo e($buttonText); ?>
                            </button>

                        </form>

                    <?php else: ?>

                        <button class="buy-btn" type="button" disabled>
                            Currently Unavailable
                        </button>

                    <?php endif; ?>

                </div>


            <?php else: ?>

                <div class="own-listing">

                    <h2>Your Listing</h2>

                    <p>
                        This is your own listing. You cannot place an order
                        for your own material.
                    </p>

                </div>

            <?php endif; ?>


            <div class="seller">

                <div class="avatar">
                    <?php echo e($initial); ?>
                </div>

                <div>
                    <small>Listed by</small>
                    <strong><?php echo e($seller); ?></strong>
                    <small><?php echo e($sellerCollege); ?></small>
                </div>

            </div>

        </section>

    </div>

</main>


<script>
const profileButton = document.getElementById("profileButton");
const profileMenu = document.getElementById("profileMenu");

if(profileButton && profileMenu){

    profileButton.addEventListener("click", function(e){
        e.stopPropagation();
        profileMenu.classList.toggle("show");
    });

    profileMenu.addEventListener("click", function(e){
        e.stopPropagation();
    });

    document.addEventListener("click", function(){
        profileMenu.classList.remove("show");
    });

}
</script>

</body>
</html>

<?php
$conn->close();
?>
