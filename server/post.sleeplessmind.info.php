<?php

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
            require_once __DIR__ . '/../vendor/autoload.php';

            $facebookSettings = $slmd->getFacebookSettings();
            $fb = new Facebook\Facebook($facebookSettings);

            $facebookData = $slmd->getFacebookData();

            try {
                $facebookResponse = $fb->post('/me/feed', $facebookData);
                $slmd->updateWritingStatus();
                $response['message'] = 'Successfully posted on Facebook!';
            } catch (Facebook\Exceptions\FacebookResponseException $e) {
                $response['message'] = 'Graph returned an error: ' . $e->getMessage();

            } catch (Facebook\Exceptions\FacebookSDKException $e) {
                $response['message'] = 'Facebook SDK returned an error: ' . $e->getMessage();
            }

            break;

        default:
            $response['message'] = 'service not recognized';
            break;
    }
}

$slmd->clean();

header('Content-Type: application/json');
echo json_encode($response);
