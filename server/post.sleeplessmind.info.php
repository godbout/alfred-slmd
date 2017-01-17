<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'SleeplessmindAuth.class.php';
require_once 'SleeplessmindWriting.class.php';

$postData = json_decode(file_get_contents('php://input'), true);

$auth = new SleeplessmindAuth();
$writing = new SleeplessmindWriting();

$response = [];

/**
 * Only for testing
 */
$query = trim($argv[1]);
if (isset($query) === true && $query === 'test') {
    $postData = [
        'token' => '',
        'service' => $argv[2],
    ];
}

if ($auth->authenticate($postData['token']) === false) {
    $response['message'] = 'token invalid';
} else {
    switch ($postData['service']) {
        case 'facebook':
            $response['message'] = $writing->postToFacebook();
            break;

        case 'twitter':
            $response['message'] = $writing->postToTwitter();
            break;

        case 'googleplus':
            $response['message'] = $writing->postToGooglePlus();
            break;

        case 'instagram':
            $response['message'] = $writing->postToInstagram();
            break;

        case 'all':
            $response['message'] = $writing->postToAllPlatforms();

            if (substr_count($response['message'], 'posted!') >= 3) {
                $writing->updateStatus();
            }
            break;

        default:
            $response['message'] = 'service not recognized';
            break;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
