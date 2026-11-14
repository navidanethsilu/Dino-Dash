
<?php

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Only logged-in users can access sounds
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please login first.'
    ]);

    exit;
}

// Available sound types
$types = [
    'jump'      => 'cartoon jump',
    'collision' => 'cartoon impact',
    'gameover'  => 'game over',
    'success'   => 'success',
    'click'     => 'button click',
    'music'     => 'retro game music loop'
];

$type = $_GET['type'] ?? '';

if (!isset($types[$type])) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid sound type.'
    ]);

    exit;
}


$cacheDir = __DIR__ . '/../storage/sound-cache';
$cacheFile = $cacheDir . '/' . $type . '.json';

// Cache duration: 7 days
$cacheDuration = 7 * 24 * 60 * 60;

// Create cache directory if it doesn't exist
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}


$cachedData = null;

if (file_exists($cacheFile)) {

    $cachedData = json_decode(
        file_get_contents($cacheFile),
        true
    );

    // Return valid, unexpired cache
    if (
        is_array($cachedData) &&
        isset($cachedData['cached_at']) &&
        isset($cachedData['sounds']) &&
        (time() - $cachedData['cached_at']) < $cacheDuration
    ) {

        echo json_encode([
            'success' => true,
            'sounds' => $cachedData['sounds'],
            'source' => 'cache'
        ]);

        exit;
    }
}


$url = 'https://sfxmint.com/api/v1/search?' .
    http_build_query([
        'q' => $types[$type],
        'limit' => 5
    ]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);


$sounds = [];

if ($response !== false && $httpCode === 200) {

    $apiSounds = json_decode($response, true);

    if (is_array($apiSounds)) {

        foreach ($apiSounds as $sound) {

            if (!empty($sound['mp3_url'])) {

                $sounds[] = [
                    'name' => $sound['title'] ?? '',
                    'url' => $sound['mp3_url'],
                    'license' => $sound['license'] ?? 'CC0'
                ];
            }
        }
    }
}


if (!empty($sounds)) {

    $cacheData = [
        'cached_at' => time(),
        'sounds' => $sounds
    ];

    file_put_contents(
        $cacheFile,
        json_encode($cacheData, JSON_PRETTY_PRINT),
        LOCK_EX
    );

    echo json_encode([
        'success' => true,
        'sounds' => $sounds,
        'source' => 'api'
    ]);

    exit;
}


if (
    is_array($cachedData) &&
    !empty($cachedData['sounds'])
) {

    echo json_encode([
        'success' => true,
        'sounds' => $cachedData['sounds'],
        'source' => 'stale_cache'
    ]);

    exit;
}


http_response_code(503);

echo json_encode([
    'success' => false,
    'message' => 'Sound API unavailable.',
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'api_response' => $response === false ? null : $response
]);

?>
