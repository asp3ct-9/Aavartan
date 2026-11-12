<?php
session_start();
require_once __DIR__ . DIRECTORY_SEPARATOR . "image_classifier.php";

/*
|--------------------------------------------------------------------------
| AAVARTAN / PROJECTWASTE - SELLING PAGE
|--------------------------------------------------------------------------
| PHP + MySQL version. Firebase has been completely removed.
|
| Database : aavartan_db
| User     : logged-in PHP session
| Listing  : listings table
|--------------------------------------------------------------------------
*/

$host = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "aavartan_db";

$successMessage = "";
$errorMessage = "";

if (empty($_SESSION["listing_image_csrf"])) {
    $_SESSION["listing_image_csrf"] = bin2hex(random_bytes(32));
}

/* ---------- Require login ---------- */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

/* ---------- Database connection ---------- */
$conn = new mysqli($host, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$conn->set_charset("utf8mb4");

/* ---------- Load current user from MySQL ---------- */
$userStmt = $conn->prepare(
    "SELECT id, full_name, email, college, course, year
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$userStmt) {
    $conn->close();
    die("Could not prepare user query.");
}

$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    $conn->close();
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/* Keep session information synchronized. */
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["college"] = $user["college"];
$_SESSION["course"] = $user["course"];
$_SESSION["year"] = $user["year"];

/* ---------- Initials ---------- */
$fullName = trim($user["full_name"] ?? "");
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

$displayName = htmlspecialchars($fullName ?: "User", ENT_QUOTES, "UTF-8");
$displayInitials = htmlspecialchars($initials, ENT_QUOTES, "UTF-8");
$displayEmail = htmlspecialchars($user["email"] ?? "", ENT_QUOTES, "UTF-8");
$displayCollege = htmlspecialchars($user["college"] ?? "", ENT_QUOTES, "UTF-8");
$displayCourse = htmlspecialchars($user["course"] ?? "", ENT_QUOTES, "UTF-8");
$displayYear = htmlspecialchars($user["year"] ?? "", ENT_QUOTES, "UTF-8");

/* ---------- Helpers ---------- */
function clean_text($value) {
    return trim((string)$value);
}

function listing_form_value($name, $default = "") {
    return htmlspecialchars((string)($_POST[$name] ?? $default), ENT_QUOTES, "UTF-8");
}

function listing_form_selected($name, $value, $default = "") {
    return (string)($_POST[$name] ?? $default) === $value ? "selected" : "";
}

function listing_form_checked($name, $value, $default = "") {
    return (string)($_POST[$name] ?? $default) === $value ? "checked" : "";
}

function redirect_after_success() {
    header("Location: selling.php?success=1");
    exit;
}

/* ---------- Success message after redirect ---------- */
if (isset($_GET["success"]) && $_GET["success"] === "1") {
    $successMessage = "Your material has been published successfully and saved in MySQL.";
}

/* ---------- Publish listing ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $materialName = clean_text($_POST["material_name"] ?? "");
    $category = clean_text($_POST["category"] ?? "");
    $condition = clean_text($_POST["condition"] ?? "");
    $listingType = clean_text($_POST["sellType"] ?? "");
    $priceInput = clean_text($_POST["price"] ?? "");
    $originalPriceInput = clean_text($_POST["original_price"] ?? "");
    $quantityInput = clean_text($_POST["quantity"] ?? "");
    $location = clean_text($_POST["location"] ?? "");
    $description = clean_text($_POST["description"] ?? "");
    $contactPreference = clean_text($_POST["contact_preference"] ?? "ProjectWaste Messages");
    $handoverMethod = clean_text($_POST["handover_method"] ?? "Meet at College");

    /* Basic validation. */
    $allowedCategories = [
        "Arduino", "Sensors", "Electronics", "Mechanical",
        "Robotics", "IoT", "Cables & Wires", "Tools", "Other"
    ];

    $allowedConditions = ["Like New", "Good", "Used"];
    $allowedTypes = ["Sell", "Exchange", "Donate"];

    if ($materialName === "" || strlen($materialName) > 150) {
        $errorMessage = "Please enter a valid material name.";
    } elseif (!in_array($category, $allowedCategories, true)) {
        $errorMessage = "Please select a valid category.";
    } elseif (!in_array($condition, $allowedConditions, true)) {
        $errorMessage = "Please select a valid condition.";
    } elseif (!in_array($listingType, $allowedTypes, true)) {
        $errorMessage = "Please select Sell, Exchange or Donate.";
    } elseif (!ctype_digit($quantityInput) || (int)$quantityInput < 1) {
        $errorMessage = "Quantity must be at least 1.";
    } elseif ($location === "") {
        $errorMessage = "Please enter your college or location.";
    } elseif ($description === "") {
        $errorMessage = "Please add a description.";
    }

    $quantity = (int)$quantityInput;

    /* Price is required only for Sell. */
    $price = null;
    if ($errorMessage === "" && $listingType === "Sell") {
        if ($priceInput === "" || !is_numeric($priceInput) || (float)$priceInput < 0) {
            $errorMessage = "Please enter a valid selling price.";
        } else {
            $price = (float)$priceInput;
        }
    }

    $originalPrice = null;
    if ($errorMessage === "" && $originalPriceInput !== "") {
        if (!is_numeric($originalPriceInput) || (float)$originalPriceInput < 0) {
            $errorMessage = "Please enter a valid original price.";
        } else {
            $originalPrice = (float)$originalPriceInput;
        }
    }

    /* ---------- Image validation ---------- */
    $imagePathForDb = null;
    $imageNameForDb = null;

    if ($errorMessage === "") {
        if (!isset($_FILES["image"])) {
            $errorMessage = "Image field was not submitted. Make sure the file input has name=\"image\".";
        } elseif ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $uploadError = (int)$_FILES["image"]["error"];
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => "The image is larger than PHP's upload_max_filesize.",
                UPLOAD_ERR_FORM_SIZE => "The image is larger than the form upload limit.",
                UPLOAD_ERR_PARTIAL => "The image upload was incomplete.",
                UPLOAD_ERR_NO_FILE => "Please upload a material photo.",
                UPLOAD_ERR_NO_TMP_DIR => "PHP temporary upload folder is missing.",
                UPLOAD_ERR_CANT_WRITE => "PHP could not write the uploaded file.",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
            ];
            $errorMessage = $uploadErrors[$uploadError] ?? ("Image upload failed. PHP error code: " . $uploadError);
        } else {
            $file = $_FILES["image"];

            if ($file["size"] > 5 * 1024 * 1024) {
                $errorMessage = "Image must be smaller than 5MB.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                $allowedMimes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowedMimes[$mime])) {
                    $errorMessage = "Only JPG, PNG and WEBP images are allowed.";
                } elseif (!@getimagesize($file["tmp_name"])) {
                    $errorMessage = "This file is not a valid image. Please upload a clear JPG, PNG or WEBP photo of the hardware component.";
                } else {
                    $submittedVerificationToken = (string)($_POST["image_verification_token"] ?? "");
                    $uploadedImageHash = hash_file("sha256", $file["tmp_name"]);
                    $verifications = $_SESSION["listing_image_verifications"] ?? [];
                    $verification = is_array($verifications) ? ($verifications[$submittedVerificationToken] ?? null) : null;

                    if (
                        !is_array($verification) ||
                        $submittedVerificationToken === "" ||
                        !hash_equals((string)($verification["hash"] ?? ""), (string)$uploadedImageHash) ||
                        (int)($verification["created_at"] ?? 0) < time() - 900
                    ) {
                        $errorMessage = "Please wait for the image check to finish, then publish your listing.";
                    } else {
                        unset($_SESSION["listing_image_verifications"][$submittedVerificationToken]);
                    }
                }
            }
        }
    }

    /* ---------- Save listing ---------- */
    if ($errorMessage === "") {

        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "listings";

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $errorMessage = "Could not create the image upload folder.";
            }
        }

        if ($errorMessage === "") {

            $extension = $allowedMimes[$mime];
            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file["name"], PATHINFO_FILENAME));
            $uniqueName = $userId . "_" . bin2hex(random_bytes(8)) . "_" . $safeBase . "." . $extension;

            $destination = $uploadDir . DIRECTORY_SEPARATOR . $uniqueName;
            $relativeImagePath = "uploads/listings/" . $uniqueName;

            if (!move_uploaded_file($file["tmp_name"], $destination)) {
                $errorMessage = "Could not save the uploaded image.";
            } else {

                /*
                 * IMPORTANT:
                 * seller_id is the logged-in user's ID.
                 * This is what lets My Listings show only this user's records.
                 */
                $insert = $conn->prepare(
                    "INSERT INTO listings
                    (
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
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
                );

                if (!$insert) {
                    @unlink($destination);
                    $errorMessage = "Could not prepare listing query: " . $conn->error;
                } else {

                    $sellerName = $user["full_name"];
                    $sellerEmail = $user["email"];
                    $sellerCollege = $user["college"];

                    $insert->bind_param(
                        "isssssssddissssss",
                        $userId,
                        $sellerName,
                        $sellerEmail,
                        $sellerCollege,
                        $materialName,
                        $category,
                        $condition,
                        $listingType,
                        $price,
                        $originalPrice,
                        $quantity,
                        $location,
                        $description,
                        $contactPreference,
                        $handoverMethod,
                        $relativeImagePath,
                        $file["name"]
                    );

                    if ($insert->execute()) {
                        $insert->close();
                        $conn->close();
                        redirect_after_success();
                    } else {
                        @unlink($destination);
                        $errorMessage = "Could not save listing: " . $insert->error;
                        $insert->close();
                    }
                }
            }
        }
    }
}

/* ---------- Close DB connection ---------- */
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aavartan | List Your Material</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

*{margin:0;padding:0;box-sizing:border-box}

:root{
    --green:#16a34a;
    --green-2:#0f8f43;
    --green-dark:#0b2116;
    --green-soft:#e9f9ef;
    --mint:#d8f7e5;
    --bg:#f4f8f6;
    --card:#ffffff;
    --ink:#10221a;
    --muted:#6d7b74;
    --line:#dce8e1;
    --danger:#dc2626;
    --shadow:0 20px 60px rgba(16,34,26,.08);
}

html{scroll-behavior:smooth}

body{
    font-family:"DM Sans",sans-serif;
    background:
        radial-gradient(circle at 8% 0%,rgba(22,163,74,.08),transparent 25%),
        radial-gradient(circle at 92% 10%,rgba(52,211,153,.07),transparent 24%),
        var(--bg);
    color:var(--ink);
}

/* NAVBAR — MATCHED WITH MY ORDERS */
.navbar{
    height:74px;
    padding:0 5.5%;
    background:rgba(255,255,255,.90);
    backdrop-filter:blur(16px);
    -webkit-backdrop-filter:blur(16px);
    border-bottom:1px solid #dfe9e4;
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
    color:#16a34a;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
}

.logo span{color:#16a34a}

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
    color:#16a34a;
    background:#e8f8ef;
}

.nav-right{
    display:flex;
    align-items:center;
    gap:12px;
}

.nav-list-btn{
    text-decoration:none;
    color:white;
    background:linear-gradient(135deg,#16a34a,#0f8f43);
    padding:11px 16px;
    border-radius:11px;
    font-size:12px;
    font-weight:800;
    box-shadow:0 8px 20px rgba(22,163,74,.20);
    transition:.2s;
}

.nav-list-btn:hover{
    transform:translateY(-1px);
    background:linear-gradient(135deg,#0f8f43,#0d6f38);
}

.profile-wrap{
    position:relative;
}

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
    background:white;
    border:1px solid var(--line);
    border-radius:18px;
    padding:15px;
    box-shadow:0 25px 60px rgba(0,0,0,.14);
    display:none;
    z-index:300;
}
.profile-menu.show{display:block}
.profile-menu-head{display:flex;align-items:center;gap:11px;padding-bottom:13px;border-bottom:1px solid var(--line)}
.profile-large{
    width:44px;height:44px;border-radius:50%;
    background:var(--green-soft);color:#166534;
    display:flex;align-items:center;justify-content:center;font-weight:800;
}
.profile-menu-head strong{display:block;font-size:13px}
.profile-menu-head span{
    display:block;color:#7a8780;font-size:11px;margin-top:3px;
    max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.profile-details{padding:11px 0;display:grid;gap:8px}
.profile-details div{display:flex;justify-content:space-between;gap:15px;font-size:11px}
.profile-details b{color:#718078;font-weight:600}
.profile-details span{
    color:#25352c;text-align:right;max-width:170px;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.menu-link,.logout-btn{
    display:block;text-align:center;text-decoration:none;
    padding:10px;border-radius:10px;font-size:12px;font-weight:800;
}
.menu-link{color:#166534;background:#f0fdf4;border:1px solid #dcfce7;margin-bottom:8px}
.logout-btn{background:#122019;color:white}

/* HERO */
.page-header{
    position:relative;
    overflow:hidden;
    padding:62px 5.5% 54px;
    background:
        radial-gradient(circle at 84% 25%,rgba(74,222,128,.23),transparent 22%),
        linear-gradient(135deg,#0d2418 0%,#123a25 55%,#0e291b 100%);
    color:white;
}
.page-header:before{
    content:"";
    position:absolute;
    width:360px;height:360px;
    border:1px solid rgba(255,255,255,.09);
    border-radius:50%;
    right:-110px;top:-180px;
}
.page-header:after{
    content:"";
    position:absolute;
    width:180px;height:180px;
    border:1px solid rgba(255,255,255,.07);
    border-radius:50%;
    right:150px;bottom:-100px;
}
.header-inner{max-width:1240px;margin:auto;position:relative;z-index:2}

.breadcrumb{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(255,255,255,.07);
    border-radius:30px;
    color:#b8cabe;
    font-size:11px;
    font-weight:600;
    margin-bottom:20px;
}
.breadcrumb span{color:#72e0a0}

.page-header h1{
    font-family:"Plus Jakarta Sans";
    font-size:48px;
    line-height:1.08;
    letter-spacing:-2px;
    max-width:700px;
}
.page-header h1 span{color:#66e69b}
.page-header p{
    margin-top:15px;
    color:#b9c9c0;
    font-size:15px;
    line-height:1.7;
    max-width:680px;
}
.hero-points{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:23px;
}
.hero-point{
    display:flex;
    align-items:center;
    gap:7px;
    padding:8px 12px;
    border-radius:30px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(255,255,255,.1);
    color:#d4e1da;
    font-size:11px;
    font-weight:700;
}


/* COMPACT LISTING HEADER */
.compact-heading{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:25px;
    margin-bottom:22px;
}
.compact-kicker{
    color:var(--green);
    font-size:10px;
    font-weight:800;
    letter-spacing:1.3px;
    margin-bottom:6px;
}
.compact-heading h1{
    font-family:"Plus Jakarta Sans";
    font-size:30px;
    line-height:1.15;
    letter-spacing:-1px;
}
.compact-heading p{
    margin-top:6px;
    color:var(--muted);
    font-size:12px;
}
.heading-badge{
    flex-shrink:0;
    padding:10px 14px;
    border:1px solid #ccebd7;
    background:var(--green-soft);
    color:#166534;
    border-radius:12px;
    font-size:11px;
    font-weight:800;
}

/* DESKTOP APP-LIKE LAYOUT */
@media(min-width:1001px){
    html,body{
        min-height:100%;
    }

    body{
        overflow-x:hidden;
        overflow-y:auto;
    }

    .main{
        margin:0 auto;
        padding-top:20px;
        padding-bottom:50px;
    }

    .sell-layout{
        min-height:0;
    }

    .form-card{
        height:auto;
        min-height:0;
        overflow:visible;
    }

    .preview-column{
        height:auto;
        min-height:0;
        overflow:visible;
    }
}


/* PAGE SCROLLBAR */
html{
    scrollbar-width:thin;
    scrollbar-color:#9fcfb0 #eef5f1;
}
html::-webkit-scrollbar{
    width:9px;
}
html::-webkit-scrollbar-track{
    background:#eef5f1;
}
html::-webkit-scrollbar-thumb{
    background:#9fcfb0;
    border-radius:20px;
}
html::-webkit-scrollbar-thumb:hover{
    background:#6fbb89;
}

/* MAIN */
.main{max-width:1240px;margin:30px auto 65px;padding:0 20px}
.sell-layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:24px;align-items:start}

/* FORM */
.form-card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:24px;
    padding:30px;
    box-shadow:var(--shadow);
}
.form-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
.form-title h2{font-family:"Plus Jakarta Sans";font-size:21px;letter-spacing:-.4px}
.form-title h2:before{
    content:"01";
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:29px;height:29px;
    margin-right:10px;
    border-radius:9px;
    background:var(--green-soft);
    color:var(--green);
    font-size:11px;
    vertical-align:middle;
}
.required-note{color:#89948e;font-size:11px}

.upload-section{margin-bottom:22px}
.upload-label{font-size:13px;font-weight:800;display:block;margin-bottom:9px}
.upload-box{
    min-height:175px;
    border:2px dashed #c8d8ce;
    border-radius:18px;
    background:linear-gradient(145deg,#fbfefc,#f5faf7);
    display:flex;align-items:center;justify-content:center;
    text-align:center;cursor:pointer;transition:.25s;position:relative;overflow:hidden;
}
.upload-box:hover,.upload-box.dragover{
    border-color:#4ade80;
    background:#f0fdf4;
    transform:translateY(-1px);
}
.upload-content{padding:28px}
.upload-icon{
    width:62px;height:62px;border-radius:18px;
    background:linear-gradient(145deg,#dcfce7,#c9f5d8);
    color:var(--green-dark);
    display:flex;align-items:center;justify-content:center;
    font-size:28px;margin:0 auto 13px;
}
.upload-content h3{font-size:15px}
.upload-content p{color:#7a8780;font-size:11px;margin-top:6px}
.upload-btn{
    display:inline-block;margin-top:14px;padding:10px 15px;
    border-radius:10px;background:var(--green);color:white;
    font-size:12px;font-weight:800;box-shadow:0 7px 18px rgba(22,163,74,.18);
}
#imageInput{display:none}
.image-preview{display:none;width:100%;height:235px;object-fit:contain;background:#f5f8f6}
.remove-image{
    display:none;position:absolute;right:12px;top:12px;width:36px;height:36px;
    border:none;border-radius:50%;background:white;color:var(--danger);
    cursor:pointer;font-size:18px;box-shadow:0 5px 16px rgba(0,0,0,.13);
}

/* FORM FIELDS */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{display:flex;flex-direction:column}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:12px;font-weight:800;margin-bottom:8px;color:#34453c}
.form-group label span{color:var(--danger)}
input,select,textarea{
    width:100%;border:1px solid var(--line);border-radius:11px;
    padding:13px 14px;font-family:inherit;font-size:13px;outline:none;
    background:#fbfdfc;color:var(--ink);transition:.2s;
}
input:hover,select:hover,textarea:hover{border-color:#b8cec0;background:white}
input:focus,select:focus,textarea:focus{
    border-color:#4ade80;background:white;
    box-shadow:0 0 0 4px rgba(34,197,94,.08);
}
textarea{height:125px;resize:vertical}

.sell-options{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.sell-option{position:relative}
.sell-option input{position:absolute;opacity:0}
.sell-option label{
    border:1px solid var(--line);border-radius:12px;padding:14px 10px;
    text-align:center;cursor:pointer;display:block;font-size:12px;font-weight:800;
    transition:.2s;background:#fbfdfc;
}
.sell-option label:hover{border-color:#9ee6b7}
.sell-option input:checked + label{
    border-color:#65d990;background:var(--green-soft);color:#15803d;
    box-shadow:inset 0 0 0 1px rgba(22,163,74,.08);
}

.condition-options{display:flex;gap:8px;flex-wrap:wrap}
.condition{position:relative}
.condition input{position:absolute;opacity:0}
.condition label{
    border:1px solid var(--line);border-radius:30px;padding:9px 13px;
    cursor:pointer;font-size:11px;font-weight:700;background:#fbfdfc;
}
.condition input:checked + label{background:var(--green-soft);border-color:#a7e9bc;color:#15803d}

.divider{height:1px;background:var(--line);margin:20px 0}
.form-footer{display:flex;justify-content:space-between;align-items:center;gap:16px}
.secure-note{color:#78847e;font-size:11px}
.publish-btn{
    padding:14px 24px;font-size:13px;border:none;border-radius:12px;
    background:linear-gradient(135deg,#16a34a,#0f8f43);
    color:white;font-weight:800;box-shadow:0 10px 24px rgba(22,163,74,.22);
    transition:.2s;
}
.publish-btn:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(22,163,74,.27)}
.btn{cursor:pointer;font-family:inherit}

/* PREVIEW */
.preview-column{position:sticky;top:95px}
.preview-card{
    background:white;border:1px solid var(--line);border-radius:24px;
    overflow:hidden;box-shadow:var(--shadow);
}
.preview-header{
    padding:20px 21px;border-bottom:1px solid var(--line);
    display:flex;justify-content:space-between;align-items:center;
}
.preview-header h3{font-family:"Plus Jakarta Sans";font-size:16px}
.preview-header p{color:#7a8780;font-size:11px;margin-top:4px}
.preview-live{
    font-size:9px;font-weight:800;color:#15803d;background:#dcfce7;
    padding:6px 8px;border-radius:20px;
}
.preview-image{
    height:245px;background:linear-gradient(145deg,#edf7f1,#f8fbf9);
    display:flex;align-items:center;justify-content:center;
    font-size:65px;color:#9aa79f;overflow:hidden;
}
.preview-image img{width:100%;height:100%;object-fit:cover;display:none}
.preview-info{padding:21px}
.preview-tag{
    display:inline-block;background:#ecfdf5;color:#15803d;padding:6px 9px;
    border-radius:7px;font-size:9px;font-weight:800;text-transform:uppercase;
}
.preview-info h2{font-family:"Plus Jakarta Sans";font-size:20px;margin-top:11px;line-height:1.3}
.preview-location{color:#78847e;font-size:11px;margin-top:8px}
.preview-description{color:#69766f;font-size:11px;line-height:1.65;margin-top:13px}
.preview-bottom{
    display:flex;justify-content:space-between;align-items:end;
    margin-top:18px;padding-top:15px;border-top:1px solid var(--line);
}
.preview-price{font-size:23px;font-weight:800;color:var(--green-dark)}
.preview-type{
    font-size:10px;color:#718078;background:#f4f7f5;
    padding:6px 8px;border-radius:7px;font-weight:700;
}

.tips{
    margin-top:15px;padding:19px;background:#10221a;color:white;
    border-radius:19px;box-shadow:0 14px 35px rgba(16,34,26,.12);
}
.tips h4{font-size:13px;color:#9af0b9}
.tips ul{margin-top:10px;padding-left:17px}
.tips li{color:#c1cec7;font-size:10.5px;line-height:1.75}

/* MESSAGES */
.server-message{margin-bottom:18px;padding:13px 15px;border-radius:11px;font-size:12px;font-weight:700}
.success-message{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
.error-message{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}
.image-check-status{display:none;margin-top:10px;padding:11px 13px;border-radius:10px;font-size:12px;font-weight:700}
.image-check-status.checking{display:block;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
.image-check-status.success{display:block;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
.image-check-status.error{display:block;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}


@media(max-width:1000px){
    body{
        overflow-x:hidden;
        overflow-y:auto;
    }

    .compact-heading{
        align-items:flex-start;
    }
}

@media(max-width:700px){
    .compact-heading{
        flex-direction:column;
        align-items:flex-start;
        gap:12px;
        margin-bottom:18px;
    }
    .compact-heading h1{font-size:27px}
    .heading-badge{font-size:10px}
}

/* RESPONSIVE */
@media(max-width:1000px){
    .nav-links{display:none}
    .sell-layout{grid-template-columns:1fr}
    .preview-column{position:static}
}
@media(max-width:700px){
    .navbar{padding:0 5%}
    .nav-list-btn{padding:10px 12px}
    .page-header{padding:48px 5% 44px}
    .page-header h1{font-size:36px;letter-spacing:-1.3px}
    .main{padding:0 5%;margin-top:22px}
    .form-card{padding:20px;border-radius:19px}
    .form-grid{grid-template-columns:1fr}
    .form-group.full{grid-column:auto}
    .sell-options{grid-template-columns:1fr}
    .form-footer{flex-direction:column;align-items:stretch}
    .publish-btn{width:100%}
    .upload-box{min-height:190px}
    .preview-image{height:220px}
    footer{flex-direction:column;align-items:flex-start;gap:20px}
    .footer-links{flex-wrap:wrap}
}

</style>
</head>
<body>


<!-- ================= NAVBAR ================= -->

<nav class="navbar">

    <div class="logo">
        <div class="logo-icon">♻</div>
        Aav<span>artan</span>
    </div>

    <div class="nav-links">
        <a href="dashboard.php">Marketplace</a>
        <a href="myorder.php">My Orders</a>
    </div>

    <div class="nav-right">
        <a href="selling.php" class="nav-list-btn">+ List Material</a>

        <div class="profile-wrap">
            <button class="profile" id="profileButton" type="button"
                    title="<?php echo $displayName; ?>"
                    aria-label="Open profile menu">
                <?php echo $displayInitials; ?>
            </button>

            <div class="profile-menu" id="profileMenu">
                <div class="profile-menu-head">
                    <div class="profile-large"><?php echo $displayInitials; ?></div>
                    <div>
                        <strong><?php echo $displayName; ?></strong>
                        <span><?php echo $displayEmail; ?></span>
                    </div>
                </div>

                <div class="profile-details">
                    <div><b>College</b><span><?php echo $displayCollege; ?></span></div>
                    <div><b>Course</b><span><?php echo $displayCourse; ?></span></div>
                    <div><b>Year</b><span><?php echo $displayYear; ?></span></div>
                </div>

                <a href="mylisting.php" class="menu-link">My Listings</a>
                <a href="myorder.php" class="menu-link">My Orders</a>
                <a href="logout.php" class="logout-btn">Sign out</a>
            </div>
        </div>
    </div>

</nav>


<!-- ================= MAIN ================= -->

<main class="main">

<div class="compact-heading">
    <div>
        <div class="compact-kicker">AAVARTAN MARKETPLACE</div>
        <h1>List your material</h1>
        <p>Sell, exchange or donate your unused college project materials.</p>
    </div>
    <div class="heading-badge">♻ Give it a second life</div>
</div>

<div class="sell-layout">


<!-- ================= FORM ================= -->

<section class="form-card">
<form id="listingForm" action="selling.php" method="POST" enctype="multipart/form-data">

    
<?php if ($successMessage): ?>
    <div class="server-message success-message">
        ✓ <?php echo htmlspecialchars($successMessage, ENT_QUOTES, "UTF-8"); ?>
    </div>
<?php endif; ?>

<?php if ($errorMessage): ?>
    <div class="server-message error-message">
        <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, "UTF-8"); ?>
    </div>
<?php endif; ?>

<div class="form-title">

        <h2>Material Details</h2>

        <div class="required-note">
            * Required fields
        </div>

    </div>


    <!-- IMAGE -->

    <div class="upload-section">

        <label class="upload-label">
            Material Photos <span style="color:#dc2626">*</span>
        </label>

        <div
            class="upload-box"
            id="uploadBox"
            onclick="document.getElementById('imageInput').click()"
        >

            <div class="upload-content" id="uploadContent">

                <div class="upload-icon">
                    📷
                </div>

                <h3>
                    Upload material photo
                </h3>

                <p>
                    Drag &amp; drop your image here or choose from your device
                </p>

                <span class="upload-btn">
                    Choose Image
                </span>

                <p>
                    JPG, PNG or WEBP · Max 5MB
                </p>

            </div>

            <img
                id="imagePreview"
                class="image-preview"
                alt="Material Preview"
            >

            <button
                type="button"
                class="remove-image"
                id="removeImage"
                onclick="removeImage(event)"
            >
                ×
            </button>

        </div>

            <input
            type="file"
            id="imageInput"
            name="image"
            accept="image/png,image/jpeg,image/webp"
            required
        >
        <input type="hidden" id="imageVerificationToken" name="image_verification_token" value="">
        <input type="hidden" id="imageCheckCsrf" value="<?php echo htmlspecialchars($_SESSION["listing_image_csrf"], ENT_QUOTES, "UTF-8"); ?>">
        <div id="imageCheckStatus" class="image-check-status" role="status" aria-live="polite"></div>

    </div>


    <!-- FORM -->

    <div class="form-grid">


        <!-- NAME -->

        <div class="form-group full">

            <label>
                Material Name <span>*</span>
            </label>

            <input
                type="text"
                id="materialName" name="material_name" required
                placeholder="e.g. Arduino UNO R3"
                oninput="updatePreview()"
                value="<?php echo listing_form_value("material_name"); ?>"
            >

        </div>


        <!-- CATEGORY -->

        <div class="form-group">

            <label>
                Category <span>*</span>
            </label>

            <select id="category" name="category" required onchange="updatePreview()">

                <option value="">
                    Select Category
                </option>

                <option <?php echo listing_form_selected("category", "Arduino"); ?>>Arduino</option>
                <option <?php echo listing_form_selected("category", "Sensors"); ?>>Sensors</option>
                <option <?php echo listing_form_selected("category", "Electronics"); ?>>Electronics</option>
                <option <?php echo listing_form_selected("category", "Mechanical"); ?>>Mechanical</option>
                <option <?php echo listing_form_selected("category", "Robotics"); ?>>Robotics</option>
                <option <?php echo listing_form_selected("category", "IoT"); ?>>IoT</option>
                <option <?php echo listing_form_selected("category", "Cables & Wires"); ?>>Cables &amp; Wires</option>
                <option <?php echo listing_form_selected("category", "Tools"); ?>>Tools</option>
                <option <?php echo listing_form_selected("category", "Other"); ?>>Other</option>

            </select>

        </div>


        <!-- CONDITION -->

        <div class="form-group">

            <label>
                Condition <span>*</span>
            </label>

            <div class="condition-options">

                <div class="condition">
                    <input
                        type="radio"
                        name="condition"
                        id="new"
                        value="Like New"
                        required
                        <?php echo listing_form_checked("condition", "Like New", "Like New"); ?>
                    >
                    <label for="new">
                        Like New
                    </label>
                </div>

                <div class="condition">
                    <input
                        type="radio"
                        name="condition"
                        id="good"
                        value="Good"
                        <?php echo listing_form_checked("condition", "Good", "Like New"); ?>
                    >
                    <label for="good">
                        Good
                    </label>
                </div>

                <div class="condition">
                    <input
                        type="radio"
                        name="condition"
                        id="used"
                        value="Used"
                        <?php echo listing_form_checked("condition", "Used", "Like New"); ?>
                    >
                    <label for="used">
                        Used
                    </label>
                </div>

            </div>

        </div>


        <!-- SELL TYPE -->

        <div class="form-group full">

            <label>
                What do you want to do? <span>*</span>
            </label>

            <div class="sell-options">

                <div class="sell-option">

                    <input
                        type="radio"
                        name="sellType"
                        id="sell"
                        value="Sell"
                        required
                        <?php echo listing_form_checked("sellType", "Sell", "Sell"); ?>
                        onchange="togglePrice()"
                    >

                    <label for="sell">
                        💰 Sell
                    </label>

                </div>

                <div class="sell-option">

                    <input
                        type="radio"
                        name="sellType"
                        id="exchange"
                        value="Exchange"
                        <?php echo listing_form_checked("sellType", "Exchange", "Sell"); ?>
                        onchange="togglePrice()"
                    >

                    <label for="exchange">
                        🔄 Exchange
                    </label>

                </div>

                <div class="sell-option">

                    <input
                        type="radio"
                        name="sellType"
                        id="donate"
                        value="Donate"
                        <?php echo listing_form_checked("sellType", "Donate", "Sell"); ?>
                        onchange="togglePrice()"
                    >

                    <label for="donate">
                        🎁 Donate
                    </label>

                </div>

            </div>

        </div>


        <!-- PRICE -->

        <div class="form-group">

            <label>
                Selling Price ₹
            </label>

            <input
                type="number"
                id="price" name="price"
                placeholder="e.g. 250"
                min="0"
                oninput="updatePreview()"
                value="<?php echo listing_form_value("price"); ?>"
            >

        </div>


        <!-- ORIGINAL PRICE -->

        <div class="form-group">

            <label>
                Original Price ₹
            </label>

            <input
                type="number"
                id="originalPrice" name="original_price"
                placeholder="e.g. 500"
                min="0"
                value="<?php echo listing_form_value("original_price"); ?>"
            >

        </div>


        <!-- QUANTITY -->

        <div class="form-group">

            <label>
                Quantity <span>*</span>
            </label>

            <input
                type="number"
                id="quantity" name="quantity"
                placeholder="e.g. 2"
                min="1"
                required
                value="<?php echo listing_form_value("quantity", "1"); ?>"
            >

        </div>


        <!-- LOCATION -->

        <div class="form-group">

            <label>
                College / Location <span>*</span>
            </label>

            <input
                type="text"
                id="location" name="location" required
                placeholder="e.g. DY Patil Akurdi"
                oninput="updatePreview()"
                value="<?php echo listing_form_value("location"); ?>"
            >

        </div>


        <!-- DESCRIPTION -->

        <div class="form-group full">

            <label>
                Description <span>*</span>
            </label>

            <textarea
                id="description" name="description"
                placeholder="Tell students about the material, usage, condition, compatibility, reason for selling..."
                oninput="updatePreview()"
                required
            ><?php echo listing_form_value("description"); ?></textarea>

        </div>


        <!-- CONTACT -->

        <div class="form-group">

            <label>
                Contact Preference
            </label>

            <select id="contactPreference" name="contact_preference">

                <option <?php echo listing_form_selected("contact_preference", "ProjectWaste Messages", "ProjectWaste Messages"); ?>>ProjectWaste Messages</option>
                <option <?php echo listing_form_selected("contact_preference", "WhatsApp", "ProjectWaste Messages"); ?>>WhatsApp</option>
                <option <?php echo listing_form_selected("contact_preference", "Phone Call", "ProjectWaste Messages"); ?>>Phone Call</option>

            </select>

        </div>


        <!-- PICKUP -->

        <div class="form-group">

            <label>
                Handover Method
            </label>

            <select id="handoverMethod" name="handover_method">

                <option <?php echo listing_form_selected("handover_method", "Meet at College", "Meet at College"); ?>>Meet at College</option>
                <option <?php echo listing_form_selected("handover_method", "Pickup from Location", "Meet at College"); ?>>Pickup from Location</option>
                <option <?php echo listing_form_selected("handover_method", "Courier", "Meet at College"); ?>>Courier</option>

            </select>

        </div>

    </div>


    <div class="divider"></div>


    <div id="publishStatus"
         style="display:none;margin-top:18px;padding:12px 14px;border-radius:10px;font-size:12px;font-weight:600;">
    </div>

    <div class="form-footer">

        <div class="secure-note">
            🔒 Your personal contact details stay private.
        </div>

        <button
            type="submit"
            class="btn btn-green publish-btn"
            id="publishButton"
        >
            Publish Listing →
        </button>

    </div>

</form>
</section>


<!-- ================= PREVIEW ================= -->

<aside class="preview-column">

    <div class="preview-card">

        <div class="preview-header">
            <div>
                <h3>Listing Preview</h3>
                <p>This is how students will see your material.</p>
            </div>
            <span class="preview-live">LIVE PREVIEW</span>
        </div>


        <div class="preview-image">

            <div id="previewEmoji">
                📦
            </div>

            <img id="cardPreviewImage">

        </div>


        <div class="preview-info">

            <span
                class="preview-tag"
                id="previewCategory"
            >
                MATERIAL
            </span>

            <h2 id="previewName">
                Your material name
            </h2>

            <div
                class="preview-location"
                id="previewLocation"
            >
                📍 Your college / location
            </div>

            <div
                class="preview-description"
                id="previewDescription"
            >
                Your material description will appear here.
                Add useful information to help another student
                understand what you're selling.
            </div>

            <div class="preview-bottom">

                <div
                    class="preview-price"
                    id="previewPrice"
                >
                    ₹0
                </div>

                <div
                    class="preview-type"
                    id="previewType"
                >
                    Sell
                </div>

            </div>

        </div>

    </div>


    <!-- TIPS -->

    <div class="tips">

        <h4>
            💡 Tips for a better listing
        </h4>

        <ul>

            <li>
                Upload a clear photo of the actual material.
            </li>

            <li>
                Mention defects or missing components honestly.
            </li>

            <li>
                Add the exact model number if available.
            </li>

            <li>
                Keep your price reasonable for students.
            </li>

            <li>
                Mention whether testing is possible.
            </li>

        </ul>

    </div>

</aside>


</div>

</main>


<script>
/* ================= PHP DASHBOARD PROFILE ================= */

const profileButton = document.getElementById("profileButton");
const profileMenu = document.getElementById("profileMenu");

if (profileButton && profileMenu) {
    profileButton.addEventListener("click", function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle("show");
    });

    profileMenu.addEventListener("click", function(e) {
        e.stopPropagation();
    });

    document.addEventListener("click", function() {
        profileMenu.classList.remove("show");
    });
}

/* ================= IMAGE PREVIEW ================= */

const imageInput = document.getElementById("imageInput");
const uploadBox = document.getElementById("uploadBox");
const uploadContent = document.getElementById("uploadContent");
const imagePreview = document.getElementById("imagePreview");
const removeImageBtn = document.getElementById("removeImage");
const cardPreviewImage = document.getElementById("cardPreviewImage");
const previewEmoji = document.getElementById("previewEmoji");

let selectedImageFile = null;
let imageVerified = false;
let imageCheckRequestId = 0;

function setImageCheckStatus(message, state) {
    const status = document.getElementById("imageCheckStatus");
    if (!status) return;
    status.textContent = message;
    status.className = "image-check-status " + (state || "");
}

function validateImage(file) {
    if (!file) return false;

    const allowed = ["image/jpeg", "image/png", "image/webp"];

    if (!allowed.includes(file.type)) {
        alert("Only JPG, PNG or WEBP images are allowed.");
        return false;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("Please select an image smaller than 5MB.");
        return false;
    }

    return true;
}

function showImage(file) {
    if (!validateImage(file)) {
        imageInput.value = "";
        selectedImageFile = null;
        imageVerified = false;
        document.getElementById("imageVerificationToken").value = "";
        return;
    }

    selectedImageFile = file;
    imageVerified = false;
    document.getElementById("imageVerificationToken").value = "";
    const requestId = ++imageCheckRequestId;
    const publishButton = document.getElementById("publishButton");
    if (publishButton) publishButton.disabled = true;
    setImageCheckStatus("Checking this photo…", "checking");

    const reader = new FileReader();

    reader.onload = function(e) {
        imagePreview.src = e.target.result;
        imagePreview.style.display = "block";

        uploadContent.style.display = "none";

        removeImageBtn.style.display = "flex";
        removeImageBtn.style.alignItems = "center";
        removeImageBtn.style.justifyContent = "center";

        cardPreviewImage.src = e.target.result;
        cardPreviewImage.style.display = "block";
        previewEmoji.style.display = "none";
    };

    reader.readAsDataURL(file);

    const formData = new FormData();
    formData.append("image", file);
    formData.append("csrf_token", document.getElementById("imageCheckCsrf").value);

    fetch("classify-image.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin"
    })
    .then(async function(response) {
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || "Image check failed.");
        return result;
    })
    .then(function(result) {
        if (requestId !== imageCheckRequestId) return;

        if (result.accepted && result.token) {
            imageVerified = true;
            document.getElementById("imageVerificationToken").value = result.token;
            setImageCheckStatus("Image looks good. You can publish your listing.", "success");
            if (publishButton) publishButton.disabled = false;
            return;
        }

        imageVerified = false;
        if (publishButton) publishButton.disabled = false;
        setImageCheckStatus(result.message || "This image doesn't look like a hardware component or project material. Please upload a clear photo of the item.", "error");
        clearImagePreview();
    })
    .catch(function(error) {
        if (requestId !== imageCheckRequestId) return;
        imageVerified = false;
        if (publishButton) publishButton.disabled = false;
        setImageCheckStatus(error.message === "Failed to fetch" ? "Couldn't reach image checking. Check your connection and try again." : (error.message || "Image checking is temporarily unavailable. Please try again."), "error");
        clearImagePreview();
    });
}

if (imageInput) {
    imageInput.addEventListener("change", function() {
        if (this.files[0]) showImage(this.files[0]);
    });
}

function removeImage(event) {
    if (event) event.stopPropagation();

    imageCheckRequestId++;
    imageVerified = false;
    selectedImageFile = null;
    document.getElementById("imageVerificationToken").value = "";
    setImageCheckStatus("", "");
    const publishButton = document.getElementById("publishButton");
    if (publishButton) publishButton.disabled = false;

    clearImagePreview();
}

function clearImagePreview() {
    selectedImageFile = null;

    if (imageInput) imageInput.value = "";

    imagePreview.src = "";
    imagePreview.style.display = "none";

    uploadContent.style.display = "block";
    removeImageBtn.style.display = "none";

    cardPreviewImage.src = "";
    cardPreviewImage.style.display = "none";
    previewEmoji.style.display = "block";
}

window.removeImage = removeImage;

if (uploadBox) {
    uploadBox.addEventListener("dragover", function(e) {
        e.preventDefault();
        uploadBox.classList.add("dragover");
    });

    uploadBox.addEventListener("dragleave", function() {
        uploadBox.classList.remove("dragover");
    });

    uploadBox.addEventListener("drop", function(e) {
        e.preventDefault();
        uploadBox.classList.remove("dragover");

        const file = e.dataTransfer.files[0];

        if (file) {
            // Assign the dropped file to the file input.
            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                imageInput.files = dt.files;
            } catch (err) {
                console.warn("Could not assign dropped file:", err);
            }

            showImage(file);
        }
    });
}

/* ================= LIVE PREVIEW ================= */

function getSelectedValue(name) {
    const selected = document.querySelector(`input[name="${name}"]:checked`);
    return selected ? selected.value : "";
}

function updatePreview() {
    const name = document.getElementById("materialName").value.trim();
    const category = document.getElementById("category").value;
    const location = document.getElementById("location").value.trim();
    const description = document.getElementById("description").value.trim();
    const price = document.getElementById("price").value;
    const sellType = getSelectedValue("sellType");

    document.getElementById("previewName").innerText =
        name || "Your material name";

    document.getElementById("previewCategory").innerText =
        category || "MATERIAL";

    document.getElementById("previewLocation").innerText =
        "📍 " + (location || "Your college / location");

    document.getElementById("previewDescription").innerText =
        description ||
        "Your material description will appear here. Add useful information to help another student understand what you're selling.";

    document.getElementById("previewType").innerText =
        sellType || "Sell";

    if (sellType === "Donate") {
        document.getElementById("previewPrice").innerText = "FREE";
    } else if (sellType === "Exchange") {
        document.getElementById("previewPrice").innerText = "EXCHANGE";
    } else {
        document.getElementById("previewPrice").innerText =
            price ? "₹" + Number(price).toLocaleString("en-IN") : "₹0";
    }
}

window.updatePreview = updatePreview;

/* ================= PRICE ================= */

function togglePrice() {
    const type = getSelectedValue("sellType");
    const price = document.getElementById("price");

    if (type === "Donate") {
        price.value = "";
        price.disabled = true;
        price.required = false;
        price.placeholder = "No price for donation";
    } else if (type === "Exchange") {
        price.value = "";
        price.disabled = true;
        price.required = false;
        price.placeholder = "Not applicable";
    } else {
        price.disabled = false;
        price.required = true;
        price.placeholder = "e.g. 250";
    }

    updatePreview();
}

window.togglePrice = togglePrice;

/* ================= FORM ================= */

const listingForm = document.getElementById("listingForm");

if (listingForm) {
    listingForm.addEventListener("submit", function(event) {
        const button = document.getElementById("publishButton");

        if (!imageVerified || !document.getElementById("imageVerificationToken").value) {
            event.preventDefault();
            setImageCheckStatus("Please upload a hardware photo and wait for the image check to pass before publishing.", "error");
            return;
        }

        if (button) {
            button.disabled = true;
            button.style.opacity = "0.7";
            button.innerHTML = "Publishing...";
        }
    });
}

togglePrice();
updatePreview();
</script>
</body>
</html>
