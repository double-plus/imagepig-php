<?php
require 'src/ImagePig.php';

$path = 'output';

if (!is_dir($path)) {
    mkdir($path);
}

$jane = 'https://imagepig.com/static/jane.jpeg';
$mona_lisa = 'https://imagepig.com/static/mona_lisa.jpeg';

$imagepig = new \ImagePig\ImagePig(getenv('IMAGEPIG_API_KEY'));

$imagepig->default('pig')->save($path . '/pig1.jpeg');
$imagepig->xl('pig')->save($path . '/pig2.jpeg');
$imagepig->flux('pig')->save($path . '/pig3.jpeg');
$imagepig->faceswap($jane, $mona_lisa)->save($path . '/faceswap.jpeg');
$imagepig->upscale($jane)->save($path . '/upscale.jpeg');
$imagepig->cutout($jane)->save($path . '/cutout.png');
$imagepig->replace($jane, 'woman', 'robot')->save($path . '/replace.jpeg');
