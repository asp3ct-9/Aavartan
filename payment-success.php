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
   RECEIVE PAYMENT DATA
========================================= */

$userId = (int)$_SESSION['user_id'];

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

$paymentMethod = trim(
    $_POST['payment_method'] ?? 'UPI'
);


/* =========================================
   BASIC VALIDATION
========================================= */

if ($listingId <= 0 || $quantity <= 0) {

    die("
        <div style='
            font-family:Arial;
            padding:50px;
            text-align:center;
        '>

            <h2>Invalid Order</h2>

            <p>
                The order information is invalid.
            </p>

            <a href='dashboard.php'>
                Back to Marketplace
            </a>

        </div>
    ");

}


/* =========================================
   ALLOWED PAYMENT METHODS
========================================= */

$allowedPaymentMethods = [
    'UPI',
    'Cash',
    'Card'
];

if (!in_array(
    $paymentMethod,
    $allowedPaymentMethods,
    true
)) {

    $paymentMethod = 'UPI';

}


/* =========================================
   START TRANSACTION
========================================= */

$conn->begin_transaction();


try {

    /* =====================================
       LOCK AND FETCH LISTING
    ===================================== */

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
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception(
            "Unable to prepare listing query."
        );
    }

    $stmt->bind_param(
        "i",
        $listingId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $product = $result->fetch_assoc();

    $stmt->close();


    /* =====================================
       CHECK PRODUCT
    ===================================== */

    if (!$product) {

        throw new Exception(
            "This product is no longer available."
        );

    }


    /* =====================================
       PREVENT BUYING OWN LISTING
    ===================================== */

    $sellerId = (int)$product['seller_id'];

    if ($sellerId === $userId) {

        throw new Exception(
            "You cannot order your own listing."
        );

    }


    /* =====================================
       CHECK QUANTITY
    ===================================== */

    $availableQuantity =
        (int)$product['quantity'];

    if ($quantity > $availableQuantity) {

        throw new Exception(
            "Only "
            . $availableQuantity
            . " item(s) are available."
        );

    }


    /* =====================================
       PRODUCT INFORMATION
    ===================================== */

    $materialName =
        trim(
            $product['material_name']
            ?? 'Material'
        );

    $listingType =
        trim(
            $product['listing_type']
            ?? 'Sell'
        );

    $price =
        (float)$product['price'];

    $totalPrice =
        $price * $quantity;


    /* =====================================
       PAYMENT STATUS

       This is a demo payment flow.

       UPI/Card are treated as successful
       for now. A real payment gateway
       should update this after verification.
    ===================================== */

    $paymentStatus = "Paid";

    $orderStatus = "Pending";


    /* =====================================
       INSERT ORDER
    ===================================== */

    $stmt = $conn->prepare("
        INSERT INTO orders
        (
            buyer_id,
            listing_id,
            seller_id,
            material_name,
            quantity,
            price,
            total_price,
            listing_type,
            handover_method,
            note,
            payment_method,
            payment_status,
            order_status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare order query."
        );

    }


    $stmt->bind_param(
        "iiisiddssssss",
        $userId,
        $listingId,
        $sellerId,
        $materialName,
        $quantity,
        $price,
        $totalPrice,
        $listingType,
        $handoverMethod,
        $note,
        $paymentMethod,
        $paymentStatus,
        $orderStatus
    );


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to create order."
        );

    }


    $orderId =
        $conn->insert_id;


    $stmt->close();


    /* =====================================
       REDUCE LISTING QUANTITY
    ===================================== */

    $remainingQuantity =
        $availableQuantity - $quantity;


    if ($remainingQuantity <= 0) {

        /* ================================
           NO STOCK LEFT
        ================================= */

        $stmt = $conn->prepare("
            UPDATE listings
            SET
                quantity = 0,
                status = 'inactive'
            WHERE id = ?
        ");

        if (!$stmt) {

            throw new Exception(
                "Unable to update listing."
            );

        }

        $stmt->bind_param(
            "i",
            $listingId
        );

    } else {

        /* ================================
           STOCK STILL AVAILABLE
        ================================= */

        $stmt = $conn->prepare("
            UPDATE listings
            SET quantity = ?
            WHERE id = ?
        ");

        if (!$stmt) {

            throw new Exception(
                "Unable to update quantity."
            );

        }

        $stmt->bind_param(
            "ii",
            $remainingQuantity,
            $listingId
        );

    }


    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to update product quantity."
        );

    }

    $stmt->close();


    /* =====================================
       COMMIT
    ===================================== */

    $conn->commit();


} catch (Exception $e) {

    /* =====================================
       ROLLBACK
    ===================================== */

    $conn->rollback();

    $errorMessage =
        $e->getMessage();

    $conn->close();

    die("
        <div style='
            font-family:Arial;
            background:#f6f8f7;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:30px;
        '>

            <div style='
                background:white;
                padding:40px;
                border-radius:20px;
                max-width:500px;
                width:100%;
                text-align:center;
                box-shadow:0 10px 35px rgba(20,48,35,.08);
            '>

                <div style='
                    font-size:55px;
                    margin-bottom:15px;
                '>
                    ❌
                </div>

                <h2 style='
                    color:#b42318;
                    margin-bottom:10px;
                '>
                    Order Failed
                </h2>

                <p style='
                    color:#66736d;
                    line-height:1.6;
                    margin-bottom:25px;
                '>
                    " . e($errorMessage) . "
                </p>

                <a
                    href='view-product.php?id="
                    . $listingId .
                    "'
                    style='
                        display:inline-block;
                        background:#176b45;
                        color:white;
                        padding:13px 20px;
                        border-radius:10px;
                        text-decoration:none;
                        font-weight:800;
                    '
                >
                    ← Back to Product
                </a>

            </div>

        </div>
    ");

}


/* =========================================
   SUCCESS DATA
========================================= */

$productName =
    $materialName;

$displayTotal =
    $listingType === 'Donate'
        ? 'FREE'
        : (
            $listingType === 'Exchange'
                ? 'EXCHANGE'
                : '₹' . number_format(
                    $totalPrice,
                    2
                )
        );

$userName =
    $_SESSION['full_name']
    ?? 'User';

$userEmail =
    $_SESSION['email']
    ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Confirmed | Aavartan</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --green:#16a34a;
    --green2:#0f8f43;
    --green-dark:#0d6f38;
    --green-soft:#e8f8ef;
    --ink:#10221a;
    --muted:#718078;
    --line:#dfe9e4;
    --bg:#f5f9f6;
    --white:#fff;
}

*{box-sizing:border-box;margin:0;padding:0}

body{
    font-family:"DM Sans",sans-serif;
    color:var(--ink);
    min-height:100vh;
    background:
        radial-gradient(circle at 10% 0%,rgba(22,163,74,.10),transparent 28%),
        radial-gradient(circle at 90% 20%,rgba(110,231,174,.10),transparent 25%),
        var(--bg);
}

a{text-decoration:none;color:inherit}

/* NAVBAR — same Aavartan family */
.nav{
    height:74px;
    padding:0 5.5%;
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(16px);
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
}

.logo-icon{
    width:40px;
    height:40px;
    border-radius:13px;
    background:linear-gradient(145deg,#d9f8e6,#c2f1d3);
    color:var(--green);
    display:grid;
    place-items:center;
    font-size:20px;
}

.logo span{color:var(--green)}

.nav-right{
    display:flex;
    align-items:center;
    gap:12px;
}

.nav-link{
    color:#526059;
    padding:10px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
}

.nav-link:hover{
    color:var(--green);
    background:var(--green-soft);
}

.list-btn{
    color:#fff;
    background:linear-gradient(135deg,var(--green),var(--green2));
    padding:11px 16px;
    border-radius:11px;
    font-size:12px;
    font-weight:800;
    box-shadow:0 8px 20px rgba(22,163,74,.2);
}

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
    top:53px;
    right:0;
    width:290px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:18px;
    padding:15px;
    box-shadow:0 25px 60px rgba(0,0,0,.14);
    display:none;
    z-index:200;
}

.profile-menu.show{display:block}

.profile-head{
    display:flex;
    gap:11px;
    align-items:center;
    padding-bottom:13px;
    border-bottom:1px solid var(--line);
}

.profile-large{
    width:44px;
    height:44px;
    border-radius:50%;
    background:var(--green-soft);
    color:#166534;
    display:grid;
    place-items:center;
    font-weight:800;
}

.profile-head strong{display:block;font-size:13px}
.profile-head span{
    display:block;
    color:#7a8780;
    font-size:11px;
    margin-top:3px;
    max-width:190px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.profile-menu a{
    display:block;
    text-align:center;
    padding:10px;
    margin-top:9px;
    border-radius:10px;
    font-size:12px;
    font-weight:800;
}

.profile-menu .menu{
    color:#166534;
    background:#f0fdf4;
    border:1px solid #dcfce7;
}

.profile-menu .logout{
    background:#122019;
    color:#fff;
}

/* PAGE */
.container{
    width:min(1050px,92%);
    margin:0 auto;
    padding:55px 0 70px;
}

.success-shell{
    position:relative;
    overflow:hidden;
    background:#fff;
    border:1px solid var(--line);
    border-radius:30px;
    box-shadow:0 25px 70px rgba(16,34,26,.08);
}

.success-shell:before{
    content:"";
    position:absolute;
    left:0;
    right:0;
    top:0;
    height:7px;
    background:linear-gradient(90deg,var(--green),#4ade80,var(--green2));
}

.hero{
    text-align:center;
    padding:48px 30px 34px;
}

.success-icon{
    width:92px;
    height:92px;
    margin:0 auto 20px;
    border-radius:28px;
    display:grid;
    place-items:center;
    background:linear-gradient(145deg,#dcfce7,#bff3d1);
    color:var(--green2);
    font-size:48px;
    font-weight:900;
    box-shadow:0 15px 35px rgba(22,163,74,.16);
    animation:pop .45s ease-out;
}

@keyframes pop{
    0%{transform:scale(.75);opacity:0}
    100%{transform:scale(1);opacity:1}
}

.kicker{
    color:var(--green2);
    font-size:11px;
    font-weight:800;
    letter-spacing:.16em;
    text-transform:uppercase;
    margin-bottom:9px;
}

h1{
    font-family:"Plus Jakarta Sans";
    font-size:clamp(30px,5vw,44px);
    letter-spacing:-1.5px;
    margin-bottom:9px;
}

.subtitle{
    color:var(--muted);
    line-height:1.65;
    font-size:13px;
    max-width:580px;
    margin:auto;
}

/* ORDER ID */
.order-id{
    width:min(420px,90%);
    margin:0 auto 30px;
    padding:14px 18px;
    border:1px solid #dcece2;
    background:#f5fbf7;
    border-radius:15px;
    text-align:center;
}

.order-id small{
    display:block;
    color:#7e8c84;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.08em;
    margin-bottom:5px;
}

.order-id strong{
    color:var(--green2);
    font-family:"Plus Jakarta Sans";
    font-size:23px;
}

/* DETAILS */
.details-wrap{
    padding:0 32px 32px;
}

.details-title{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:12px;
}

.details-title h2{
    font-family:"Plus Jakarta Sans";
    font-size:18px;
}

.status{
    padding:7px 10px;
    border-radius:999px;
    background:var(--green-soft);
    color:var(--green2);
    font-size:10px;
    font-weight:800;
}

.details{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    border:1px solid var(--line);
    border-radius:17px;
    overflow:hidden;
}

.detail{
    padding:16px;
    background:#fff;
    border-bottom:1px solid #edf2ef;
}

.detail:nth-child(odd){
    border-right:1px solid #edf2ef;
}

.detail:nth-last-child(-n+2){border-bottom:0}

.detail small{
    display:block;
    color:#8a958f;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    margin-bottom:5px;
}

.detail strong{
    display:block;
    font-size:13px;
    line-height:1.4;
}

/* TOTAL */
.total-card{
    margin-top:14px;
    padding:17px 18px;
    border-radius:16px;
    background:linear-gradient(135deg,#f0fbf4,#f8fcf9);
    border:1px solid #dceee3;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
}

.total-card span{
    color:#65736b;
    font-weight:700;
    font-size:13px;
}

.total-card strong{
    color:var(--green2);
    font-family:"Plus Jakarta Sans";
    font-size:25px;
}

/* ACTIONS */
.actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:11px;
    margin-top:20px;
}

.btn{
    padding:14px;
    border-radius:12px;
    text-align:center;
    font-weight:800;
    font-size:12px;
    transition:.2s;
}

.btn:hover{transform:translateY(-1px)}

.primary{
    color:#fff;
    background:linear-gradient(135deg,var(--green),var(--green2));
    box-shadow:0 10px 24px rgba(22,163,74,.18);
}

.secondary{
    color:#34443b;
    background:#eef3f0;
}

.footer{
    text-align:center;
    padding:0 25px 30px;
    color:#8a958f;
    font-size:11px;
    line-height:1.6;
}

@media(max-width:700px){
    .nav{padding:0 4%}
    .nav-link{display:none}
    .list-btn{padding:10px 12px}
    .container{padding:30px 0 50px}
    .hero{padding:38px 20px 28px}
    .details-wrap{padding:0 18px 22px}
    .details{grid-template-columns:1fr}
    .detail:nth-child(odd){border-right:0}
    .detail:nth-last-child(-n+2){border-bottom:1px solid #edf2ef}
    .detail:last-child{border-bottom:0}
    .actions{grid-template-columns:1fr}
    .profile-menu{right:-5px;width:min(290px,calc(100vw - 25px))}
}

@media(max-width:430px){
    .logo{font-size:19px}
    .logo-icon{width:36px;height:36px}
    .profile{width:38px;height:38px}
    .success-icon{width:78px;height:78px;font-size:40px}
    h1{font-size:30px}
    .total-card strong{font-size:21px}
}
</style>
</head>

<body>

<nav class="nav">

    <a href="dashboard.php" class="logo">
        <div class="logo-icon">♻</div>
        Aav<span>artan</span>
    </a>

    <div class="nav-right">

        <a href="dashboard.php" class="nav-link">
            Marketplace
        </a>

        <a href="myorder.php" class="nav-link">
            My Orders
        </a>

        <a href="selling.php" class="list-btn">
            + List Material
        </a>

        <div class="profile-wrap">

            <button class="profile" id="profileButton" type="button">
                <?php
                $parts = preg_split('/\s+/', trim($userName), -1, PREG_SPLIT_NO_EMPTY);
                $initials = count($parts) >= 2
                    ? strtoupper($parts[0][0] . $parts[count($parts)-1][0])
                    : strtoupper(substr($userName,0,2));
                echo e($initials);
                ?>
            </button>

            <div class="profile-menu" id="profileMenu">

                <div class="profile-head">

                    <div class="profile-large">
                        <?php echo e($initials); ?>
                    </div>

                    <div>
                        <strong><?php echo e($userName); ?></strong>
                        <span><?php echo e($userEmail); ?></span>
                    </div>

                </div>

                <a href="mylisting.php" class="menu">
                    My Listings
                </a>

                <a href="myorder.php" class="menu">
                    My Orders
                </a>

                <a href="logout.php" class="logout">
                    Sign out
                </a>

            </div>

        </div>

    </div>

</nav>


<main class="container">

    <section class="success-shell">

        <div class="hero">

            <div class="success-icon">✓</div>

            <div class="kicker">AAVARTAN • ORDER CONFIRMED</div>

            <h1>Order Successful!</h1>

            <p class="subtitle">
                Your order has been successfully placed.
                The seller can now review your request and coordinate
                the handover with you.
            </p>

        </div>


        <div class="order-id">

            <small>Order ID</small>

            <strong>
                #<?php echo $orderId; ?>
            </strong>

        </div>


        <div class="details-wrap">

            <div class="details-title">

                <h2>Order Details</h2>

                <span class="status">
                    ✓ Paid
                </span>

            </div>


            <div class="details">

                <div class="detail">
                    <small>Material</small>
                    <strong><?php echo e($productName); ?></strong>
                </div>

                <div class="detail">
                    <small>Quantity</small>
                    <strong><?php echo $quantity; ?></strong>
                </div>

                <div class="detail">
                    <small>Payment Method</small>
                    <strong><?php echo e($paymentMethod); ?></strong>
                </div>

                <div class="detail">
                    <small>Handover</small>
                    <strong><?php echo e($handoverMethod); ?></strong>
                </div>

                <div class="detail">
                    <small>Payment Status</small>
                    <strong>Payment received</strong>
                </div>

                <div class="detail">
                    <small>Order Status</small>
                    <strong>Awaiting seller handover</strong>
                </div>

            </div>


            <div class="total-card">

                <span>Total Amount</span>

                <strong>
                    <?php echo e($displayTotal); ?>
                </strong>

            </div>


            <div class="actions">

                <a href="dashboard.php" class="btn secondary">
                    ← Marketplace
                </a>

                <a href="myorder.php" class="btn primary">
                    📦 My Orders
                </a>

            </div>

        </div>


        <div class="footer">
            Thank you for using Aavartan.
            Your order is now visible in your My Orders section.
        </div>

    </section>

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
