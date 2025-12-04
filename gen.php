<?php
function generateRandomKey($length = 6) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

$puroks = ['P1', 'P2', 'P3', 'P4A', 'P4B', 'P5', 'P6', 'P7'];
$keys_per_purok = 1000;

if (!is_dir('keys')) {
    mkdir('keys', 0777, true);
}

foreach ($puroks as $purok) {
    $keys = [];
    $filename = "keys/{$purok}_confirmation_key.json"; // Use original purok name
    
    while (count($keys) < $keys_per_purok) {
        $unique_key = "SMCT-{$purok}-" . generateRandomKey();
        if (!isset($keys[$unique_key])) {
            $keys[$unique_key] = ['used' => false];
        }
    }
    
    file_put_contents($filename, json_encode($keys, JSON_PRETTY_PRINT));
    echo "Generated keys for {$purok} in {$filename}\n";
}

echo "All keys generated successfully.\n";
?>