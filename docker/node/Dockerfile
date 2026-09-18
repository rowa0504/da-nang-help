# syntax=docker/dockerfile:1

FROM node:22-alpine

# fontconfig + a font are required for scripts/generate-favicons.mjs — sharp
# (via librsvg) needs to resolve font-family names when rasterizing
# resources/branding/ogp.svg's <text> elements. Without a font installed,
# sharp does not error; it silently rasterizes glyphs as empty boxes.
RUN apk add --no-cache fontconfig ttf-dejavu
