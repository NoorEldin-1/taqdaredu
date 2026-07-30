// PHP-parity slugify — mirrors api/application/helpers/common_helper.php:
//   preg_replace('~[^\pL\d]+~u', '-', $text) → trim('-') → strtolower
// Must run on the RAW API title (entities NOT decoded) so "&amp;" produces the
// same "-amp-" token as the server-side slugs in canonicals and sitemap.xml.
export const slugify = (text: string): string => {
  if (!text) return "n-a";
  const slug = text
    .replace(/[^\p{L}\d]+/gu, "-")
    .replace(/^-+|-+$/g, "")
    .toLowerCase();
  return slug || "n-a";
};

// Canonical SEO-friendly course URL: /courses/<id>-<slug>. Mirrors the server
// canonical (Seo.php course_detail) and the CourseDetails page canonical, so
// internal links point straight at the indexable URL (no id-only → slug redirect).
export const courseUrl = (id: number | string, title?: string): string =>
  `/courses/${id}${title ? "-" + slugify(title) : ""}`;

// Canonical SEO-friendly blog URL: /blogs/<id>-<slug>. Mirrors the server
// canonical (Seo.php blog_detail) and the BlogDetails page canonical.
export const blogUrl = (id: number | string, title?: string): string =>
  `/blogs/${id}${title ? "-" + slugify(title) : ""}`;
