<?php

/**
 *
 * Flmngr server package for PHP.
 *
 * This file is a part of the server side implementation of Flmngr -
 * the JavaScript/TypeScript file manager widely used for building apps and editors.
 *
 * Comes as a standalone package for custom integrations,
 * and as a part of N1ED web content builder.
 *
 * Flmngr file manager:       https://flmngr.com
 * N1ED web content builder:  https://n1ed.com
 * Developer website:         https://edsdk.com
 *
 * License: GNU General Public License Version 3 or later
 *
 **/

namespace EdSDK\FlmngrServer\lib\file;

use InvalidArgumentException;

/**
 * The BlurHash of an image (https://blurha.sh): a short string the file manager paints as a
 * blurred placeholder while the real preview loads. Same output as the reference encoder.
 */
class BlurHash {

  // base 83 digits, in order of value
  const DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

  /**
   * @param array $pixels rows of [r, g, b] pixels, every channel an int from 0 to 255
   * @param int $xComponents how many cosines across, 1 to 9
   * @param int $yComponents how many cosines down, 1 to 9
   * @return string
   */
  public static function encode($pixels, $xComponents = 4, $yComponents = 3) {
    if ($xComponents < 1 || $xComponents > 9 || $yComponents < 1 || $yComponents > 9) {
      throw new InvalidArgumentException('BlurHash takes 1 to 9 components on each axis');
    }
    $height = is_array($pixels) ? count($pixels) : 0;
    $width = $height > 0 && is_array($pixels[0]) ? count($pixels[0]) : 0;
    if ($width === 0) {
      throw new InvalidArgumentException('BlurHash needs an image of at least one pixel');
    }

    // Everything below works in linear light; there are only 256 byte values to convert
    $linear = [];
    for ($v = 0; $v < 256; $v++) {
      $linear[$v] = self::toLinear($v);
    }
    $cosX = self::cosines($xComponents, $width);
    $cosY = self::cosines($yComponents, $height);

    // One average colour per cosine pair. The first is the plain average, the DC; the rest are the
    // AC terms, how far the picture swings away from it in that direction.
    $terms = [];
    $scale = 1 / ($width * $height);
    for ($j = 0; $j < $yComponents; $j++) {
      for ($i = 0; $i < $xComponents; $i++) {
        $weight = ($i === 0 && $j === 0) ? 1 : 2;
        $r = $g = $b = 0;
        for ($x = 0; $x < $width; $x++) {
          $wx = $weight * $cosX[$i][$x];
          for ($y = 0; $y < $height; $y++) {
            $basis = $wx * $cosY[$j][$y];
            $pixel = $pixels[$y][$x];
            $r += $basis * $linear[$pixel[0]];
            $g += $basis * $linear[$pixel[1]];
            $b += $basis * $linear[$pixel[2]];
          }
        }
        $terms[] = [$r * $scale, $g * $scale, $b * $scale];
      }
    }
    $dc = array_shift($terms);

    // The AC terms are sent relative to the largest of them, which goes first in one digit
    $maxDigit = 0;
    $maxValue = 1;
    if (count($terms) > 0) {
      $largest = max(array_map('max', $terms));
      $maxDigit = (int) floor(max(0, min(82, floor($largest * 166 - 0.5))));
      $maxValue = ($maxDigit + 1) / 166;
    }

    $hash = self::base83($xComponents - 1 + ($yComponents - 1) * 9, 1)
      . self::base83($maxDigit, 1)
      . self::base83((self::toSrgb($dc[0]) << 16) + (self::toSrgb($dc[1]) << 8) + self::toSrgb($dc[2]), 4);
    foreach ($terms as $term) {
      $hash .= self::base83(
        self::quantise($term[0] / $maxValue) * 361
        + self::quantise($term[1] / $maxValue) * 19
        + self::quantise($term[2] / $maxValue),
        2
      );
    }
    return $hash;
  }

  // cos(pi * k * n / size) for every term k and pixel n along one axis
  private static function cosines($count, $size) {
    $table = [];
    for ($k = 0; $k < $count; $k++) {
      for ($n = 0; $n < $size; $n++) {
        $table[$k][$n] = cos(M_PI * $k * $n / $size);
      }
    }
    return $table;
  }

  private static function toLinear($byte) {
    $c = $byte / 255;
    return $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
  }

  private static function toSrgb($value) {
    $c = max(0, min(1, $value));
    $byte = $c <= 0.0031308
      ? $c * 12.92 * 255 + 0.5
      : (1.055 * pow($c, 1 / 2.4) - 0.055) * 255 + 0.5;
    return (int) $byte;
  }

  // An AC value in -1..1 to one of 19 steps, finer near zero where the eye notices more
  private static function quantise($value) {
    $root = ($value < 0 ? -1 : 1) * pow(abs($value), 0.5);
    return (int) floor(max(0, min(18, floor($root * 9 + 9.5))));
  }

  private static function base83($value, $length) {
    $digits = self::DIGITS;
    $out = '';
    for ($n = 0; $n < $length; $n++) {
      $digit = $value % 83;
      $out = $digits[$digit] . $out;
      $value = ($value - $digit) / 83;
    }
    return $out;
  }

}
