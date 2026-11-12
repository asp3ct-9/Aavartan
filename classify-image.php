<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function image_check_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    image_check_response(401, [
        'accepted' => false,
        'message' => 'Your session expired. Please sign in again.',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    image_check_response(405, [
        'accepted' => false,
        'message' => 'Please choose an image to check.',
    ]);
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['listing_image_csrf'] ?? '');
if ($csrfToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
    image_check_response(403, [
        'accepted' => false,
        'message' => 'The image check expired. Refresh the page and choose the image again.',
    ]);
}

if (!isset($_FILES['image']) || (int)($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    image_check_response(400, [
        'accepted' => false,
        'message' => 'The image could not be uploaded. Please choose it again.',
    ]);
}

$image = $_FILES['image'];
if ((int)$image['size'] <= 0 || (int)$image['size'] > 5 * 1024 * 1024) {
    image_check_response(400, [
        'accepted' => false,
        'message' => 'Please upload a JPG, PNG or WEBP image smaller than 5MB.',
    ]);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($image['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mimeType, $allowedMimes, true) || !@getimagesize($image['tmp_name'])) {
    image_check_response(400, [
        'accepted' => false,
        'message' => 'This is not a valid image. Please upload a clear photo of the hardware item.',
    ]);
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'image_classifier.php';
$classification = classify_listing_image($image['tmp_name'], $mimeType);

if ($classification === 'accepted') {
    $token = bin2hex(random_bytes(32));
    $verifications = $_SESSION['listing_image_verifications'] ?? [];
    if (!is_array($verifications)) {
        $verifications = [];
    }
    foreach ($verifications as $existingToken => $verification) {
        if (!is_array($verification) || (int)($verification['created_at'] ?? 0) < time() - 900) {
            unset($verifications[$existingToken]);
        }
    }
    if (count($verifications) >= 8) {
        $verifications = array_slice($verifications, -7, null, true);
    }
    $verifications[$token] = [
        'hash' => hash_file('sha256', $image['tmp_name']),
        'created_at' => time(),
    ];
    $_SESSION['listing_image_verifications'] = $verifications;

    image_check_response(200, [
        'accepted' => true,
        'token' => $token,
        'message' => 'Image looks good. You can publish your listing.',
    ]);
}

if ($classification === 'rejected') {
    image_check_response(422, [
        'accepted' => false,
        'message' => "This image doesn't look like a hardware component or project material. Please upload a clear photo of the item.",
    ]);
}

if ($classification === 'not_configured') {
    image_check_response(503, [
        'accepted' => false,
        'message' => 'Image checking is not configured yet. Please contact the site administrator.',
    ]);
}

if ($classification === 'api_limit') {
    image_check_response(429, [
        'accepted' => false,
        'message' => 'Image checking has reached an API usage limit. Please wait and try again. If it keeps happening, the site administrator needs to check API credits and rate limits.',
    ]);
}

image_check_response(503, [
    'accepted' => false,
    'message' => 'Image checking is temporarily unavailable. Please try again in a moment.',
]);
