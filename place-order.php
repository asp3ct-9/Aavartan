<?php
session_start();

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
   RECEIVE DATA
========================================= */

$listingId = isset($_POST['listing_id'])
    ? (int)$_POST['listing_id']
    : 0;

$quantity = isset($_POST['quantity'])
    ? (int)$_POST['quantity']
    : 1;

$handoverMethod = trim(
    $_POST['handover_method'] ?? 'Meetup'
);

$note = trim(
    $_POST['note'] ?? ''
);


/* =========================================
   VALIDATE LISTING
========================================= */

if ($listingId <= 0) {

    header("Location: dashboard.php");
    exit;

}


/* =========================================
   VALIDATE QUANTITY
========================================= */

if ($quantity < 1) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Invalid Quantity</h2>

            <p>
                Please select a valid quantity.
            </p>

            <a href='dashboard.php'>
                Back to Marketplace
            </a>

        </div>
    ");

}


/* =========================================
   FETCH PRODUCT
========================================= */

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
        image_path,
        status
    FROM listings
    WHERE id = ?
      AND status = 'active'
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $listingId
);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$stmt->close();


/* =========================================
   PRODUCT NOT FOUND
========================================= */

if (!$product) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Product Not Available</h2>

            <p>
                This product is no longer available.
            </p>

            <a href='dashboard.php'>
                Back to Marketplace
            </a>

        </div>
    ");

}


/* =========================================
   PREVENT OWN PRODUCT
========================================= */

$currentUserId = (int)$_SESSION['user_id'];

if ((int)$product['seller_id'] === $currentUserId) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Own Listing</h2>

            <p>
                You cannot place an order
                for your own listing.
            </p>

            <a href='dashboard.php'>
                Back to Marketplace
            </a>

        </div>
    ");

}


/* =========================================
   CHECK QUANTITY
========================================= */

$availableQuantity = (int)$product['quantity'];

if ($quantity > $availableQuantity) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Insufficient Quantity</h2>

            <p>
                Only <strong>
                " . $availableQuantity . "
                </strong> item(s) are available.
            </p>

            <a href='view-product.php?id="
            . $listingId .
            "'>
                Back to Product
            </a>

        </div>
    ");

}


/* =========================================
   PRODUCT DATA
========================================= */

$name = $product['material_name']
    ?: 'Material';

$type = $product['listing_type']
    ?: 'Sell';

$price = (float)$product['price'];

$totalPrice = $price * $quantity;

$image = trim(
    (string)($product['image_path'] ?? '')
);


/* =========================================
   PRICE DISPLAY
========================================= */

if ($type === "Donate") {

    $priceText = "FREE";
    $totalText = "FREE";

} elseif ($type === "Exchange") {

    $priceText = "EXCHANGE";
    $totalText = "EXCHANGE";

} else {

    $priceText =
        "₹" . number_format($price, 2);

    $totalText =
        "₹" . number_format($totalPrice, 2);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Place Order | Aavartan</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
:root{--g:#16a34a;--gd:#0d6f38;--soft:#e8f8ef;--ink:#10221a;--muted:#718078;--line:#dfe9e4;--bg:#f5f8f6}
body{font-family:"DM Sans",sans-serif;color:var(--ink);background:radial-gradient(circle at 5% 0%,rgba(22,163,74,.08),transparent 25%),var(--bg);min-height:100vh}
.nav{height:74px;padding:0 5.5%;background:rgba(255,255,255,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--ink);font:800 21px "Plus Jakarta Sans"}
.logo-icon{width:40px;height:40px;border-radius:13px;background:linear-gradient(145deg,#d9f8e6,#c2f1d3);color:var(--g);display:grid;place-items:center;font-size:20px}
.logo span{color:var(--g)}
.nav-links{display:flex;gap:8px}.nav-links a{color:#526059;text-decoration:none;font-size:13px;font-weight:700;padding:10px 13px;border-radius:10px}.nav-links a:hover{color:var(--g);background:var(--soft)}
.nav-right{display:flex;align-items:center;gap:12px}.list-btn{color:#fff;text-decoration:none;background:linear-gradient(135deg,#16a34a,#0f8f43);padding:11px 16px;border-radius:11px;font-size:12px;font-weight:800;box-shadow:0 8px 20px rgba(22,163,74,.2)}
.profile-wrap{position:relative}.profile{width:42px;height:42px;border:1px solid #c7ead5;border-radius:50%;background:#d9f8e6;color:#166534;font-weight:800;cursor:pointer}
.profile-menu{position:absolute;top:53px;right:0;width:290px;background:#fff;border:1px solid var(--line);border-radius:18px;padding:15px;box-shadow:0 25px 60px rgba(0,0,0,.14);display:none;z-index:300}.profile-menu.show{display:block}
.profile-menu-head{display:flex;gap:11px;align-items:center;padding-bottom:13px;border-bottom:1px solid var(--line)}.profile-large{width:44px;height:44px;border-radius:50%;background:var(--soft);color:#166534;display:grid;place-items:center;font-weight:800}.profile-menu-head strong{display:block;font-size:13px}.profile-menu-head span{display:block;color:#7a8780;font-size:11px;margin-top:3px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.profile-details{padding:11px 0;display:grid;gap:8px}.profile-details div{display:flex;justify-content:space-between;gap:12px;font-size:11px}.profile-details b{color:#718078}.profile-details span{max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.menu-link,.logout-btn{display:block;text-align:center;text-decoration:none;padding:10px;border-radius:10px;font-size:12px;font-weight:800}.menu-link{color:#166534;background:#f0fdf4;border:1px solid #dcfce7;margin-bottom:8px}.logout-btn{background:#122019;color:#fff}

.container{width:min(1080px,92%);margin:0 auto;padding:38px 0 70px}
.eyebrow{display:flex;align-items:center;gap:8px;color:var(--g);font-size:10px;font-weight:800;letter-spacing:1.5px;margin-bottom:8px}.eyebrow i{width:7px;height:7px;border-radius:50%;background:var(--g)}
h1{font:800 clamp(30px,4vw,42px) "Plus Jakarta Sans";letter-spacing:-1.4px}.subtitle{color:var(--muted);font-size:13px;margin-top:7px}
.checkout{margin-top:27px;display:grid;grid-template-columns:1.1fr .9fr;gap:20px;align-items:start}
.card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 16px 45px rgba(16,34,26,.06)}
.product-card{padding:16px;display:grid;grid-template-columns:170px 1fr;gap:18px}
.product-image,.no-image{width:170px;height:170px;border-radius:16px;object-fit:contain;background:#f0f6f2}.no-image{display:grid;place-items:center;font-size:55px}
.product-info{padding:6px 4px}.badges{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:11px}.badge{padding:6px 9px;border-radius:8px;background:var(--soft);color:#15803d;font-size:9px;font-weight:800;text-transform:uppercase}.badge.type{background:#fff6dc;color:#916800}
.product-info h2{font:800 23px "Plus Jakarta Sans";letter-spacing:-.5px}.seller{font-size:11px;color:var(--muted);margin-top:8px}.seller strong{color:var(--ink)}.price{font-size:25px;font-weight:800;color:var(--gd);margin-top:15px}
.summary-card{padding:22px}.section-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:17px}.section-title h2{font:800 17px "Plus Jakarta Sans"}.secure{font-size:9px;color:#15803d;background:var(--soft);padding:6px 8px;border-radius:8px;font-weight:800}
.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.summary-box{background:#f8fbf9;border:1px solid #e7efea;border-radius:12px;padding:13px}.summary-box small{display:block;color:#89958f;font-size:9px;text-transform:uppercase;font-weight:700;margin-bottom:5px}.summary-box strong{font-size:13px}
.note{margin-top:10px;padding:14px;background:#f8fbf9;border-radius:12px;border:1px solid #e7efea}.note strong{display:block;font-size:11px;margin-bottom:5px}.note p{font-size:11px;color:#58675f;line-height:1.6}
.actions{display:flex;gap:10px;margin-top:18px}.btn{flex:1;padding:13px;border:0;border-radius:11px;text-align:center;text-decoration:none;font:800 12px "DM Sans";cursor:pointer}.cancel{background:#edf1ee;color:#34443b}.confirm{background:linear-gradient(135deg,#16a34a,#0f8f43);color:#fff;box-shadow:0 10px 24px rgba(22,163,74,.18)}.confirm:hover{transform:translateY(-1px)}
.payment-note{margin-top:12px;text-align:center;color:#89958f;font-size:9px}
@media(max-width:800px){.nav-links{display:none}.checkout{grid-template-columns:1fr}.product-card{grid-template-columns:140px 1fr}.product-image,.no-image{width:140px;height:140px}}
@media(max-width:560px){.nav{padding:0 5%}.list-btn{padding:10px 12px}.container{padding-top:25px}.product-card{grid-template-columns:1fr}.product-image,.no-image{width:100%;height:230px}.summary-grid{grid-template-columns:1fr}.actions{flex-direction:column}.profile-menu{width:min(290px,calc(100vw - 30px))}}
</style>
</head>
<body>
<nav class="nav">
<a href="dashboard.php" class="logo"><div class="logo-icon">♻</div>Aav<span>artan</span></a>
<div class="nav-links"><a href="dashboard.php">Marketplace</a><a href="myorder.php">My Orders</a></div>
<div class="nav-right">
<a href="selling.php" class="list-btn">+ List Material</a>
<div class="profile-wrap">
<button class="profile" id="profileButton" type="button"><?php echo e($_SESSION["full_name"] ? strtoupper(substr(trim($_SESSION["full_name"]),0,1)) : "U"); ?></button>
<div class="profile-menu" id="profileMenu">
<div class="profile-menu-head"><div class="profile-large"><?php echo e($_SESSION["full_name"] ? strtoupper(substr(trim($_SESSION["full_name"]),0,1)) : "U"); ?></div><div><strong><?php echo e($_SESSION["full_name"] ?? "User"); ?></strong><span><?php echo e($_SESSION["email"] ?? ""); ?></span></div></div>
<div class="profile-details"><div><b>College</b><span><?php echo e($_SESSION["college"] ?? ""); ?></span></div><div><b>Course</b><span><?php echo e($_SESSION["course"] ?? ""); ?></span></div><div><b>Year</b><span><?php echo e($_SESSION["year"] ?? ""); ?></span></div></div>
<a href="mylisting.php" class="menu-link">My Listings</a><a href="myorder.php" class="menu-link">My Orders</a><a href="logout.php" class="logout-btn">Sign out</a>
</div></div>
</div>
</nav>

<main class="container">
<div class="eyebrow"><i></i> CHECKOUT</div>
<h1>Review your order</h1>
<p class="subtitle">Everything looks good? Confirm below and continue securely to payment.</p>

<div class="checkout">
<section class="card product-card">
<?php if($image): ?>
<img class="product-image" src="<?php echo e($image); ?>" alt="<?php echo e($name); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
<div class="no-image" style="display:none">📦</div>
<?php else: ?><div class="no-image">📦</div><?php endif; ?>
<div class="product-info">
<div class="badges"><span class="badge"><?php echo e($product['category']); ?></span><span class="badge"><?php echo e($product['item_condition']); ?></span><span class="badge type"><?php echo e($type); ?></span></div>
<h2><?php echo e($name); ?></h2>
<div class="seller">Listed by <strong><?php echo e($product['seller_name']); ?></strong><br><?php echo e($product['seller_college']); ?></div>
<div class="price"><?php echo e($totalText); ?></div>
</div>
</section>

<section class="card summary-card">
<div class="section-title"><h2>Order summary</h2><span class="secure">SECURE CHECKOUT</span></div>
<div class="summary-grid">
<div class="summary-box"><small>Quantity</small><strong><?php echo $quantity; ?></strong></div>
<div class="summary-box"><small>Available</small><strong><?php echo $availableQuantity; ?></strong></div>
<div class="summary-box"><small>Handover</small><strong><?php echo e($handoverMethod); ?></strong></div>
<div class="summary-box"><small>Total</small><strong><?php echo e($totalText); ?></strong></div>
</div>
<?php if($note !== ''): ?><div class="note"><strong>Message to Seller</strong><p><?php echo nl2br(e($note)); ?></p></div><?php endif; ?>

<div class="actions">
<a class="btn cancel" href="view-product.php?id=<?php echo $listingId; ?>">← Go Back</a>
<form action="payment.php" method="POST" style="flex:1">
<input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
<input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
<input type="hidden" name="handover_method" value="<?php echo e($handoverMethod); ?>">
<input type="hidden" name="note" value="<?php echo e($note); ?>">
<input type="hidden" name="total_price" value="<?php echo $totalPrice; ?>">
<button type="submit" class="btn confirm"><?php if($type==="Exchange") echo "🔄 Continue"; elseif($type==="Donate") echo "🎁 Continue"; else echo "💳 Continue to Payment"; ?></button>
</form>
</div>
<div class="payment-note">Your order details will be passed securely to the next step.</div>
</section>
</div>
</main>

<script>
const profileButton=document.getElementById("profileButton"),profileMenu=document.getElementById("profileMenu");
if(profileButton&&profileMenu){profileButton.addEventListener("click",e=>{e.stopPropagation();profileMenu.classList.toggle("show")});profileMenu.addEventListener("click",e=>e.stopPropagation());document.addEventListener("click",()=>profileMenu.classList.remove("show"))}
</script>
</body>
</html>
<?php $conn->close(); ?>
