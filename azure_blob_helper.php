<?php

/**
 * Upload a local file directly to Azure Blob Storage container 'images' using REST API
 * 
 * @param string $file_path Absolute path to the local file
 * @param string $blob_name Relative path/filename in container (e.g. 'tables/uploaded_xxx.jpg')
 * @return string|false Public URL of blob if success, or false if failed/not configured
 */
function upload_to_azure_blob($file_path, $blob_name) {
    if (!file_exists($file_path)) {
        return false;
    }

    $connection_string = getenv('AZURE_STORAGE_CONNECTION_STRING');
    if (!$connection_string) {
        return false;
    }

    // Parse Azure Storage Connection String
    preg_match('/AccountName=([^;]+)/i', $connection_string, $m1);
    preg_match('/AccountKey=([^;]+)/i', $connection_string, $m2);

    $account_name = $m1[1] ?? '';
    $account_key = $m2[1] ?? '';

    if (!$account_name || !$account_key) {
        return false;
    }

    $container = 'images';
    $blob_name = ltrim(str_replace('\\', '/', $blob_name), '/');
    
    // Determine mime content type
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $content_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml'
    ];
    $content_type = $content_types[$ext] ?? 'application/octet-stream';

    $content_length = filesize($file_path);
    $file_data = file_get_contents($file_path);
    if ($file_data === false) {
        return false;
    }

    $date = gmdate('D, d M Y H:i:s \G\M\T');
    $canonicalized_headers = "x-ms-blob-type:BlockBlob\nx-ms-date:$date\nx-ms-version:2020-10-02\n";
    $canonicalized_resource = "/$account_name/$container/$blob_name";

    $string_to_sign = "PUT\n\n\n$content_length\n\n$content_type\n\n\n\n\n\n$canonicalized_headers$canonicalized_resource";
    $signature = base64_encode(hash_hmac('sha256', $string_to_sign, base64_decode($account_key), true));

    $url = "https://$account_name.blob.core.windows.net/$container/$blob_name";
    $headers = [
        "x-ms-blob-type: BlockBlob",
        "x-ms-date: $date",
        "x-ms-version: 2020-10-02",
        "Content-Type: $content_type",
        "Content-Length: $content_length",
        "Authorization: SharedKey $account_name:$signature"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 201) {
        return $url;
    }

    return false;
}
