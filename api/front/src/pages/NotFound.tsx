import { useLocation, Link } from "react-router-dom";
import { useEffect } from "react";
import { motion } from "framer-motion";
import { ArrowLeft, Search } from "lucide-react";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { Button } from "@/components/ui/button";
import Seo from "@/components/Seo";

const NotFound = () => {
  const location = useLocation();

  useEffect(() => {
    console.error("404 Error: User attempted to access non-existent route:", location.pathname);
  }, [location.pathname]);

  return (
    <div className="min-h-screen bg-secondary flex flex-col">
      {/* A missing URL must never enter the index, whichever path produced it. */}
      <Seo
        title="الصفحة غير موجودة"
        description="لم نعثر على الصفحة المطلوبة. عد إلى الصفحة الرئيسية أو تصفّح الدورات المتاحة على تقدر."
        noindex
      />
      <Navbar />

      <main className="flex-1 flex items-center py-14 pt-32">
        <div className="container">
          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="card-tagdar mx-auto flex max-w-2xl flex-col items-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0"
          >
            <BrandIllustration name="notFound" className="w-64 max-w-full" />

            <div>
              <span className="eyebrow">
                <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
                خطأ ٤٠٤
              </span>
              <h1 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
                لم نجد هذه الصفحة
              </h1>
              <p className="mt-4 max-w-md text-muted-foreground leading-relaxed">
                يبدو أن الرابط الذي فتحته غير صحيح أو أن الصفحة نُقلت إلى مكان آخر. لا بأس — يمكنك
                العودة إلى البداية أو استكشاف ما هو متاح الآن.
              </p>
            </div>

            <div className="flex flex-col sm:flex-row items-center gap-3">
              <Link to="/">
                <Button size="lg">
                  العودة إلى الرئيسية
                  <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                </Button>
              </Link>
              <Link to="/courses">
                <Button variant="outline" size="lg">
                  <Search className="w-4 h-4" aria-hidden="true" />
                  تصفّح الدورات
                </Button>
              </Link>
            </div>
          </motion.div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default NotFound;
