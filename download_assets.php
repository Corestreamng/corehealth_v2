<?php

// Script to download CDN assets for local use

$assets = [
    [
        'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
        'path' => 'public/assets/css/bootstrap.min.css'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
        'path' => 'public/assets/css/toastr.min.css'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
        'path' => 'public/assets/js/toastr.min.js'
    ],
    [
        'url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
        'path' => 'public/assets/css/inter-font.css'
    ],
    [
        'url' => 'https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js',
        'path' => 'public/assets/js/emoji-button.min.js'
    ],
    [
        'url' => 'https://fonts.googleapis.com/css?family=Nunito',
        'path' => 'public/assets/css/nunito-font.css'
    ]
];

foreach ($assets as $asset) {
    echo "Downloading {$asset['url']}...\n";
    $content = file_get_contents($asset['url']);
    if ($content === false) {
        echo "Failed to download {$asset['url']}\n";
        continue;
    }

    $dir = dirname($asset['path']);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($asset['path'], $content);
    echo "Saved to {$asset['path']}\n";
}

echo "Download complete.\n";
