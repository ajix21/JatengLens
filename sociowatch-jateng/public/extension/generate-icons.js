const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

function createPng(width, height, drawFn) {
  const bytesPerPixel = 4;
  const scanlineLength = width * bytesPerPixel + 1; // +1 filter byte
  const rawData = Buffer.alloc(scanlineLength * height);

  for (let y = 0; y < height; y++) {
    const offset = y * scanlineLength;
    rawData[offset] = 0; // Filter: None
    for (let x = 0; x < width; x++) {
      const [r, g, b, a] = drawFn(x, y, width, height);
      const pxOffset = offset + 1 + x * bytesPerPixel;
      rawData[pxOffset] = r;
      rawData[pxOffset + 1] = g;
      rawData[pxOffset + 2] = b;
      rawData[pxOffset + 3] = a;
    }
  }

  const deflated = zlib.deflateSync(rawData);

  // PNG Signature
  const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

  // IHDR Chunk
  const ihdrData = Buffer.alloc(13);
  ihdrData.writeUInt32BE(width, 0);
  ihdrData.writeUInt32BE(height, 4);
  ihdrData[8] = 8;  // bit depth
  ihdrData[9] = 6;  // RGBA
  ihdrData[10] = 0; // compression
  ihdrData[11] = 0; // filter
  ihdrData[12] = 0; // interlace

  const ihdrChunk = makeChunk('IHDR', ihdrData);
  const idatChunk = makeChunk('IDAT', deflated);
  const iendChunk = makeChunk('IEND', Buffer.alloc(0));

  return Buffer.concat([signature, ihdrChunk, idatChunk, iendChunk]);
}

function makeChunk(type, data) {
  const len = data.length;
  const buf = Buffer.alloc(12 + len);
  buf.writeUInt32BE(len, 0);
  buf.write(type, 4, 4, 'ascii');
  data.copy(buf, 8);
  const crc = crc32(buf.subarray(4, 8 + len));
  buf.writeUInt32BE(crc, 8 + len);
  return buf;
}

function crc32(buf) {
  let crc = 0xFFFFFFFF;
  for (let i = 0; i < buf.length; i++) {
    crc ^= buf[i];
    for (let j = 0; j < 8; j++) {
      crc = (crc >>> 1) ^ (-(crc & 1) & 0xEDB88320);
    }
  }
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

// Draw SocioWatch branded logo: rounded squircle with indigo (#4f46e5) and lens iris
function drawIcon(x, y, w, h) {
  const cx = w / 2;
  const cy = h / 2;
  const r = (w / 2) * 0.9;
  const dist = Math.hypot(x - cx, y - cy);

  // Rounded squircle background
  const nx = (x - cx) / (w * 0.45);
  const ny = (y - cy) / (h * 0.45);
  const p = Math.pow(Math.abs(nx), 3.5) + Math.pow(Math.abs(ny), 3.5);

  if (p > 1.0) {
    return [0, 0, 0, 0]; // Transparent
  }

  // Gradient from Indigo 600 (#4f46e5) to Violet 800 (#5b21b6)
  const t = (x + y) / (w + h);
  let bgR = Math.round(79 + (91 - 79) * t);
  let bgG = Math.round(70 + (33 - 70) * t);
  let bgB = Math.round(229 + (182 - 229) * t);

  // Draw inner ring / lens circle
  const innerR = w * 0.28;
  const ringThickness = Math.max(1.5, w * 0.08);
  const dInner = Math.abs(dist - innerR);

  if (dInner < ringThickness) {
    // White ring
    return [255, 255, 255, 240];
  }

  // Draw center dot (aperture/camera eye)
  if (dist < w * 0.12) {
    return [238, 70, 90, 255]; // Coral accent
  }

  // Top-right indicator dot (Instagram connection badge)
  const dBadge = Math.hypot(x - (w * 0.72), y - (h * 0.28));
  if (dBadge < w * 0.09) {
    return [244, 63, 94, 255];
  }

  return [bgR, bgG, bgB, 255];
}

const iconsDir = path.join(__dirname, 'icons');
if (!fs.existsSync(iconsDir)) {
  fs.mkdirSync(iconsDir, { recursive: true });
}

[16, 48, 128].forEach(size => {
  const png = createPng(size, size, drawIcon);
  fs.writeFileSync(path.join(iconsDir, `icon${size}.png`), png);
  console.log(`Generated icon${size}.png (${png.length} bytes)`);
});
