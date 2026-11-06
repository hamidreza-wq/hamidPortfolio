<?php

declare(strict_types=1);

[$script, $directory, $privateKeyPath] = $argv;
$key = base64_decode(trim(file_get_contents($privateKeyPath)), true);
if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    throw new RuntimeException('Expected a private Ed25519 signing key outside the repository');
}
$manifest = file_get_contents($directory.'/portfolio-manifest.json');
if ($manifest === false || strlen($manifest) > 65536) {
    throw new RuntimeException('Invalid exact-byte release manifest');
}
$signature = sodium_crypto_sign_detached($manifest, $key);
if (file_put_contents($directory.'/portfolio-manifest.sig', base64_encode($signature)."\n") === false) {
    throw new RuntimeException('Unable to save release signature');
}
echo "Exact-byte release manifest signed. The private signing key was not displayed.\n";
