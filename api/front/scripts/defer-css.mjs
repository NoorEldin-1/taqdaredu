// Post-build: convert the render-blocking Tailwind stylesheet into a non-blocking
// preload+swap link (with a <noscript> fallback). This shaves the CSS off the
// critical render path. Safe for this SPA because the shell is an empty
// <div id="root"></div> — there is no visible content to flash before the CSS loads.
//
// The script is idempotent and never fails the build: if the expected <link> is
// missing or already transformed, it logs and exits 0.
import { readFile, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const indexPath = resolve(__dirname, "..", "dist", "index.html");

try {
  let html = await readFile(indexPath, "utf8");

  // Idempotency: only skip if the VITE bundle stylesheet itself was already
  // deferred. (A generic 'rel="preload" as="style"' check false-positives on the
  // Google-Fonts preload that now lives in the source index.html.)
  if (/rel="preload" as="style"[^>]*href="\/_app\/[^"]+\.css"/i.test(html)) {
    console.log("[defer-css] already transformed, skipping.");
    process.exit(0);
  }

  // Matches the Vite-injected stylesheet, e.g.:
  //   <link rel="stylesheet" crossorigin href="/_app/index-XXXX.css">
  const re = /<link\s+rel="stylesheet"([^>]*?)\shref="(\/_app\/[^"]+\.css)"\s*\/?>/i;
  const m = html.match(re);
  if (!m) {
    console.log("[defer-css] no render-blocking stylesheet link found, skipping.");
    process.exit(0);
  }

  const extraAttrs = m[1].trim(); // e.g. "crossorigin"
  const href = m[2];
  const extra = extraAttrs ? " " + extraAttrs : "";
  const replacement =
    `<link rel="preload" as="style"${extra} href="${href}" onload="this.onload=null;this.rel='stylesheet'">` +
    `<noscript><link rel="stylesheet"${extra} href="${href}"></noscript>`;

  html = html.replace(re, replacement);
  await writeFile(indexPath, html, "utf8");
  console.log(`[defer-css] deferred ${href}`);
} catch (err) {
  console.error("[defer-css] non-fatal error:", err.message);
  process.exit(0); // never break the build over post-processing
}
