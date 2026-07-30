import { motion } from "framer-motion";
import { Loader2, Trash2, ArrowLeft } from "lucide-react";
import { Link } from "react-router-dom";
import { courseUrl } from "@/lib/slugify";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { useWishlist, useToggleWishlist } from "@/hooks/useApi";
import { useAuth } from "@/contexts/AuthContext";
import { useCurrency } from "@/lib/currency";
import { Button } from "@/components/ui/button";
import BrandIllustration from "@/components/BrandIllustration";
import Seo from "@/components/Seo";

const Wishlist = () => {
  const { isAuthenticated } = useAuth();
  const { data, isLoading } = useWishlist();
  const toggleWishlist = useToggleWishlist();
  const { formatFree } = useCurrency();
  const courses = data?.data || [];

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-secondary flex flex-col">
        <Seo title="قائمة رغباتي" noindex />
        <Navbar />
        <main className="flex-1 flex items-center py-14 pt-32">
          <div className="container">
            <div className="card-tagdar mx-auto flex max-w-xl flex-col items-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="profile" className="w-56 max-w-full" />
              <div>
                <h1 className="text-2xl font-black text-navy">قائمتك محفوظة لك</h1>
                <p className="mt-3 max-w-sm text-muted-foreground leading-relaxed">
                  سجّل دخولك لتستعرض الدورات التي أضفتها إلى قائمة رغباتك.
                </p>
              </div>
              <Link to="/login">
                <Button size="lg">تسجيل الدخول</Button>
              </Link>
            </div>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="قائمة رغباتي"
        description="الدورات التي حفظتها للعودة إليها لاحقاً على منصة تقدر."
        noindex
      />
      <Navbar />
      <main className="relative z-10 pt-32 pb-20">
        <div className="container">
          <span className="eyebrow">
            <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
            محفوظاتك
          </span>
          <h1 className="mt-3 mb-8 text-3xl md:text-4xl font-black text-navy text-balance">
            قائمة رغباتي
          </h1>
          {isLoading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
            </div>
          ) : courses.length === 0 ? (
            <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="cart" className="w-64 max-w-full" />
              <div>
                <h2 className="text-xl font-bold text-navy">قائمتك فارغة حتى الآن</h2>
                <p className="mx-auto mt-3 max-w-sm text-muted-foreground leading-relaxed">
                  احفظ الدورات التي تلفت انتباهك أثناء التصفّح، لتجدها هنا حين تقرر البدء.
                </p>
              </div>
              <Link to="/courses">
                <Button size="lg">
                  تصفّح الدورات
                  <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                </Button>
              </Link>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              {courses.map((course) => (
                <motion.div
                  key={course.id}
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  className="card-tagdar flex flex-col overflow-hidden"
                >
                  <div className="bg-secondary">
                    <img
                      src={course.thumbnail || course.image_url}
                      alt={course.title}
                      loading="lazy"
                      className="w-full h-40 object-cover"
                    />
                  </div>
                  <div className="flex flex-1 flex-col p-6">
                    <h3 className="font-bold text-navy leading-relaxed line-clamp-2">
                      {course.title}
                    </h3>
                    <div className="mt-auto pt-5">
                      <p className="text-lg font-black text-primary tabular-nums">{formatFree(course.price, course.is_free)}</p>
                      <div className="mt-4 flex gap-2">
                        <Link to={courseUrl(course.id, course.title)} className="flex-1">
                          <Button className="w-full">عرض الدورة</Button>
                        </Link>
                        {/* Removal is destructive-ish but routine, so it stays a quiet
                            outline action rather than a red one. */}
                        <Button
                          variant="outline"
                          size="icon"
                          onClick={() => toggleWishlist.mutate(course.id)}
                          aria-label={`إزالة ${course.title} من قائمة الرغبات`}
                        >
                          <Trash2 className="w-4 h-4" aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </motion.div>
              ))}
            </div>
          )}
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default Wishlist;
