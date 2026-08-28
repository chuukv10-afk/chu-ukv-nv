<?php

$directory = __DIR__ . '/../config/jwt';
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException(sprintf('Cannot create directory "%s".', $directory));
}

$opensslConfig = getenv('OPENSSL_CONF');
if (!$opensslConfig) {
    $candidates = [
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            putenv('OPENSSL_CONF=' . $candidate);
            $opensslConfig = $candidate;
            break;
        }
    }
}

$passphrase = getenv('JWT_PASSPHRASE') ?: '94068c5f66a3bca6ad8b07dd07591b5dfcf1540fa27bc5974ee0fa5f7b63e071';

$keyOptions = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

if (!empty($opensslConfig)) {
    $keyOptions['config'] = str_replace('\\', '/', $opensslConfig);
}

$key = openssl_pkey_new($keyOptions);

if ($key === false) {
    throw new RuntimeException(openssl_error_string() ?: 'Unable to generate private key.');
}

if (!openssl_pkey_export($key, $privateKey, $passphrase)) {
    throw new RuntimeException(openssl_error_string() ?: 'Unable to export private key.');
}

$details = openssl_pkey_get_details($key);
if ($details === false || !isset($details['key'])) {
    throw new RuntimeException(openssl_error_string() ?: 'Unable to export public key.');
}

file_put_contents($directory . '/private.pem', $privateKey);
file_put_contents($directory . '/public.pem', $details['key']);

echo "JWT keys generated in config/jwt/\n";
