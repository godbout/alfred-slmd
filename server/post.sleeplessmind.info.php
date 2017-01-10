<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'SLMD.class.php';

$postData = json_decode(file_get_contents('php://input'), true);
$slmd = new SLMD();
$response = [];

/**
 * Only for testing
 */
$query = trim($argv[1]);
if (isset($query) === true && $query === 'test') {
    $postData = [
        'token' => $slmd->getToken(),
        'service' => $argv[2],
    ];
}

if ($postData['token'] !== $slmd->getToken()) {
    $response['message'] = 'token invalid';
} else {
    switch ($postData['service']) {
        case 'facebook':
            $response['message'] = $slmd->postWritingToFacebook();
            break;

        case 'twitter':
            $response['message'] = $slmd->postWritingToTwitter();
            break;

        case 'all':
            $response['message'] = $slmd->postWritingToFacebook();
            $response['message'] .= "\r\n" . $slmd->postWritingToTwitter();
            break;

        default:
            $response['message'] = 'service not recognized';
            break;
    }
}

$slmd->clean();

header('Content-Type: application/json');
echo json_encode($response);
