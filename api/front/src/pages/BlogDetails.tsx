import { useParams, Link, useNavigate } from "react-router-dom";
import { useMemo, useEffect } from "react";
import { motion } from "framer-motion";
import { Calendar, Clock, User, ArrowRight, Loader2 } from "lucide-react";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { useBlog } from "@/hooks/useApi";
import Seo from "@/components/Seo";
import { slugify, blogUrl } from "@/lib/slugify";

/**
 * Blog content is authored in a rich-text editor and is often pasted from
 * Google Docs, which injects junk: inline `style="…!important"` (cramped
 * line-heights, broken font-family, fixed colors), plus `data-path-to-node`
 * attributes. Those inline !important styles can't be overridden by our CSS,
 * so we strip them and let the `prose` typography below render clean,
 * readable text on the light card.
 */
function sanitizeBlogHtml(html: string): string {
  if (!html) return "";
  if (typeof window === "undefined" || !window.DOMParser) return html;
  try {
    const doc = new DOMParser().parseFromString(html, "text/html");
    doc.querySelectorAll("*").forEach((el) => {
      el.removeAttribute("style");
      el.removeAttribute("class");
      el.removeAttribute("align");
      Array.from(el.attributes).forEach((attr) => {
        if (attr.name.startsWith("data-")) el.removeAttribute(attr.name);
      });
    });
    return doc.body.innerHTML;
  } catch {
    return html;
  }
}

const BlogDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  // Route param may be a slug ("1-wireless-…"); parseInt grabs the leading id (Number() → NaN for slugs).
  const blogId = parseInt(String(id), 10);
  const { data, isLoading } = useBlog(blogId);
  const blog = data?.data;

  // Keep the address bar on /blogs/<id>-<slug>: if the visitor arrived via an
  // id-only link, replace it with the slug URL (matches the indexable canonical).
  useEffect(() => {
    if (!isNaN(blogId) && blog?.title) {
      const canonical = blogUrl(blogId, blog.title);
      if (window.location.pathname !== canonical) {
        navigate(canonical, { replace: true });
      }
    }
  }, [blogId, blog?.title, navigate]);
  const cleanContent = useMemo(
    () => sanitizeBlogHtml(blog?.content || blog?.excerpt || ""),
    [blog]
  );

  if (isLoading) {
    return (
      <div className="min-h-screen bg-secondary flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-secondary">
      {blog && (
        <Seo
          title={blog.title}
          description={blog.short_description || blog.excerpt}
          canonical={`/blogs/${parseInt(String(id), 10)}-${slugify(blog.title)}`}
          image={blog.thumbnail || blog.image_url}
          type="article"
        />
      )}
      <Navbar />
      <main className="pt-32 pb-20">
        <div className="container max-w-4xl">
          <Link
            to="/blogs"
            className="inline-flex items-center gap-2 text-primary font-bold mb-6 hover:underline"
          >
            <ArrowRight className="w-4 h-4" aria-hidden="true" />
            العودة إلى المدوّنة
          </Link>
          {blog && (
            <motion.article initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
              <img
                src={blog.thumbnail || blog.image_url}
                alt={blog.title}
                className="w-full h-auto object-contain rounded-2xl mb-8 bg-card border border-border"
              />
              <div className="flex flex-wrap items-center gap-4 text-muted-foreground mb-4">
                <span className="flex items-center gap-1.5">
                  <Calendar className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                  <time dateTime={blog.created_at}>
                    {new Date(blog.created_at).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' })}
                  </time>
                </span>
                <span className="flex items-center gap-1.5">
                  <Clock className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                  {blog.read_time || "٥ دقائق قراءة"}
                </span>
                <span className="flex items-center gap-1.5">
                  <User className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                  {blog.author?.name || "فريق تقدر"}
                </span>
              </div>
              <h1 className="text-3xl md:text-4xl font-black text-navy mb-8 text-balance">
                {blog.title}
              </h1>
              {/* The API returns editor HTML we don't control, so every element it
                  can emit is styled here rather than in the markup. */}
              <div
                className="card-tagdar p-6 md:p-10 hover:shadow-[var(--shadow-sm)] hover:translate-y-0
                  prose prose-lg max-w-none text-foreground
                  prose-headings:text-navy prose-headings:font-bold prose-headings:text-balance
                  prose-p:text-foreground prose-p:leading-loose
                  prose-li:text-foreground prose-li:leading-loose
                  prose-strong:text-navy prose-strong:font-bold
                  prose-a:text-primary prose-a:font-semibold prose-a:underline-offset-4
                  prose-blockquote:not-italic prose-blockquote:border-primary
                  prose-blockquote:bg-secondary prose-blockquote:rounded-xl
                  prose-blockquote:py-2 prose-blockquote:px-5 prose-blockquote:text-muted-foreground
                  prose-code:text-primary prose-code:bg-secondary prose-code:rounded-md
                  prose-code:px-1.5 prose-code:py-0.5 prose-code:font-normal
                  prose-code:before:content-none prose-code:after:content-none
                  prose-pre:bg-navy prose-pre:text-secondary prose-pre:rounded-xl
                  prose-img:rounded-xl prose-img:max-w-full prose-img:mx-auto
                  prose-hr:border-border
                  prose-th:text-navy prose-td:text-foreground"
                dangerouslySetInnerHTML={{ __html: cleanContent }}
              />
            </motion.article>
          )}
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default BlogDetails;
