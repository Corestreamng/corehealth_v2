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
    ],
    // JsBarcode for barcode generation
    [
        'url' => 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js',
        'path' => 'public/assets/js/jsbarcode.all.min.js'
    ],
    // SortableJS for drag & drop
    [
        'url' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js',
        'path' => 'public/assets/js/sortable.min.js'
    ],
    // Select2 for searchable dropdowns
    [
        'url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
        'path' => 'public/assets/css/select2.min.css'
    ],
    [
        'url' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
        'path' => 'public/assets/js/select2.min.js'
    ],
    // Montserrat font for welcome page
    [
        'url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@100;200;300;400;500;600;700;800;900&display=swap',
        'path' => 'public/assets/css/montserrat-font.css'
    ],
    // Chosen Select for searchable dropdowns
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css',
        'path' => 'public/assets/css/chosen.min.css'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js',
        'path' => 'public/assets/js/chosen.jquery.min.js'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen-sprite.png',
        'path' => 'public/assets/css/chosen-sprite.png'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen-sprite@2x.png',
        'path' => 'public/assets/css/chosen-sprite@2x.png'
    ],
    // Bootstrap Datepicker
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css',
        'path' => 'public/assets/css/bootstrap-datepicker.min.css'
    ],
    [
        'url' => 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js',
        'path' => 'public/assets/js/bootstrap-datepicker.min.js'
    ],
    // Select2 Bootstrap4 theme
    [
        'url' => 'https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css',
        'path' => 'public/assets/css/select2-bootstrap4.min.css'
    ],
    // Marked.js for Markdown rendering in chat
    [
        'url' => 'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
        'path' => 'public/assets/js/marked.min.js'
    ],
    // Retext JS and Unified NLP scribe dependencies for local offline use
    [
        'url' => 'https://esm.sh/unified@10?bundle',
        'path' => 'public/assets/js/unified.min.js'
    ],
    [
        'url' => 'https://esm.sh/retext-english@4?bundle',
        'path' => 'public/assets/js/retext-english.min.js'
    ],
    [
        'url' => 'https://esm.sh/retext-stringify@3?bundle',
        'path' => 'public/assets/js/retext-stringify.min.js'
    ],
    [
        'url' => 'https://esm.sh/retext-repeated-words@3?bundle',
        'path' => 'public/assets/js/retext-repeated-words.min.js'
    ],
    [
        'url' => 'https://esm.sh/retext-indefinite-article@3?bundle',
        'path' => 'public/assets/js/retext-indefinite-article.min.js'
    ],
    [
        'url' => 'https://esm.sh/unist-util-visit@4?bundle',
        'path' => 'public/assets/js/unist-util-visit.min.js'
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
