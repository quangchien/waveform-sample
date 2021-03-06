<?php
function requestGet($url) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}

function requestPost($url, $data) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}

function requestPut($url, $data) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}

// COMMON VARIABLES
$API_URL = 'https://ompcj5dtt8.execute-api.us-west-2.amazonaws.com/dev';
$fileName = 'music.mp3';

if(!file_exists($fileName)) {
    echo 'File not exists';
    return false;
}

// GET PRESIGNED URL
$presignedUrl = requestGet($API_URL."/s3/presigned?filename=$fileName");
$presignedUrl = json_decode($presignedUrl);
$presignedUrl = $presignedUrl->url;

// UPLOAD FILE MP3 TO S3
$file = file_get_contents("./$fileName");
$s3 = requestPut($presignedUrl, ['file' => $file]);

// REQUEST LAMBDA TO PROCESS MP3 TO WAVE IMAGE
$wavConf = [
    'w' => '',
    'h' => '',
    'f' => '',
    'c' => '',
    'file' => $fileName,
];
$wav2png = requestPost($API_URL.'/transforms/wav2png', $wavConf);
$wav2png = json_decode($wav2png);

// Check wav2png is error or not
if(!$wav2png->result) {
    echo $wav2png->message;
    return false;
}

// REQUEST LAMBDA TO PROCESS WAVE IMAGE USING IMAGE MAGICK
$imagickConf = [
    'gravity' => '',
    'background' => '',
    'extent' => '',
    'file' => $wav2png->file,
];
$imagick = requestPost($API_URL.'/transforms/imagick', $imagickConf);
$imagick = json_decode($imagick);

// Check wav2png is error or not
if(!$imagick->result) {
    echo $imagick->message;
    return false;
}

// REQUEST LAMBDA TO PROCESS WAVE IMAGE TO GEOMETRIC IMAGE
$primConf = [
    'a' => '',
    'bg' => '',
    'm' => '',
    'n' => '',
    'nth' => '',
    'r' => '',
    'rep' => '',
    's' => '',
    'file' => $imagick->file,
];
$primitive = requestPost($API_URL.'/transforms/primitive', $primConf);
$primitive = json_decode($primitive);

// Check primitive is error or not
if(!$primitive->result) {
    echo $primitive->message;
    return false;
}
// FINAL LINK
$finalLink = $primitive->link;
$finalLink = str_replace('%', '', $finalLink);
echo $finalLink;

// DOWNLOAD
file_put_contents($primitive->file, file_get_contents($finalLink));