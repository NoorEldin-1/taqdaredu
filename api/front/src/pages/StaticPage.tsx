import { useParams, useLocation } from "react-router-dom";
import { motion } from "framer-motion";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { usePage } from "@/hooks/useApi";
import { Loader2 } from "lucide-react";
import BrandIllustration from "@/components/BrandIllustration";
import Seo from "@/components/Seo";

const pageTitles: Record<string, string> = {
  "privacy-policy": "سياسة الخصوصية",
  "terms": "الشروط والأحكام",
  "refund-policy": "سياسة الاسترداد",
  "faq": "الأسئلة الشائعة",
};

const pageDescriptions: Record<string, string> = {
  "privacy-policy": "كيف نجمع بياناتك ونستخدمها ونحميها داخل منصة تقدر.",
  "terms": "الشروط التي تحكم استخدامك لمنصة تقدر والاشتراك في دوراتها.",
  "refund-policy": "متى يحق لك استرداد قيمة الدورة وكيف تقدّم الطلب.",
  "faq": "إجابات مباشرة عن أكثر ما يسأل عنه المتدرّبون قبل البدء.",
};

/**
 * The body arrives as HTML authored in the admin panel, so its markup cannot be
 * controlled from here. The prose overrides below are what keep an arbitrary
 * blob (headings, lists, tables, links) legible on the light card.
 */
const proseClasses = [
  "prose prose-lg max-w-none",
  "prose-headings:text-navy prose-headings:font-black prose-headings:text-balance",
  "prose-p:text-foreground prose-p:leading-relaxed",
  "prose-li:text-foreground prose-li:marker:text-primary",
  "prose-strong:text-navy prose-strong:font-bold",
  "prose-a:text-primary prose-a:font-medium prose-a:no-underline hover:prose-a:underline",
  "prose-blockquote:border-s-4 prose-blockquote:border-e-0 prose-blockquote:border-primary prose-blockquote:text-muted-foreground prose-blockquote:not-italic",
  "prose-hr:border-border",
  "prose-img:rounded-xl",
  "prose-table:text-foreground prose-th:text-navy prose-th:font-bold prose-td:border-border prose-th:border-border",
].join(" ");

const StaticPage = () => {
  const { slug: paramSlug } = useParams();
  const location = useLocation();
  const slug = paramSlug || location.pathname.replace(/^\//, "");
  const { data, isLoading } = usePage(slug);
  const page = data?.data;
  const title = pageTitles[slug || ""] || slug?.replace(/-/g, " ").replace(/\b\w/g, c => c.toUpperCase()) || "صفحة";

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title={page?.title || title}
        description={pageDescriptions[slug || ""]}
        canonical={`/${slug}`}
      />
      <Navbar />
      <main className="relative z-10 pt-32 pb-20">
        <div className="container max-w-4xl mx-auto px-4">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
          >
            <span className="eyebrow">
              <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
              معلومات المنصة
            </span>
            <h1 className="mt-3 mb-8 text-3xl md:text-4xl font-black text-navy text-balance">
              {page?.title || title}
            </h1>
            {isLoading ? (
              <div className="flex justify-center py-20">
                <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
              </div>
            ) : page?.content ? (
              <div className="card-tagdar p-7 sm:p-10 hover:shadow-[var(--shadow-sm)] hover:translate-y-0">
                <div
                  className={proseClasses}
                  dangerouslySetInnerHTML={{ __html: page.content }}
                />
              </div>
            ) : (
              <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
                <BrandIllustration name="writing" className="w-64 max-w-full" />
                <p className="max-w-sm text-muted-foreground leading-relaxed">
                  ما زلنا نُعدّ محتوى هذه الصفحة. عد إلينا بعد قليل، أو تواصل معنا إذا كنت تحتاج
                  إلى التفاصيل الآن.
                </p>
              </div>
            )}
          </motion.div>
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default StaticPage;
