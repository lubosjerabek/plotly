<?php

header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

$s = 64;
$c = (int)($s / 2);
$img = imagecreatetruecolor($s, $s);
imagealphablending($img, false);
imagesavealpha($img, true);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);

$bg  = imagecolorallocate($img, 15, 15, 19);   // #0f0f13
$pur = imagecolorallocate($img, 99, 102, 241);  // #6366f1

imagefilledellipse($img, $c, $c, $s, $s, $bg);   // dark circle background
imagefilledellipse($img, $c, $c, 46, 46, $pur);   // outer purple ring
imagefilledellipse($img, $c, $c, 28, 28, $bg);    // dark cutout
imagefilledellipse($img, $c, $c, 14, 14, $pur);   // inner purple dot

imagepng($img);
