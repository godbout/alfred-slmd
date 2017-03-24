<?php

$query = trim($argv[1]);

$workflowDataDir = getenv('alfred_workflow_data');
if (file_exists($workflowDataDir) === false) {
    mkdir($workflowDataDir);
}

$configFile = $workflowDataDir . '/config.json';
if (file_exists($configFile) === false) {
    echo 'Config File missing.';
    exit;
} else {
    $configFileContent = file_get_contents($configFile);
    $config = json_decode($configFileContent, true);
}

switch ($query) {
    case 'facebook':
    case 'twitter':
    case 'googleplus':
    case 'instagram':
    case 'pinterest':
    case 'all':

        $data = [
            'token' => $config['token'],
            'service' => $query,
        ];

        $dataJson = json_encode($data);

        $ch = curl_init('https://post.sleeplessmind.info/post.sleeplessmind.info.php');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataJson))
        );

        $response = curl_exec($ch);
        $data = json_decode($response, true);
        echo $data['message'];

        break;

    default:
        echo 'Not Implemented.';
        break;
}
