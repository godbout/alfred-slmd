<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'SLMD.class.php';

$postData = json_decode(file_get_contents('php://input'), true);
$slmd = new SLMD();
$response = [];

/**
 * Only for testing
 */
// $postData = [
//     'token' => $slmd->getToken(),
//     'service' => 'facebook',
// ];

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

        default:
            $response['message'] = 'service not recognized';
            break;
    }
}

$slmd->clean();

header('Content-Type: application/json');
echo json_encode($response);
