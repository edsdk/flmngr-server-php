<?php

use EdSDK\FlmngrServer\lib\file\BlurHash;
use PHPUnit\Framework\TestCase;

class BlurHashTest extends TestCase {

  // fixtures/blurhash-vectors.json holds what the reference encoder gives for these images
  public function testMatchesTheReferenceEncoder() {
    $cases = json_decode(file_get_contents(__DIR__ . '/fixtures/blurhash-vectors.json'), TRUE);
    $this->assertGreaterThan(20, count($cases));
    foreach ($cases as $c) {
      $pixels = self::pixels($c['pattern'], $c['width'], $c['height'], $c['seed']);
      $this->assertSame(
        $c['hash'],
        BlurHash::encode($pixels, $c['x'], $c['y']),
        "{$c['pattern']} {$c['width']}x{$c['height']}, seed {$c['seed']}, {$c['x']}x{$c['y']} components"
      );
    }
  }

  public function testLengthAndSizeDigitFollowTheComponents() {
    $pixels = self::pixels('noise', 10, 8, 7);
    for ($x = 1; $x <= 9; $x++) {
      for ($y = 1; $y <= 9; $y++) {
        $hash = BlurHash::encode($pixels, $x, $y);
        $this->assertSame(4 + 2 * $x * $y, strlen($hash), "$x x $y");
        $this->assertSame($x - 1 + ($y - 1) * 9, self::decode83($hash[0]), "$x x $y");
      }
    }
  }

  // With one component the hash is just the average colour, so a flat image has to come back as is
  public function testFlatImageKeepsItsColour() {
    $colours = [[0, 0, 0], [255, 255, 255], [128, 128, 128], [1, 2, 3], [254, 253, 252], [255, 0, 0], [0, 255, 0], [0, 0, 255]];
    for ($v = 0; $v < 256; $v += 5) {
      $colours[] = [$v, 255 - $v, ($v * 7) % 256];
    }
    foreach ($colours as $rgb) {
      $hash = BlurHash::encode(array_fill(0, 3, array_fill(0, 5, $rgb)), 1, 1);
      $this->assertSame(6, strlen($hash));
      $this->assertSame('00', substr($hash, 0, 2));
      $dc = self::decode83(substr($hash, 2, 4));
      $this->assertSame($rgb, [$dc >> 16, ($dc >> 8) & 255, $dc & 255], implode(',', $rgb));
    }
  }

  public function testThrowsOnBadComponentCounts() {
    foreach ([[0, 3], [4, 0], [10, 3], [4, 10]] as $xy) {
      try {
        BlurHash::encode(self::pixels('noise', 4, 4, 1), $xy[0], $xy[1]);
        $this->fail('no exception for ' . implode('x', $xy));
      }
      catch (InvalidArgumentException $e) {
        $this->assertNotEmpty($e->getMessage());
      }
    }
  }

  public function testThrowsOnAnEmptyImage() {
    foreach ([[], [[]]] as $pixels) {
      try {
        BlurHash::encode($pixels);
        $this->fail('no exception for ' . json_encode($pixels));
      }
      catch (InvalidArgumentException $e) {
        $this->assertNotEmpty($e->getMessage());
      }
    }
  }

  // The images blurhash-vectors.mjs builds, byte for byte
  private static function pixels($pattern, $w, $h, $seed) {
    $state = $seed;
    $rows = [];
    for ($y = 0; $y < $h; $y++) {
      $row = [];
      for ($x = 0; $x < $w; $x++) {
        if ($pattern === 'solid') {
          $p = [($seed * 37) % 256, ($seed * 101) % 256, ($seed * 173) % 256];
        }
        elseif ($pattern === 'noise') {
          $p = [];
          for ($n = 0; $n < 3; $n++) {
            $state = ($state * 48271) % 2147483647;
            $p[] = $state % 256;
          }
        }
        elseif ($pattern === 'gradient') {
          $p = [intdiv(255 * $x, max(1, $w - 1)), intdiv(255 * $y, max(1, $h - 1)), ($seed * 53) % 256];
        }
        elseif ($pattern === 'stripes') {
          $p = intdiv($x, $seed % 5 + 1) % 2 ? [20, 40, 200] : [250, 230, 30];
        }
        elseif ($pattern === 'checker') {
          $size = $seed % 7 + 2;
          $p = (intdiv($x, $size) + intdiv($y, $size)) % 2 ? [0, 0, 0] : [255, 255, 255];
        }
        else {
          $p = [intdiv(255 * $x, $w - 1), intdiv(255 * $y, $h - 1), 128];
          if ($x >= intdiv($w, 6) && $x < intdiv($w, 2) && $y >= intdiv($h, 4) && $y < intdiv($h * 5, 8)) {
            $p = [250, 30, 30];
          }
          if ($x >= intdiv($w * 7, 12) && $x < intdiv($w * 11, 12) && $y >= intdiv($h * 3, 8) && $y < intdiv($h * 7, 8)) {
            $p = [20, 40, 200];
          }
        }
        $row[] = $p;
      }
      $rows[] = $row;
    }
    return $rows;
  }

  private static function decode83($digits) {
    $value = 0;
    foreach (str_split($digits) as $digit) {
      $value = $value * 83 + strpos(BlurHash::DIGITS, $digit);
    }
    return $value;
  }

}
