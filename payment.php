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
   GET POST DATA
========================================= */

$listingId = isset($_POST['listing_id'])
    ? (int)$_POST['listing_id']
    : 0;

$quantity = isset($_POST['quantity'])
    ? (int)$_POST['quantity']
    : 0;

$handoverMethod = trim(
    $_POST['handover_method'] ?? 'Meetup'
);

$note = trim(
    $_POST['note'] ?? ''
);


/* =========================================
   VALIDATE
========================================= */

if ($listingId <= 0 || $quantity <= 0) {

    header("Location: dashboard.php");
    exit;
}


/* =========================================
   FETCH LISTING
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
            text-align:center;
            padding:50px;
        '>

            <h2>Product No Longer Available</h2>

            <p>
                This listing is no longer available.
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

$userId = (int)$_SESSION['user_id'];

if ((int)$product['seller_id'] === $userId) {

    die("
        <div style='
            font-family:Arial;
            text-align:center;
            padding:50px;
        '>

            <h2>Invalid Order</h2>

            <p>
                You cannot purchase your own listing.
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
            text-align:center;
            padding:50px;
        '>

            <h2>Quantity Not Available</h2>

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

$name = trim(
    $product['material_name'] ?? ''
);

if ($name === '') {
    $name = 'Material';
}

$type = trim(
    $product['listing_type'] ?? ''
);

if ($type === '') {
    $type = 'Sell';
}

$price = (float)$product['price'];

$totalPrice = $price * $quantity;

$image = trim(
    (string)($product['image_path'] ?? '')
);


/* =========================================
   DISPLAY PRICE
========================================= */

if ($type === 'Donate') {

    $priceDisplay = 'FREE';

    $totalDisplay = 'FREE';

} elseif ($type === 'Exchange') {

    $priceDisplay = 'EXCHANGE';

    $totalDisplay = 'EXCHANGE';

} else {

    $priceDisplay =
        '₹' . number_format($price, 2);

    $totalDisplay =
        '₹' . number_format($totalPrice, 2);
}


/* =========================================
   USER INFORMATION
========================================= */

$userName = $_SESSION['full_name'] ?? 'User';

$userEmail = $_SESSION['email'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment | Aavartan</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
:root{--green:#16a34a;--green2:#0f8f43;--soft:#e8f8ef;--ink:#10221a;--muted:#718078;--line:#dfe9e4;--bg:#f5f9f6;--gold:#9a6900}
body{font-family:"DM Sans",sans-serif;background:radial-gradient(circle at 5% 0%,rgba(22,163,74,.08),transparent 25%),var(--bg);color:var(--ink);min-height:100vh}
.nav{height:74px;padding:0 5.5%;background:rgba(255,255,255,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.logo{display:flex;align-items:center;gap:10px;font-family:"Plus Jakarta Sans";font-size:21px;font-weight:800;letter-spacing:-.5px;text-decoration:none;color:var(--ink)}
.logo-icon{width:40px;height:40px;border-radius:13px;background:linear-gradient(145deg,#d9f8e6,#c2f1d3);color:var(--green);display:grid;place-items:center;font-size:20px}.logo span{color:var(--green)}
.nav-links{display:flex;gap:8px}.nav-links a{color:#526059;text-decoration:none;font-size:13px;font-weight:700;padding:10px 13px;border-radius:10px}.nav-links a:hover{background:var(--soft);color:var(--green)}
.nav-right{display:flex;align-items:center;gap:12px}.list-btn{color:#fff;text-decoration:none;background:linear-gradient(135deg,var(--green),var(--green2));padding:11px 16px;border-radius:11px;font-size:12px;font-weight:800;box-shadow:0 8px 20px rgba(22,163,74,.2)}
.profile-wrap{position:relative}.profile{width:42px;height:42px;border:1px solid #c7ead5;border-radius:50%;background:#d9f8e6;color:#166534;font-weight:800;cursor:pointer}
.profile-menu{position:absolute;right:0;top:53px;width:285px;background:#fff;border:1px solid var(--line);border-radius:17px;padding:15px;box-shadow:0 25px 60px rgba(0,0,0,.14);display:none;z-index:200}.profile-menu.show{display:block}
.profile-head{display:flex;gap:10px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--line)}.profile-large{width:43px;height:43px;border-radius:50%;background:var(--soft);color:#166534;display:grid;place-items:center;font-weight:800}.profile-head strong{display:block;font-size:13px}.profile-head span{display:block;color:#7a8780;font-size:10px;margin-top:3px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.profile-details{padding:11px 0;display:grid;gap:7px}.profile-details div{display:flex;justify-content:space-between;font-size:11px}.profile-details b{color:#718078}.profile-details span{max-width:170px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.menu-link,.logout{display:block;text-align:center;text-decoration:none;padding:10px;border-radius:9px;font-size:11px;font-weight:800}.menu-link{color:#166534;background:#f0fdf4;border:1px solid #dcfce7;margin-bottom:8px}.logout{background:#122019;color:#fff}
.container{width:min(1120px,92%);margin:0 auto;padding:38px 0 70px}.header{margin-bottom:27px}.eyebrow{display:flex;align-items:center;gap:8px;color:var(--green);font-size:10px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:8px}.dot{width:7px;height:7px;border-radius:50%;background:var(--green)}h1{font-family:"Plus Jakarta Sans";font-size:38px;letter-spacing:-1.4px}.header p{margin-top:8px;color:var(--muted);font-size:13px}
.checkout{display:grid;grid-template-columns:1.08fr .92fr;gap:20px;align-items:start}.card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 15px 45px rgba(16,34,26,.055)}
.product-card{padding:16px}.image{height:355px;border-radius:16px;background:linear-gradient(145deg,#eef7f1,#f9fcfa);display:grid;place-items:center;overflow:hidden;position:relative}.image img{width:100%;height:100%;object-fit:contain;padding:15px}.no-image{font-size:70px}.image-badge{position:absolute;top:12px;left:12px;background:rgba(255,255,255,.92);padding:7px 10px;border-radius:20px;color:#166534;font-size:9px;font-weight:800}
.product-info{padding:19px 5px 4px}.badges{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:11px}.badge{background:var(--soft);color:#15803d;padding:6px 9px;border-radius:8px;font-size:9px;font-weight:800;text-transform:uppercase}.badge.type{background:#fff5dc;color:var(--gold)}.product-name{font-family:"Plus Jakarta Sans";font-size:25px;letter-spacing:-.7px}.seller{color:var(--muted);font-size:12px;margin-top:7px;line-height:1.6}.seller strong{color:var(--ink)}
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px}.info{background:#f7faf8;border:1px solid #e8efeb;border-radius:11px;padding:11px}.info small{display:block;color:#89958f;font-size:9px;text-transform:uppercase;margin-bottom:4px}.info strong{font-size:11px}
.summary{padding:25px}.summary-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.summary h2{font-family:"Plus Jakarta Sans";font-size:20px}.secure-pill{background:var(--soft);color:#15803d;padding:6px 9px;border-radius:8px;font-size:9px;font-weight:800}
.rows{border-top:1px solid var(--line)}.row{display:flex;justify-content:space-between;gap:15px;padding:13px 0;border-bottom:1px solid #edf1ef;font-size:12px}.row span:first-child{color:var(--muted)}.row strong{text-align:right;max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.total{margin-top:4px;padding:17px 0;border-bottom:0}.total span{font-weight:800;color:var(--ink)!important;font-size:14px}.total strong{font-family:"Plus Jakarta Sans";font-size:25px;color:var(--green2)}
.user{margin:17px 0;padding:13px;background:#f7faf8;border:1px solid #e8efeb;border-radius:12px}.user small{display:block;color:#89958f;font-size:9px;text-transform:uppercase;margin-bottom:4px}.user strong{display:block;font-size:12px}.user span{color:var(--muted);font-size:10px}
.payment-title{font-family:"Plus Jakarta Sans";font-size:17px;margin:22px 0 12px}.option{display:flex;align-items:center;gap:11px;padding:13px;border:1px solid var(--line);border-radius:12px;margin-bottom:9px;cursor:pointer;transition:.2s}.option:hover,.option.active{border-color:#82d3a1;background:#f6fcf8}.option input{accent-color:var(--green);width:17px;height:17px}.icon{font-size:21px}.option strong{display:block;font-size:12px}.option small{display:block;color:var(--muted);font-size:10px;margin-top:2px}
.pay{width:100%;border:0;border-radius:12px;padding:14px;background:linear-gradient(135deg,var(--green),var(--green2));color:#fff;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 10px 24px rgba(22,163,74,.2);margin-top:8px}.pay:hover{transform:translateY(-1px)}.security{text-align:center;color:#8a958f;font-size:10px;margin:11px 0}.back{display:block;text-align:center;color:var(--green2);font-size:11px;font-weight:800;text-decoration:none;padding:8px}
@media(max-width:850px){.checkout{grid-template-columns:1fr}.image{height:320px}}
@media(max-width:600px){.nav{padding:0 4%}.nav-links{display:none}.list-btn{padding:10px 12px}.container{width:92%;padding-top:27px}h1{font-size:31px}.info-grid{grid-template-columns:1fr}.summary{padding:20px}.profile-menu{right:-5px;width:min(285px,calc(100vw - 25px))}}
</style>
</head>
<body>

<nav class="nav">
<a href="dashboard.php" class="logo"><div class="logo-icon">♻</div>Aav<span>artan</span></a>
<div class="nav-links"><a href="dashboard.php">Marketplace</a><a href="myorder.php">My Orders</a></div>
<div class="nav-right">
<a href="selling.php" class="list-btn">+ List Material</a>
<div class="profile-wrap">
<button class="profile" id="profileButton"><?php echo e(strtoupper(substr(trim($userName),0,1))); ?></button>
<div class="profile-menu" id="profileMenu">
<div class="profile-head"><div class="profile-large"><?php echo e(strtoupper(substr(trim($userName),0,1))); ?></div><div><strong><?php echo e($userName); ?></strong><span><?php echo e($userEmail); ?></span></div></div>
<div class="profile-details"><div><b>College</b><span><?php echo e($_SESSION['college'] ?? ''); ?></span></div><div><b>Course</b><span><?php echo e($_SESSION['course'] ?? ''); ?></span></div><div><b>Year</b><span><?php echo e($_SESSION['year'] ?? ''); ?></span></div></div>
<a href="mylisting.php" class="menu-link">My Listings</a><a href="myorder.php" class="menu-link">My Orders</a><a href="logout.php" class="logout">Sign out</a>
</div></div></div>
</nav>

<main class="container">
<div class="header"><div class="eyebrow"><span class="dot"></span>Secure Checkout</div><h1>Complete your payment</h1><p>Review the order details and choose how you'd like to pay.</p></div>

<div class="checkout">
<section class="card product-card">
<div class="image">
<span class="image-badge">ORDER PREVIEW</span>
<?php if($image): ?><img src="<?php echo e($image); ?>" alt="<?php echo e($name); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='grid';"><div class="no-image" style="display:none">📦</div>
<?php else: ?><div class="no-image">📦</div><?php endif; ?>
</div>
<div class="product-info">
<div class="badges"><span class="badge"><?php echo e($product['category']); ?></span><span class="badge"><?php echo e($product['item_condition']); ?></span><span class="badge type"><?php echo e($type); ?></span></div>
<div class="product-name"><?php echo e($name); ?></div>
<div class="seller">Sold by <strong><?php echo e($product['seller_name']); ?></strong><br><?php echo e($product['seller_college']); ?></div>
<div class="info-grid">
<div class="info"><small>Quantity</small><strong><?php echo $quantity; ?></strong></div>
<div class="info"><small>Handover</small><strong><?php echo e($handoverMethod); ?></strong></div>
<div class="info"><small>Location</small><strong><?php echo e($product['location']); ?></strong></div>
</div>
</div>
</section>

<section class="card summary">
<div class="summary-head"><h2>Order Summary</h2><span class="secure-pill">SECURE</span></div>
<div class="rows">
<div class="row"><span>Material</span><strong><?php echo e($name); ?></strong></div>
<div class="row"><span>Price / item</span><strong><?php echo e($priceDisplay); ?></strong></div>
<div class="row"><span>Quantity</span><strong><?php echo $quantity; ?></strong></div>
<div class="row"><span>Handover</span><strong><?php echo e($handoverMethod); ?></strong></div>
<div class="row total"><span>Total Amount</span><strong><?php echo e($totalDisplay); ?></strong></div>
</div>

<div class="user"><small>Ordering as</small><strong><?php echo e($userName); ?></strong><?php if($userEmail): ?><span><?php echo e($userEmail); ?></span><?php endif; ?></div>

<h3 class="payment-title">Choose payment method</h3>
<label class="option active"><input type="radio" name="payment_method_ui" value="UPI" checked><span class="icon">📱</span><span><strong>UPI</strong><small>Google Pay, PhonePe, Paytm</small></span></label>
<label class="option"><input type="radio" name="payment_method_ui" value="Cash"><span class="icon">💵</span><span><strong>Cash on Handover</strong><small>Pay when the material is handed over</small></span></label>
<label class="option"><input type="radio" name="payment_method_ui" value="Card"><span class="icon">💳</span><span><strong>Debit / Credit Card</strong><small>Secure card payment</small></span></label>

<form action="payment-success.php" method="POST" id="paymentForm">
<input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
<input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
<input type="hidden" name="handover_method" value="<?php echo e($handoverMethod); ?>">
<input type="hidden" name="note" value="<?php echo e($note); ?>">
<input type="hidden" name="total_price" value="<?php echo $totalPrice; ?>">
<input type="hidden" name="payment_method" id="selectedPayment" value="UPI">
<button type="submit" class="pay"><?php if($type==='Donate') echo "🎁 Confirm Request"; elseif($type==='Exchange') echo "🔄 Confirm Exchange"; else echo "💳 Pay ".e($totalDisplay); ?></button>
</form>
<div class="security">🔒 Your order information is securely processed through Aavartan.</div>
<a href="place-order.php" class="back" onclick="event.preventDefault();history.back();">← Go Back</a>
</section>
</div>
</main>

<script>
const btn=document.getElementById("profileButton"),menu=document.getElementById("profileMenu");
if(btn&&menu){btn.onclick=e=>{e.stopPropagation();menu.classList.toggle("show")};menu.onclick=e=>e.stopPropagation();document.onclick=()=>menu.classList.remove("show")}
const selected=document.getElementById("selectedPayment");
document.querySelectorAll('input[name="payment_method_ui"]').forEach(r=>{
r.addEventListener("change",function(){selected.value=this.value;document.querySelectorAll(".option").forEach(x=>x.classList.remove("active"));this.closest(".option").classList.add("active")})
});
</script>
</body>
</html>
<?php $conn->close(); ?>