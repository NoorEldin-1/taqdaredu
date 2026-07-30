import { motion } from "framer-motion";
import { Loader2, ArrowLeft } from "lucide-react";
import { Link } from "react-router-dom";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { useMyCourses } from "@/hooks/useApi";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import BrandIllustration from "@/components/BrandIllustration";
import Seo from "@/components/Seo";

const MyCourses = () => {
  const { isAuthenticated } = useAuth();
  const { data, isLoading } = useMyCourses();
  const courses = data?.data || [];

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-secondary flex flex-col">
        <Seo title="دوراتي" noindex />
        <Navbar />
        <main className="flex-1 flex items-center py-14 pt-32">
          <div className="container">
            <div className="card-tagdar mx-auto flex max-w-xl flex-col items-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="profile" className="w-56 max-w-full" />
              <div>
                <h1 className="text-2xl font-black text-navy">دوراتك بانتظارك</h1>
                <p className="mt-3 max-w-sm text-muted-foreground leading-relaxed">
                  سجّل دخولك لتتابع تقدّمك وتكمل الدروس من حيث توقفت.
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
        title="دوراتي"
        description="تابع تقدّمك في الدورات المسجّل بها وأكمل من حيث توقفت."
        noindex
      />
      <Navbar />
      <main className="relative z-10 pt-32 pb-20">
        <div className="container">
          <span className="eyebrow">
            <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
            مساحتك التعليمية
          </span>
          <h1 className="mt-3 mb-8 text-3xl md:text-4xl font-black text-navy text-balance">
            دوراتي
          </h1>
          {isLoading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
            </div>
          ) : courses.length === 0 ? (
            <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="catalogue" className="w-64 max-w-full" />
              <div>
                <h2 className="text-xl font-bold text-navy">لم تبدأ أي دورة بعد</h2>
                <p className="mx-auto mt-3 max-w-sm text-muted-foreground leading-relaxed">
                  اختر مساراً يناسب هدفك، وسيظهر هنا مع تقدّمك في كل درس تُنهيه.
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
                    {/* Progress leads the card here — on this page the question is
                        "how far am I?", not "what is this course?". */}
                    <div className="mt-auto pt-5">
                      <Progress
                        value={course.progress || 0}
                        className="h-2"
                        aria-label={`نسبة إتمام دورة ${course.title}`}
                      />
                      <p className="mt-2 text-sm text-muted-foreground">
                        أنجزت <span className="font-bold text-navy tabular-nums">{course.progress || 0}%</span> من الدورة
                      </p>
                      <Link to={`/courses/${course.id}/watch`}>
                        <Button className="w-full mt-4">
                          متابعة التعلّم
                          <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                        </Button>
                      </Link>
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

export default MyCourses;
