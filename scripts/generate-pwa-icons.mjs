// توليد أيقونات توافق من هندسة علامة CARLED دون حفظ ملفات ثنائية في Git.
import { mkdirSync, writeFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
const crc = (data) => {
    let value = 0xffffffff;
    for (const byte of data) {
        value ^= byte;
        for (let bit = 0; bit < 8; bit++)
            value = (value >>> 1) ^ (value & 1 ? 0xedb88320 : 0);
    }
    return (value ^ 0xffffffff) >>> 0;
};
const chunk = (name, data) => {
    const type = Buffer.from(name);
    const size = Buffer.alloc(4);
    size.writeUInt32BE(data.length);
    const checksum = Buffer.alloc(4);
    checksum.writeUInt32BE(crc(Buffer.concat([type, data])));
    return Buffer.concat([size, type, data, checksum]);
};
const color = (x, y) => {
    if ((x - 383) ** 2 + (y - 349) ** 2 <= 18 ** 2) return [2, 6, 23];
    // مراكز قوسي C مشتقة من نقاط البداية والنهاية في public/carled.svg.
    const outerCenter = 391 - Math.sqrt(162 ** 2 - 82 ** 2);
    const innerCenter = 317 - Math.sqrt(96 ** 2 - 82 ** 2);
    const outer = (x - outerCenter) ** 2 + (y - 256) ** 2;
    const inner = (x - innerCenter) ** 2 + (y - 256) ** 2;
    if (
        outer <= 162 ** 2 &&
        inner >= 96 ** 2 &&
        !(x > innerCenter && y > 174 && y < 338)
    )
        return [248, 250, 252];
    return [0, 196, 180];
};
mkdirSync('public/icons', { recursive: true });
for (const size of [192, 512, 180]) {
    const pixels = Buffer.alloc(size * (size * 3 + 1));
    for (let y = 0; y < size; y++)
        for (let x = 0; x < size; x++) {
            const sums = [0, 0, 0];
            for (let dy = 0; dy < 4; dy++)
                for (let dx = 0; dx < 4; dx++) {
                    const rgb = color(
                        ((x + (dx + 0.5) / 4) * 512) / size,
                        ((y + (dy + 0.5) / 4) * 512) / size,
                    );
                    rgb.forEach((value, channel) => {
                        sums[channel] += value;
                    });
                }
            const offset = y * (size * 3 + 1) + 1 + x * 3;
            sums.forEach((value, channel) => {
                pixels[offset + channel] = Math.round(value / 16);
            });
        }
    const header = Buffer.alloc(13);
    header.writeUInt32BE(size, 0);
    header.writeUInt32BE(size, 4);
    header[8] = 8;
    header[9] = 2;
    writeFileSync(
        `public/icons/carled-${size}.png`,
        Buffer.concat([
            Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
            chunk('IHDR', header),
            chunk('IDAT', deflateSync(pixels)),
            chunk('IEND', Buffer.alloc(0)),
        ]),
    );
}
console.log('Generated Android and Apple touch icons (192, 512, 180).');
