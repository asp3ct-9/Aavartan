<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

/* DATABASE CONNECTION */
$host = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "aavartan_db";

$conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error, ENT_QUOTES, "UTF-8"));
}

$conn->set_charset("utf8mb4");

$userId = (int)$_SESSION["user_id"];

/* GET LATEST USER DETAILS */
$userStmt = $conn->prepare(
    "SELECT id, full_name, email, college, course, year, role
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$userStmt) {
    die("Unable to prepare user request.");
}

$userStmt->bind_param("i", $userId);
$userStmt->execute();

$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

$userStmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/* SYNC SESSION */
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["college"] = $user["college"];
$_SESSION["course"] = $user["course"];
$_SESSION["year"] = $user["year"];
$_SESSION["role"] = $user["role"];

/* PROFILE DATA */
$fullName = trim($user["full_name"]);
$email = $user["email"];
$college = $user["college"];
$course = $user["course"];
$year = $user["year"];

/* GENERATE INITIALS */
$nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);

if (count($nameParts) >= 2) {
    $initials = strtoupper(
        mb_substr($nameParts[0], 0, 1) .
        mb_substr($nameParts[count($nameParts) - 1], 0, 1)
    );
} elseif (count($nameParts) === 1) {
    $initials = strtoupper(mb_substr($nameParts[0], 0, 2));
} else {
    $initials = "U";
}

/* ESCAPE OUTPUT */
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* STATUS CLASS */
function statusClass($status) {
    $status = strtolower(trim((string)$status));

    return match ($status) {
        "pending" => "pending",
        "confirmed" => "confirmed",
        "processing" => "processing",
        "ready for handover" => "ready",
        "completed" => "completed",
        "cancelled", "canceled" => "cancelled",
        default => "pending"
    };
}

/* STATUS ICON */
function statusIcon($status) {
    $status = strtolower(trim((string)$status));

    return match ($status) {
        "pending" => "⏳",
        "confirmed" => "✓",
        "processing" => "⚙",
        "ready for handover" => "📦",
        "completed" => "✓",
        "cancelled", "canceled" => "×",
        default => "•"
    };
}

$allowedStatuses = [
    "Pending",
    "Confirmed",
    "Processing",
    "Ready for Handover",
    "Completed",
    "Cancelled"
];

$message = "";
$messageType = "";

/* =========================================
   UPDATE ORDER STATUS
========================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $orderId = (int)($_POST["order_id"] ?? 0);
    $newStatus = trim($_POST["order_status"] ?? "");

    if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {

        $message = "Invalid order status request.";
        $messageType = "error";

    } else {

        $update = $conn->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
              AND seller_id = ?
            LIMIT 1
        ");

        if (!$update) {

            $message = "Unable to prepare status update.";
            $messageType = "error";

        } else {

            $update->bind_param("sii", $newStatus, $orderId, $userId);

            if ($update->execute()) {

                if ($update->affected_rows > 0) {

                    $message = "Order #".$orderId." status updated to ".$newStatus.".";
                    $messageType = "success";

                } else {

                    $message = "No order was updated. It may not belong to your listings.";
                    $messageType = "error";
                }

            } else {

                $message = "Unable to update order status.";
                $messageType = "error";
            }

            $update->close();
        }
    }
}

/* =========================================
   FETCH SELLER ORDERS
========================================= */

$orders = [];

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

        u.full_name AS buyer_name,
        u.email AS buyer_email,
        u.college AS buyer_college,
        u.course AS buyer_course,
        u.year AS buyer_year

    FROM orders o

    LEFT JOIN listings l
        ON o.listing_id = l.id

    LEFT JOIN users u
        ON o.buyer_id = u.id

    WHERE o.seller_id = ?

    ORDER BY
        CASE
            WHEN o.order_status = 'Pending' THEN 1
            WHEN o.order_status = 'Confirmed' THEN 2
            WHEN o.order_status = 'Processing' THEN 3
            WHEN o.order_status = 'Ready for Handover' THEN 4
            WHEN o.order_status = 'Completed' THEN 5
            WHEN o.order_status = 'Cancelled' THEN 6
            ELSE 7
        END,
        o.created_at DESC
");

if ($stmt) {

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();
}

$conn->close();

/* =========================================
   STATISTICS
========================================= */

$totalOrders = count($orders);

$pendingOrders = 0;
$activeOrders = 0;
$completedOrders = 0;

foreach ($orders as $order) {

    $status = strtolower(trim((string)$order["order_status"]));

    if ($status === "pending") {
        $pendingOrders++;
    }

    if (in_array($status, ["confirmed", "processing", "ready for handover"], true)) {
        $activeOrders++;
    }

    if ($status === "completed") {
        $completedOrders++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order Status | Aavartan</title>

<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --green: #16a34a;
    --green-dark: #0d6f38;
    --green-soft: #e8f8ef;
    --ink: #10221a;
    --muted: #718078;
    --line: #e1ebe5;
    --bg: #f5f8f6;
    --white: #fff;
    --shadow: 0 16px 45px rgba(16,34,26,.08);
}

body {
    font-family: "DM Sans", sans-serif;
    color: var(--ink);
    background:
        radial-gradient(circle at 8% 0%, rgba(22,163,74,.08), transparent 26%),
        var(--bg);
    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
}

/* =========================================================
   MASTER AAVARTAN NAVBAR
========================================================= */

.navbar {
    height: 76px;
    background: rgba(255,255,255,.94);
    backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.nav-inner {
    max-width: 1320px;
    height: 100%;
    margin: auto;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

/* LOGO */

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: "Plus Jakarta Sans", sans-serif;
    font-weight: 800;
    font-size: 21px;
    white-space: nowrap;
    color: var(--ink);
    text-decoration: none;
}

.logo-mark {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: linear-gradient(145deg,#f1fff5,#dcfce7);
    box-shadow: 0 7px 18px rgba(22,163,74,.14);
}

.logo-mark svg {
    width: 34px;
    height: 34px;
    display: block;
}

/* NAVIGATION LINKS */

.main-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    justify-content: center;
}

.nav-link {
    padding: 10px 12px;
    border-radius: 10px;
    color: #526059;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    text-decoration: none;
    transition: .2s ease;
}

.nav-link:hover,
.nav-link.active {
    color: var(--green);
    background: #f0faf4;
}

.nav-icon {
    font-size: 14px;
}

/* RIGHT SIDE */

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-wrap {
    position: relative;
}

/* PROFILE BUTTON */

.profile {
    width: 42px;
    height: 42px;
    border: 1px solid #c7ead5;
    border-radius: 50%;
    background: #d9f8e6;
    color: #166534;
    font-weight: 800;
    cursor: pointer;
}

/* PROFILE DROPDOWN */

.profile-menu {
    position: absolute;
    right: 0;
    top: 52px;
    width: 300px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 17px;
    padding: 15px;
    box-shadow: 0 20px 50px rgba(16,34,26,.14);
    display: none;
    z-index: 1200;
}

.profile-menu.show {
    display: block;
}

.profile-head {
    display: flex;
    align-items: center;
    gap: 11px;
    padding-bottom: 13px;
    border-bottom: 1px solid var(--line);
}

.profile-large {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    display: grid;
    place-items: center;
    font-weight: 800;
}

.profile-head strong {
    display: block;
    font-size: 13px;
}

.profile-head small {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
    max-width: 210px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-details {
    display: grid;
    gap: 8px;
    padding: 12px 0;
}

.profile-details div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 11px;
}

.profile-details b {
    color: var(--muted);
}

.profile-details span {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.profile-nav {
    display: grid;
    gap: 4px;
    border-top: 1px solid var(--line);
    padding-top: 10px;
}

.profile-nav a {
    padding: 9px 10px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    color: #405048;
}

.profile-nav a:hover {
    background: #f0faf4;
    color: var(--green);
}

.profile-nav .logout {
    background: #122019;
    color: white;
    text-align: center;
    margin-top: 4px;
}

/* MOBILE MENU BUTTON */

.mobile-menu-btn {
    display: none;
    border: 1px solid var(--line);
    background: white;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    font-size: 18px;
    cursor: pointer;
}

/* =========================================================
   PAGE DESIGN
========================================================= */

.container {
    max-width: 1240px;
    margin: auto;
    padding: 44px 24px 70px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 28px;
}

.kicker {
    color: var(--green);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

h1 {
    font-family: "Plus Jakarta Sans";
    font-size: 36px;
    letter-spacing: -1.2px;
    margin-top: 8px;
}

.page-head p {
    color: var(--muted);
    margin-top: 8px;
    max-width: 680px;
    line-height: 1.6;
}

.back {
    border: 1px solid var(--line);
    background: white;
    color: #46544d;
    padding: 11px 15px;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 800;
}

/* ALERT */

.alert {
    padding: 13px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 700;
}

.alert.success {
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.alert.error {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #be123c;
}

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 15px;
    margin-bottom: 28px;
}

.stat {
    background: white;
    border: 1px solid var(--line);
    border-radius: 17px;
    padding: 19px;
    box-shadow: 0 7px 25px rgba(16,34,26,.04);
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-icon {
    width: 37px;
    height: 37px;
    border-radius: 11px;
    background: #effaf3;
    display: grid;
    place-items: center;
}

.stat small {
    display: block;
    color: var(--muted);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-top: 13px;
}

.stat strong {
    display: block;
    font-family: "Plus Jakarta Sans";
    font-size: 26px;
    margin-top: 3px;
}

/* ORDERS */

.orders {
    display: grid;
    gap: 17px;
}

.order-card {
    background: white;
    border: 1px solid var(--line);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow);
}

.order-top {
    padding: 18px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border-bottom: 1px solid var(--line);
}

.order-id {
    font-family: "Plus Jakarta Sans";
    font-weight: 800;
    font-size: 14px;
}

.order-date {
    color: var(--muted);
    font-size: 11px;
    margin-top: 4px;
}

/* ORDER STATUS BADGES */

.status-pill {
    padding: 7px 11px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-pill.pending {
    background: #fff7ed;
    color: #c2410c;
}

.status-pill.confirmed {
    background: #eff6ff;
    color: #1d4ed8;
}

.status-pill.processing {
    background: #f5f3ff;
    color: #6d28d9;
}

.status-pill.ready {
    background: #ecfeff;
    color: #0e7490;
}

.status-pill.completed {
    background: #ecfdf5;
    color: #15803d;
}

.status-pill.cancelled {
    background: #fff1f2;
    color: #be123c;
}

/* ORDER BODY */

.order-body {
    display: grid;
    grid-template-columns: minmax(0,1fr) 260px;
    gap: 0;
}

.order-info {
    padding: 20px;
}

.product-row {
    display: flex;
    gap: 15px;
    align-items: center;
}

.product-image {
    width: 76px;
    height: 76px;
    flex: 0 0 76px;
    border-radius: 14px;
    overflow: hidden;
    background: #eff6f1;
    display: grid;
    place-items: center;
    font-size: 31px;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-name {
    font-family: "Plus Jakarta Sans";
    font-size: 16px;
    font-weight: 800;
}

.product-meta {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.price {
    color: var(--green-dark);
    font-weight: 800;
    margin-top: 7px;
}

/* DETAILS */

.details {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 10px;
    margin-top: 18px;
}

.detail {
    background: #f8faf9;
    border: 1px solid #edf2ef;
    border-radius: 11px;
    padding: 11px;
}

.detail small {
    color: var(--muted);
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: .5px;
    font-weight: 800;
}

.detail strong {
    display: block;
    font-size: 12px;
    margin-top: 4px;
}

/* BUYER INFORMATION */

.buyer-box {
    margin-top: 15px;
    padding: 13px;
    border-radius: 12px;
    background: #f4fbf6;
    border: 1px solid #e0f1e6;
}

.buyer-title {
    color: var(--green-dark);
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .7px;
}

.buyer-name {
    font-weight: 800;
    font-size: 13px;
    margin-top: 5px;
}

.buyer-meta {
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
}

.note {
    margin-top: 12px;
    color: #526059;
    font-size: 12px;
    line-height: 1.55;
}

/* UPDATE PANEL */

.update-panel {
    background: #fbfdfc;
    border-left: 1px solid var(--line);
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.update-title {
    font-family: "Plus Jakarta Sans";
    font-size: 13px;
    font-weight: 800;
}

.update-help {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.5;
    margin: 6px 0 13px;
}

.status-select {
    width: 100%;
    height: 44px;
    border: 1px solid #cfdcd5;
    background: white;
    border-radius: 10px;
    padding: 0 11px;
    font-family: inherit;
    font-weight: 700;
    color: #26372e;
    outline: none;
}

.status-select:focus {
    border-color: #86efac;
    box-shadow: 0 0 0 3px rgba(134,239,172,.18);
}

.update-btn {
    border: 0;
    margin-top: 9px;
    height: 44px;
    border-radius: 10px;
    background: #122019;
    color: white;
    font-family: inherit;
    font-weight: 800;
    cursor: pointer;
}

.update-btn:hover {
    background: #1d3026;
}

.update-btn:disabled {
    opacity: .5;
    cursor: not-allowed;
}

/* EMPTY STATE */

.empty {
    background: white;
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 70px 20px;
    text-align: center;
}

.empty-icon {
    font-size: 50px;
}

.empty h2 {
    font-family: "Plus Jakarta Sans";
    font-size: 20px;
    margin-top: 12px;
}

.empty p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 7px;
}

/* =========================================================
   RESPONSIVE DESIGN
========================================================= */

@media(max-width:1050px) {

    .main-nav .nav-link {
        display: flex;
    }

    .order-body {
        grid-template-columns: 1fr;
    }

    .update-panel {
        border-left: 0;
        border-top: 1px solid var(--line);
    }
}

@media(max-width:850px) {

    .main-nav {
        display: none;
        position: absolute;
        left: 15px;
        right: 15px;
        top: 86px;
        padding: 10px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        box-shadow: 0 18px 45px rgba(0,0,0,.12);
        flex-direction: column;
        align-items: stretch;
    }

    .main-nav.open {
        display: flex;
    }

    .nav-link {
        width: 100%;
        padding: 12px;
    }

    .mobile-menu-btn {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stats {
        grid-template-columns: repeat(2,1fr);
    }
}

@media(max-width:650px) {

    .container {
        padding: 30px 15px 50px;
    }

    .nav-inner {
        padding: 0 15px;
    }

    .page-head {
        align-items: flex-start;
        flex-direction: column;
    }

    h1 {
        font-size: 29px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat {
        padding: 14px;
    }

    .stat strong {
        font-size: 22px;
    }

    .details {
        grid-template-columns: 1fr 1fr;
    }

    .order-top {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-menu {
        right: -4px;
        width: 280px;
    }
}

</style>

</head>

<body>

<!-- =====================================================
     MASTER AAVARTAN NAVBAR
====================================================== -->

<nav class="navbar">

    <div class="nav-inner">

        <!-- LOGO -->
        <a href="dashboard.php" class="logo" aria-label="Aavartan Home">

            <div class="logo-mark" aria-hidden="true">

                <svg viewBox="0 0 48 48" role="img">

                    <defs>
                        <linearGradient
                            id="aavartanLogo"
                            x1="8"
                            y1="6"
                            x2="40"
                            y2="42"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop offset="0" stop-color="#22c55e"/>
                            <stop offset="1" stop-color="#0d8f43"/>
                        </linearGradient>
                    </defs>

                    <circle
                        cx="24"
                        cy="24"
                        r="21"
                        fill="#e9f9ef"
                    />

                    <path
                        d="M14 20.5A11 11 0 0 1 32.8 13l2.5 2.5M35.3 10.5v5.8h-5.8M34 27.5A11 11 0 0 1 15.2 35l-2.5-2.5M12.7 37.5v-5.8h5.8"
                        fill="none"
                        stroke="url(#aavartanLogo)"
                        stroke-width="4"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <path
                        d="M19 28.5h10L24 18z"
                        fill="#10221a"
                    />

                </svg>

            </div>

            <span class="brand-name">Aavartan</span>

        </a>

        <!-- NAVIGATION LINKS -->

        <div class="main-nav" id="mainNav">

            <a href="dashboard.php#market" class="nav-link">
                <span class="nav-icon">🛒</span>
                Marketplace
            </a>

            <a href="mylisting.php" class="nav-link">
                <span class="nav-icon">📦</span>
                My Listings
            </a>

            <a href="myorder.php" class="nav-link">
                <span class="nav-icon">🧾</span>
                My Orders
            </a>

            <a href="orderstatus.php"
               class="nav-link active"
               aria-current="page">

                <span class="nav-icon">🔔</span>
                Order Status

            </a>

        </div>

        <!-- RIGHT SIDE -->

        <div class="nav-right">

            <!-- MOBILE MENU -->

            <button
                class="mobile-menu-btn"
                id="mobileMenuBtn"
                type="button"
                aria-label="Open navigation"
                aria-expanded="false"
            >☰</button>

            <!-- PROFILE -->

            <div class="profile-wrap">

                <button
                    class="profile"
                    id="profileButton"
                    title="<?php echo e($fullName); ?>"
                    aria-label="Open profile menu"
                    aria-expanded="false"
                    type="button"
                >
                    <?php echo e($initials); ?>
                </button>

                <!-- PROFILE DROPDOWN -->

                <div class="profile-menu" id="profileMenu">

                    <div class="profile-head">

                        <div class="profile-large">
                            <?php echo e($initials); ?>
                        </div>

                        <div>

                            <strong>
                                <?php echo e($fullName); ?>
                            </strong>

                            <small>
                                <?php echo e($email); ?>
                            </small>

                        </div>

                    </div>

                    <div class="profile-details">

                        <div>
                            <b>College</b>
                            <span><?php echo e($college ?: 'Not added'); ?></span>
                        </div>

                        <div>
                            <b>Course</b>
                            <span><?php echo e($course ?: 'Not added'); ?></span>
                        </div>

                        <div>
                            <b>Year</b>
                            <span><?php echo e($year ?: 'Not added'); ?></span>
                        </div>

                    </div>

                    <div class="profile-nav">

                        <a href="dashboard.php#market">
                            🛒 &nbsp;Marketplace
                        </a>

                        <a href="mylisting.php">
                            📦 &nbsp;My Listings
                        </a>

                        <a href="myorder.php">
                            🧾 &nbsp;My Orders
                        </a>

                        <a href="orderstatus.php" aria-current="page">
                            🔔 &nbsp;Order Status
                        </a>

                        <a href="logout.php" class="logout">
                            ↪ &nbsp;Sign out
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</nav>

<!-- =====================================================
     MAIN PAGE
====================================================== -->

<main class="container">

    <div class="page-head">

        <div>

            <div class="kicker">Seller Center</div>

            <h1>Order Status</h1>

            <p>
                New orders for your listed materials appear here.
                Review the buyer details and update the order status.
                The new status is saved directly to your database and will
                immediately appear in the buyer's My Orders page.
            </p>

        </div>

        <a href="mylisting.php" class="back">
            ← My Listings
        </a>

    </div>

    <?php if ($message !== ""): ?>

        <div class="alert <?php echo e($messageType); ?>">

            <?php echo $messageType === "success" ? "✓ " : "⚠ "; ?>

            <?php echo e($message); ?>

        </div>

    <?php endif; ?>

    <!-- STATISTICS -->

    <section class="stats">

        <div class="stat">

            <div class="stat-top">
                <small>Total Orders</small>
                <div class="stat-icon">🧾</div>
            </div>

            <strong><?php echo $totalOrders; ?></strong>

        </div>

        <div class="stat">

            <div class="stat-top">
                <small>New Orders</small>
                <div class="stat-icon">🔔</div>
            </div>

            <strong><?php echo $pendingOrders; ?></strong>

        </div>

        <div class="stat">

            <div class="stat-top">
                <small>In Progress</small>
                <div class="stat-icon">⚙</div>
            </div>

            <strong><?php echo $activeOrders; ?></strong>

        </div>

        <div class="stat">

            <div class="stat-top">
                <small>Completed</small>
                <div class="stat-icon">✓</div>
            </div>

            <strong><?php echo $completedOrders; ?></strong>

        </div>

    </section>

    <!-- ORDERS -->

    <section class="orders">

        <?php if (empty($orders)): ?>

            <div class="empty">

                <div class="empty-icon">📭</div>

                <h2>No orders yet</h2>

                <p>
                    When a student purchases one of your listed materials,
                    the order will appear here.
                </p>

            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <?php

                    $status = $order["order_status"] ?? "Pending";

                    $statusClass = statusClass($status);

                    $icon = statusIcon($status);

                    $image = trim((string)($order["image_path"] ?? ""));

                ?>

                <article class="order-card">

                    <div class="order-top">

                        <div>

                            <div class="order-id">
                                Order #<?php echo (int)$order["id"]; ?>
                            </div>

                            <div class="order-date">

                                Placed

                                <?php

                                    echo e(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($order["created_at"])
                                        )
                                    );

                                ?>

                            </div>

                        </div>

                        <span class="status-pill <?php echo e($statusClass); ?>">

                            <?php echo e($icon); ?>

                            <?php echo e($status); ?>

                        </span>

                    </div>

                    <div class="order-body">

                        <div class="order-info">

                            <!-- PRODUCT -->

                            <div class="product-row">

                                <div class="product-image">

                                    <?php if ($image !== ""): ?>

                                        <img
                                            src="<?php echo e($image); ?>"
                                            alt="<?php echo e($order["material_name"]); ?>"
                                            onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
                                        >

                                        <span style="display:none">📦</span>

                                    <?php else: ?>

                                        📦

                                    <?php endif; ?>

                                </div>

                                <div>

                                    <div class="product-name">
                                        <?php echo e($order["material_name"]); ?>
                                    </div>

                                    <div class="product-meta">

                                        <?php echo e($order["category"] ?? "Project Material"); ?>

                                        ·

                                        <?php echo e($order["item_condition"] ?? "—"); ?>

                                    </div>

                                    <div class="price">
                                        ₹<?php echo number_format((float)$order["total_price"], 2); ?>
                                    </div>

                                </div>

                            </div>

                            <!-- ORDER DETAILS -->

                            <div class="details">

                                <div class="detail">
                                    <small>Quantity</small>
                                    <strong><?php echo (int)$order["quantity"]; ?></strong>
                                </div>

                                <div class="detail">
                                    <small>Payment</small>
                                    <strong><?php echo e($order["payment_method"]); ?></strong>
                                </div>

                                <div class="detail">
                                    <small>Payment Status</small>
                                    <strong><?php echo e($order["payment_status"]); ?></strong>
                                </div>

                                <div class="detail">
                                    <small>Handover</small>
                                    <strong><?php echo e($order["handover_method"]); ?></strong>
                                </div>

                                <div class="detail">
                                    <small>Location</small>
                                    <strong><?php echo e($order["location"] ?? "—"); ?></strong>
                                </div>

                                <div class="detail">
                                    <small>Unit Price</small>
                                    <strong>₹<?php echo number_format((float)$order["price"], 2); ?></strong>
                                </div>

                            </div>

                            <!-- BUYER INFORMATION -->

                            <div class="buyer-box">

                                <div class="buyer-title">
                                    Buyer Information
                                </div>

                                <div class="buyer-name">
                                    <?php echo e($order["buyer_name"] ?? "Student"); ?>
                                </div>

                                <div class="buyer-meta">

                                    <?php echo e($order["buyer_email"] ?? ""); ?>

                                    <?php if (!empty($order["buyer_college"])): ?>

                                        · <?php echo e($order["buyer_college"]); ?>

                                    <?php endif; ?>

                                </div>

                            </div>

                            <!-- BUYER NOTE -->

                            <?php if (trim((string)$order["note"]) !== ""): ?>

                                <div class="note">

                                    <strong>Buyer note:</strong>

                                    <?php echo e($order["note"]); ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- UPDATE ORDER PANEL -->

                        <aside class="update-panel">

                            <div class="update-title">
                                Update Order
                            </div>

                            <div class="update-help">

                                Change the status as you process the buyer's order.

                                The buyer will see the same status in My Orders.

                            </div>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?php echo (int)$order["id"]; ?>"
                                >

                                <select
                                    class="status-select"
                                    name="order_status"
                                    required
                                >

                                    <?php foreach ($allowedStatuses as $option): ?>

                                        <option
                                            value="<?php echo e($option); ?>"
                                            <?php echo $status === $option ? "selected" : ""; ?>
                                        >
                                            <?php echo e($option); ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <button
                                    class="update-btn"
                                    type="submit"
                                    name="update_status"
                                >
                                    Save Status
                                </button>

                            </form>

                        </aside>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<!-- =====================================================
     MASTER NAVBAR JAVASCRIPT
====================================================== -->

<script>

const mobileMenuBtn = document.getElementById("mobileMenuBtn");

const mainNav = document.getElementById("mainNav");

/* MOBILE NAVIGATION */

if (mobileMenuBtn && mainNav) {

    mobileMenuBtn.addEventListener("click", function(event) {

        event.stopPropagation();

        const isOpen = mainNav.classList.toggle("open");

        mobileMenuBtn.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );

        mobileMenuBtn.setAttribute(
            "aria-label",
            isOpen ? "Close navigation" : "Open navigation"
        );

        mobileMenuBtn.innerHTML = isOpen ? "✕" : "☰";

    });

    /* CLOSE MENU AFTER NAVIGATION CLICK */

    mainNav.querySelectorAll(".nav-link").forEach(function(link) {

        link.addEventListener("click", function() {

            mainNav.classList.remove("open");

            mobileMenuBtn.setAttribute("aria-expanded", "false");

            mobileMenuBtn.setAttribute("aria-label", "Open navigation");

            mobileMenuBtn.innerHTML = "☰";

        });

    });

    /* CLOSE MENU ON OUTSIDE CLICK */

    document.addEventListener("click", function(event) {

        if (
            !mainNav.contains(event.target) &&
            !mobileMenuBtn.contains(event.target)
        ) {

            mainNav.classList.remove("open");

            mobileMenuBtn.setAttribute("aria-expanded", "false");

            mobileMenuBtn.setAttribute("aria-label", "Open navigation");

            mobileMenuBtn.innerHTML = "☰";

        }

    });

}

/* PROFILE DROPDOWN */

const profileButton = document.getElementById("profileButton");

const profileMenu = document.getElementById("profileMenu");

if (profileButton && profileMenu) {

    profileButton.addEventListener("click", function(event) {

        event.stopPropagation();

        const isOpen = profileMenu.classList.toggle("show");

        profileButton.setAttribute(
            "aria-expanded",
            isOpen ? "true" : "false"
        );

    });

    /* PREVENT MENU FROM CLOSING WHEN CLICKED INSIDE */

    profileMenu.addEventListener("click", function(event) {

        event.stopPropagation();

    });

    /* CLOSE PROFILE ON OUTSIDE CLICK */

    document.addEventListener("click", function() {

        profileMenu.classList.remove("show");

        profileButton.setAttribute("aria-expanded", "false");

    });

}

</script>

</body>
</html>