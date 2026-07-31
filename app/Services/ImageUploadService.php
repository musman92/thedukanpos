<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    /**
     * Max raw upload size in kilobytes (2 MB).
     * Keep in sync with resources/js/lib/imageUpload.js (IMAGE_UPLOAD.MAX_UPLOAD_MB).
     */
    public const MAX_UPLOAD_KB = 2048;

    /** Longest edge after resize (px). Keep in sync with IMAGE_UPLOAD.MAX_EDGE. */
    public const MAX_EDGE = 800;

    /** JPEG quality (1–100). */
    public const JPEG_QUALITY = 80;

    public function __construct(
        protected ImageManager $manager = new ImageManager(new Driver),
    ) {}

    /**
     * Resize, compress to JPEG, and store on the public disk.
     *
     * @return string Relative path on the public disk (e.g. brands/abc.jpg)
     */
    public function storeCompressed(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        $filename = Str::uuid()->toString().'.jpg';
        $path = $directory.'/'.$filename;

        $encoded = $this->manager
            ->read($file->getRealPath())
            ->scaleDown(width: self::MAX_EDGE, height: self::MAX_EDGE)
            ->toJpeg(quality: self::JPEG_QUALITY);

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return route('admin.media.show', ['path' => $path]);
    }
}
