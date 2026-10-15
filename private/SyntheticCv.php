<?php
declare(strict_types=1);
namespace ApplyPilot;
function syntheticCvFixture():array{
    $fixture=json_decode(file_get_contents(__DIR__.'/synthetic-cv.json'),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($fixture))throw new Problem(500,'Synthetic fixture unavailable.');
    return $fixture;
}
