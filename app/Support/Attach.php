<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Policy + storage for internal-comms file attachments. Whitelist by extension
 * (images · PDF · xlsx/docx/pptx), block everything else (executables, macros),
 * cap size, and store on the configured object-storage disk with a random name.
 */
class Attach
{
    public const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'webp', 'gif'];

    public const DOC_EXT = ['pdf', 'xlsx', 'docx', 'pptx'];

    public const IMAGE_MAX = 10 * 1024 * 1024;   // 10 MB

    public const DOC_MAX = 25 * 1024 * 1024;      // 25 MB

    /** canonical content-type per extension (office files sniff as zip, so map by ext) */
    public const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'heic' => 'image/heic', 'heif' => 'image/heif', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** File sharing is on only when a durable object-storage disk is configured. */
    public static function enabled(): bool
    {
        return self::disk() !== null;
    }

    /**
     * The durable object-storage disk to store on, or null when only ephemeral
     * local storage exists. We DON'T assume the disk is literally named "s3":
     * Laravel Cloud names the bucket disk whatever you typed when connecting it
     * (and makes it the default), so prefer the default disk when it is an
     * s3-driver disk, then fall back to an explicit "s3" disk from AWS_* envs.
     */
    public static function disk(): ?string
    {
        $default = (string) config('filesystems.default');
        if ($default !== '' && config("filesystems.disks.{$default}.driver") === 's3') {
            return $default;
        }
        if (filled(config('filesystems.disks.s3.bucket'))) {
            return 's3';
        }

        return null;
    }

    public static function allowedExt(): array
    {
        return array_merge(self::IMAGE_EXT, self::DOC_EXT);
    }

    public static function isImageExt(string $ext): bool
    {
        return in_array(strtolower($ext), self::IMAGE_EXT, true);
    }

    public static function maxBytes(string $ext): int
    {
        return self::isImageExt($ext) ? self::IMAGE_MAX : self::DOC_MAX;
    }

    /** @return 'type'|'size'|'danger'|null  null = accepted */
    public static function reject(UploadedFile $file): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::allowedExt(), true)) {
            return 'type';
        }
        if ($file->getSize() > self::maxBytes($ext)) {
            return 'size';
        }
        // defence in depth: reject anything whose real content sniffs executable/script,
        // even if the extension was whitelisted (e.g. a renamed binary)
        $real = (string) $file->getMimeType();
        $dangerous = ['application/x-dosexec', 'application/x-msdownload', 'application/x-executable',
            'application/x-mach-binary', 'application/x-sh', 'application/x-shellscript',
            'text/x-php', 'application/x-httpd-php', 'application/java-archive', 'text/html'];
        if (in_array($real, $dangerous, true)) {
            return 'danger';
        }
        // images must really be images
        if (self::isImageExt($ext) && ! str_starts_with($real, 'image/')) {
            return 'danger';
        }

        return null;
    }

    /**
     * Store the file privately on the disk under the channel and return metadata.
     *
     * @return array{disk:string,path:string,name:string,mime:string,size:int}
     */
    public static function store(UploadedFile $file, int $channelId): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $disk = self::disk() ?? 'local';
        $path = 'comms/'.$channelId.'/'.Str::uuid()->toString().'.'.$ext;
        // no ACL/visibility arg — Cloudflare R2 (Laravel Cloud storage) rejects ACLs
        Storage::disk($disk)->putFileAs('', $file, $path);

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 180),
            'mime' => self::MIME[$ext] ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    /** Human file size, e.g. "2.4 MB". */
    public static function human(?int $bytes): string
    {
        $b = (int) $bytes;
        if ($b >= 1048576) {
            return number_format($b / 1048576, 1).' MB';
        }
        if ($b >= 1024) {
            return number_format($b / 1024, 0).' KB';
        }

        return $b.' B';
    }
}
