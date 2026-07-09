<?php

return [
    /*
     | Internal-comms attachments allow documents up to 25 MB, so the temporary
     | upload endpoint must accept that (Livewire's built-in default is 12 MB).
     | Per-file/-type limits are still enforced server-side by App\Support\Attach.
     | Every other Livewire setting keeps its framework default (merged in).
     */
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => ['required', 'file', 'max:25600'], // 25 MB
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 6, // minutes — large docs over slow site connections
        'cleanup' => true,
    ],
];
