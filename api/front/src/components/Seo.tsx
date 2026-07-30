import { Helmet } from "react-helmet-async";

const SITE = "تقدر";
const TAGLINE = "منصة تعليمية تلهمك.. وتمكّنك";

/**
 * Canonical/OG URLs need an absolute origin. Resolve it from the host actually
 * serving the page rather than hardcoding a domain — a hardcoded one silently
 * points canonicals at the wrong site the moment the app is served elsewhere.
 * TODO(client): once the production domain is final, set VITE_SITE_ORIGIN so
 * that crawlers fetching pre-rendered HTML get an absolute URL too.
 */
const ORIGIN =
  import.meta.env.VITE_SITE_ORIGIN ??
  (typeof window !== "undefined" ? window.location.origin : "");

interface SeoProps {
  title?: string;
  description?: string;
  /** Path or absolute URL for the canonical link (defaults to current path). */
  canonical?: string;
  image?: string;
  type?: "website" | "article" | "profile";
  noindex?: boolean;
}

/**
 * Per-page <head> tags for the SPA — gives users correct tab titles and
 * provides meta/OG/Twitter/canonical to social crawlers and JS-rendering bots.
 * (Search engines also get full server-rendered HTML via the dynamic-rendering
 * layer, so this is a complementary client-side layer.)
 */
const Seo = ({ title, description, canonical, image, type = "website", noindex = false }: SeoProps) => {
  const fullTitle = title ? `${title} — ${SITE}` : `${SITE} | ${TAGLINE}`;
  const path = typeof window !== "undefined" ? window.location.pathname + window.location.search : "";
  const canon = canonical
    ? (canonical.startsWith("http") ? canonical : ORIGIN + canonical)
    : ORIGIN + path;
  const img = image || `${ORIGIN}/og-image.jpg`;

  return (
    <Helmet>
      <title>{fullTitle}</title>
      {description && <meta name="description" content={description} />}
      <link rel="canonical" href={canon} />
      {noindex && <meta name="robots" content="noindex, nofollow" />}

      <meta property="og:type" content={type} />
      <meta property="og:title" content={fullTitle} />
      {description && <meta property="og:description" content={description} />}
      <meta property="og:url" content={canon} />
      <meta property="og:site_name" content={SITE} />
      <meta property="og:image" content={img} />

      <meta name="twitter:card" content="summary_large_image" />
      <meta name="twitter:title" content={fullTitle} />
      {description && <meta name="twitter:description" content={description} />}
      <meta name="twitter:image" content={img} />
    </Helmet>
  );
};

export default Seo;
