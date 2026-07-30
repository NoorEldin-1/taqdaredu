/**
 * Maps unDraw's stock palette onto the Tagdar identity.
 *
 * Run after dropping a freshly downloaded unDraw SVG into
 * src/assets/illustrations/, then optimise:
 *
 *   node scripts/recolor-illustrations.mjs
 *   npx svgo -f src/assets/illustrations --multipass
 *
 * The script is idempotent — brand hexes are not in the source map, so
 * re-running it on already-converted files is a no-op.
 *
 * Skin tones are deliberately absent from the map. Recolouring people is not
 * branding, and unDraw's figures are already diverse.
 *
 * Licence note: unDraw permits commercial use, modification and bundling into a
 * client site with no attribution. It does NOT permit redistributing the
 * illustrations as a pack — which is why these are committed as project assets
 * rather than pulled from a third-party npm mirror at build time.
 */
import { readFileSync, writeFileSync, readdirSync } from "node:fs";
import { join } from "node:path";

const DIR = "src/assets/illustrations";

const MAP = {
  // unDraw primary purple -> brand teal
  "#6c63ff": "#00847B",
  "#6C63FF": "#00847B",
  // violet-greys -> navy family
  "#3f3d56": "#16385C",
  "#2f2e41": "#0F2C4C",
  "#090814": "#0F2C4C",
  "#010001": "#0F2C4C",
  "#707070": "#16385C",
  // Neutral greys -> the brand's navy-biased slates. These deliberately keep
  // unDraw's original tonal steps: pushing them lighter made large fills
  // (clothing, panels) disappear against the white card they sit on.
  "#e6e6e6": "#DCE4EC",
  "#d6d6e3": "#C7D3E0",
  "#f2f2f2": "#EEF2F6",
  "#ccc": "#C7D3E0",
  "#cacaca": "#C7D3E0",
  "#e4e4e4": "#DCE4EC",
  // unDraw's secondary accent lands on the brand's spark orange, which keeps
  // the 10% accent rule working inside the illustrations too.
  "#ff6584": "#F29C38",
};

let changed = 0;

for (const file of readdirSync(DIR).filter((f) => f.endsWith(".svg"))) {
  const path = join(DIR, file);
  const original = readFileSync(path, "utf8");
  let svg = original;

  for (const [from, to] of Object.entries(MAP)) {
    svg = svg.split(from).join(to);
  }

  // unDraw ships fixed width/height attributes; strip them so the SVG scales to
  // whatever box the component gives it.
  svg = svg
    .replace(/<svg([^>]*?)\swidth="[^"]*"/, "<svg$1")
    .replace(/<svg([^>]*?)\sheight="[^"]*"/, "<svg$1");

  if (svg !== original) {
    writeFileSync(path, svg);
    changed++;
    const left = [...new Set(svg.match(/#[0-9a-fA-F]{3,6}/g) || [])].join(" ");
    console.log(`recoloured ${file} -> ${left}`);
  }
}

console.log(changed ? `\n${changed} file(s) updated.` : "\nNothing to do — all files already on-brand.");
