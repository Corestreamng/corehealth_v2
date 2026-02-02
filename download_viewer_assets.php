<?php

/**
 * Download PDF.js and other document viewer assets for local use
 *
 * Run: php download_viewer_assets.php
 */

$assets = [
    // PDF.js - Core library for rendering PDFs
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
        'path' => 'public/assets/js/pdf.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
        'path' => 'public/assets/js/pdf.worker.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf_viewer.min.css',
        'path' => 'public/assets/css/pdf_viewer.min.css'
    ],

    // Viewer.js - Alternative image/document viewer
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js',
        'path' => 'public/assets/js/viewer.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css',
        'path' => 'public/assets/css/viewer.min.css'
    ],

    // Lightbox2 - Simple image viewer
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js',
        'path' => 'public/assets/js/lightbox.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css',
        'path' => 'public/assets/css/lightbox.min.css'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/images/close.png',
        'path' => 'public/assets/images/lightbox/close.png'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/images/loading.gif',
        'path' => 'public/assets/images/lightbox/loading.gif'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/images/next.png',
        'path' => 'public/assets/images/lightbox/next.png'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/images/prev.png',
        'path' => 'public/assets/images/lightbox/prev.png'
    ],

    // Medium Zoom - Zoom effect for images
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/medium-zoom/1.1.0/medium-zoom.min.js',
        'path' => 'public/assets/js/medium-zoom.min.js'
    ],

    // FileSaver.js - For downloading files
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js',
        'path' => 'public/assets/js/FileSaver.min.js'
    ],

    // Print.js - For printing documents
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js',
        'path' => 'public/assets/js/print.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.css',
        'path' => 'public/assets/css/print.min.css'
    ],
];

echo "=== Downloading Document Viewer Assets ===\n\n";

$success = 0;
$failed = 0;

foreach ($assets as $asset) {
    echo "Downloading: {$asset['url']}\n";

    // Create directory if needed
    $dir = dirname($asset['path']);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  Created directory: {$dir}\n";
    }

    // Download with context for better error handling
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $content = @file_get_contents($asset['url'], false, $context);

    if ($content === false) {
        echo "  ERROR: Failed to download\n";
        $failed++;
        continue;
    }

    file_put_contents($asset['path'], $content);
    echo "  Saved to: {$asset['path']} (" . strlen($content) . " bytes)\n";
    $success++;
}

echo "\n=== Download Complete ===\n";
echo "Success: {$success}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    echo "\nNote: Some downloads failed. You may need to manually download them or check your internet connection.\n";
}
