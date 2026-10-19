// The expected hashes in fixtures/blurhash-vectors.json come from the reference encoder (npm
// "blurhash"), over the same generated images BlurHashTest::pixels() builds. To redo them:
//
//   npm install --no-save blurhash@2.0.5
//   node tests/blurhash-vectors.mjs > tests/fixtures/blurhash-vectors.json

import { encode } from "blurhash";

// [pattern, width, height, seed, x components, y components]
const CASES = [
  ["solid", 1, 1, 1, 1, 1],
  ["solid", 1, 1, 2, 9, 9],
  ["solid", 4, 4, 3, 4, 3],
  ["solid", 13, 7, 4, 5, 5],
  ["solid", 2, 9, 255, 1, 1],
  ["noise", 1, 7, 11, 4, 3],
  ["noise", 9, 1, 12, 4, 3],
  ["noise", 3, 2, 13, 9, 9],
  ["noise", 16, 16, 14, 4, 3],
  ["noise", 17, 13, 15, 7, 2],
  ["noise", 32, 24, 16, 1, 9],
  ["noise", 32, 24, 17, 9, 1],
  ["noise", 64, 48, 99, 4, 3],
  ["gradient", 2, 2, 21, 4, 3],
  ["gradient", 7, 5, 5, 2, 2],
  ["gradient", 40, 30, 22, 4, 3],
  ["gradient", 64, 48, 23, 9, 9],
  ["gradient", 100, 1, 24, 6, 1],
  ["stripes", 30, 20, 31, 4, 3],
  ["stripes", 31, 9, 32, 8, 3],
  ["checker", 24, 24, 41, 4, 3],
  ["checker", 50, 30, 42, 9, 4],
  ["scene", 60, 40, 0, 3, 3],
  ["scene", 120, 80, 0, 4, 3],
];

const div = (a, b) => Math.floor(a / b);

// Integer maths only, so PHP and JavaScript build the same bytes
function pixels(pattern, w, h, seed) {
  let state = seed;
  const next = () => (state = (state * 48271) % 2147483647);
  const rows = [];
  for (let y = 0; y < h; y++) {
    const row = [];
    for (let x = 0; x < w; x++) {
      let p;
      if (pattern === "solid") {
        p = [(seed * 37) % 256, (seed * 101) % 256, (seed * 173) % 256];
      } else if (pattern === "noise") {
        p = [next() % 256, next() % 256, next() % 256];
      } else if (pattern === "gradient") {
        p = [div(255 * x, Math.max(1, w - 1)), div(255 * y, Math.max(1, h - 1)), (seed * 53) % 256];
      } else if (pattern === "stripes") {
        p = div(x, (seed % 5) + 1) % 2 ? [20, 40, 200] : [250, 230, 30];
      } else if (pattern === "checker") {
        const size = (seed % 7) + 2;
        p = (div(x, size) + div(y, size)) % 2 ? [0, 0, 0] : [255, 255, 255];
      } else {
        // "scene": a gradient with a red and a blue block on it
        p = [div(255 * x, w - 1), div(255 * y, h - 1), 128];
        if (x >= div(w, 6) && x < div(w, 2) && y >= div(h, 4) && y < div(h * 5, 8)) p = [250, 30, 30];
        if (x >= div(w * 7, 12) && x < div(w * 11, 12) && y >= div(h * 3, 8) && y < div(h * 7, 8)) p = [20, 40, 200];
      }
      row.push(p);
    }
    rows.push(row);
  }
  return rows;
}

const out = CASES.map(([pattern, width, height, seed, x, y]) => {
  const rgba = new Uint8ClampedArray(width * height * 4);
  pixels(pattern, width, height, seed).forEach((row, j) =>
    row.forEach((p, i) => rgba.set([...p, 255], (j * width + i) * 4)));
  return { pattern, width, height, seed, x, y, hash: encode(rgba, width, height, x, y) };
});
process.stdout.write("[\n" + out.map((c) => "  " + JSON.stringify(c)).join(",\n") + "\n]\n");
