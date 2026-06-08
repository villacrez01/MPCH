<?php
/**
 * Image compression helper — GD bundled in XAMPP
 */
error_reporting(0);

function compressImage(string $src, string $dst, int $quality = 75): void
{
    $info = getimagesize($src);
    if (!$info) { fwrite(STDERR, "Cannot read: $src\n"); return; }

    [$w, $h, $type] = $info;
    $srcImg = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => imagecreatefrompng($src),
        default        => null,
    };
    if (!$srcImg) { fwrite(STDERR, "Unsupported format: $src\n"); return; }

    // Resize: max 1920px wide (keeps quality, cuts size 50-70%)
    $maxW = 1920;
    if ($w > $maxW) {
        $ratio = $maxW / $w;
        $newW  = $maxW;
        $newH  = (int)($h * $ratio);
        $tmp   = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($tmp, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($srcImg);
        $srcImg = $tmp;
        [$w, $h] = [$newW, $newH];
    }

    imagejpeg($srcImg, $dst, $quality);
    imagedestroy($srcImg);
}

$base = __DIR__ . '/public/assets/img/';
$files = ['bg-municipal.jpg', 'OTI.jpeg'];

foreach ($files as $f) {
    $src = $base . $f;
    if (!file_exists($src)) { echo "MISSING $f\n"; continue; }
    $oldSize = filesize($src);
    compressImage($src, $src, 70);
    $newSize = filesize($src);
    $saved   = $oldSize - $newSize;
    $pct     = $oldSize > 0 ? round(($saved / $oldSize) * 100, 1) : 0;
    printf("%-25s %8s → %8s  (%-5s KB / %4.1f%%)\n",
        $f,
        number_format($oldSize / 1024, 1) . ' KB',
        number_format($newSize / 1024, 1) . ' KB',
        number_format($saved / 1024, 1) . ' KB',
        $pct
    );
}
