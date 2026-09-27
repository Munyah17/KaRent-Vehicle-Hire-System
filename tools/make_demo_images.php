<?php
/**
 * Generate placeholder demo vehicle photos (GD) into public/uploads/vehicles/.
 * Run: php tools/make_demo_images.php
 */
$dir = dirname(__DIR__) . '/public/uploads/vehicles';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$cars = [
    'demo-1.jpg'  => ['Toyota Corolla', [220, 220, 220]],
    'demo-1b.jpg' => ['Toyota Corolla', [200, 200, 205]],
    'demo-1c.jpg' => ['Toyota Corolla', [210, 215, 220]],
    'demo-2.jpg'  => ['Honda Fit', [190, 195, 200]],
    'demo-3.jpg'  => ['Toyota Hilux', [110, 115, 120]],
    'demo-4.jpg'  => ['Nissan X-Trail', [60, 60, 65]],
    'demo-5.jpg'  => ['Ford Ranger', [40, 70, 140]],
    'demo-6.jpg'  => ['Mazda CX-5', [170, 50, 50]],
    'demo-7.jpg'  => ['Toyota Fortuner', [235, 235, 240]],
    'demo-8.jpg'  => ['Kia Picanto', [230, 200, 60]],
];

foreach ($cars as $file => [$label, $rgb]) {
    $img = imagecreatetruecolor(640, 400);
    $body = imagecolorallocate($img, ...$rgb);
    $glass = imagecolorallocate($img, 160, 200, 230);
    $dark = imagecolorallocate($img, 30, 30, 30);
    $bg = imagecolorallocate($img, 235, 240, 245);
    imagefilledrectangle($img, 0, 0, 640, 400, $bg);

    // ground
    imagefilledrectangle($img, 0, 300, 640, 400, imagecolorallocate($img, 200, 205, 212));
    // body
    imagefilledrectangle($img, 120, 200, 540, 290, $body);
    imagefilledpolygon($img, [170,200, 230,140, 420,140, 480,200], $body);
    // windows
    imagefilledpolygon($img, [240,150, 310,150, 310,195, 200,195], $glass);
    imagefilledpolygon($img, [320,150, 410,150, 460,195, 320,195], $glass);
    // wheels
    imagefilledellipse($img, 190, 295, 60, 60, $dark);
    imagefilledellipse($img, 450, 295, 60, 60, $dark);
    imagefilledellipse($img, 190, 295, 30, 30, imagecolorallocate($img, 150, 150, 150));
    imagefilledellipse($img, 450, 295, 30, 30, imagecolorallocate($img, 150, 150, 150));
    // lights
    imagefilledrectangle($img, 120, 220, 145, 240, imagecolorallocate($img, 255, 240, 160));
    imagefilledrectangle($img, 515, 220, 540, 240, imagecolorallocate($img, 200, 60, 60));
    // label
    imagestring($img, 5, 20, 20, $label . ' (demo image)', $dark);

    imagejpeg($img, $dir . '/' . $file, 82);
    imagedestroy($img);
    echo "wrote $file\n";
}
echo "done\n";
