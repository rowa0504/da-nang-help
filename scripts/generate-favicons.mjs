// Regenerates public/favicon.svg, public/favicon.ico,
// public/apple-touch-icon.png, and public/og-image.png from the source SVGs
// in resources/branding/. Run this after editing mark.svg or ogp.svg:
//
//   pnpm run generate-favicons
//
// resources/branding/*.svg are the only files that should be hand-edited —
// everything this script writes into public/ is a generated artifact.
//
// ogp.svg renders text via sharp's SVG rasterizer (librsvg), which needs a
// working fontconfig + at least one installed font to draw glyphs — without
// them the text silently rasterizes as empty boxes (no error is thrown).
// The project's `node` container image does not ship a font by default; if
// you see empty glyph boxes in public/og-image.png, install one before
// re-running (this does not need to be baked into the image permanently —
// a one-off `apk add fontconfig ttf-dejavu && fc-cache -f` in the same
// shell as this script is enough).

import { readFile, writeFile, copyFile } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import pngToIco from 'png-to-ico';

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const markSvgPath = resolve(rootDir, 'resources/branding/mark.svg');
const ogpSvgPath = resolve(rootDir, 'resources/branding/ogp.svg');
const publicDir = resolve(rootDir, 'public');

async function main() {
    const markSvg = await readFile(markSvgPath);
    const ogpSvg = await readFile(ogpSvgPath);

    // favicon.svg — modern browsers, crisp at any size.
    await copyFile(markSvgPath, resolve(publicDir, 'favicon.svg'));

    // favicon.ico — bundles 16/32/48px PNGs for older browsers/OS caches.
    const icoSizes = [16, 32, 48];
    const icoPngBuffers = await Promise.all(
        icoSizes.map((size) => sharp(markSvg, { density: 384 }).resize(size, size).png().toBuffer()),
    );
    const icoBuffer = await pngToIco(icoPngBuffers);
    await writeFile(resolve(publicDir, 'favicon.ico'), icoBuffer);

    // apple-touch-icon.png — iOS home screen icon.
    const appleTouchIcon = await sharp(markSvg, { density: 384 }).resize(180, 180).png().toBuffer();
    await writeFile(resolve(publicDir, 'apple-touch-icon.png'), appleTouchIcon);

    // og-image.png — social link preview (1200x630).
    const ogImage = await sharp(ogpSvg, { density: 96 }).resize(1200, 630).png().toBuffer();
    await writeFile(resolve(publicDir, 'og-image.png'), ogImage);

    console.log('Generated public/favicon.svg, public/favicon.ico, public/apple-touch-icon.png, public/og-image.png');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
