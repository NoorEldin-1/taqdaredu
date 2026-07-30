import { useParams, Link } from "react-router-dom";
import { motion } from "framer-motion";
import { Star, Users, BookOpen, Award, ArrowRight, ArrowLeft, Linkedin, Twitter, Globe2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { useInstructor } from "@/hooks/useApi";
import { courseUrl } from "@/lib/slugify";
import { useCurrency } from "@/lib/currency";
import Seo from "@/components/Seo";

const InstructorDetails = () => {
  const { id } = useParams();
  const { data: instructorData, isLoading } = useInstructor(parseInt(String(id), 10) || 0);
  const { formatFree } = useCurrency();
  const instructor = instructorData?.data;

  if (isLoading) {
    return (
      <div className="min-h-screen bg-secondary">
        <Navbar />
        <section className="pt-32 pb-12">
          <div className="container">
            <div className="grid lg:grid-cols-3 gap-12">
              <div>
                <Skeleton className="w-full aspect-square rounded-3xl mb-6" />
                <Skeleton className="h-48 rounded-2xl" />
              </div>
              <div className="lg:col-span-2 space-y-8">
                <div>
                  <Skeleton className="h-8 w-48 mb-4" />
                  <Skeleton className="h-12 w-96 mb-2" />
                  <Skeleton className="h-6 w-32" />
                </div>
                <Skeleton className="h-64 rounded-2xl" />
                <Skeleton className="h-48 rounded-2xl" />
              </div>
            </div>
          </div>
        </section>
        <Footer />
      </div>
    );
  }

  if (!instructor) {
    return (
      <div className="min-h-screen bg-secondary">
        <Navbar />
        <section className="pt-32 pb-12">
          <div className="container max-w-2xl">
            <div className="card-tagdar flex flex-col items-center justify-center gap-7 px-6 py-16 text-center border-dashed hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="mentor" className="w-64 max-w-full" />
              <h1 className="text-2xl font-bold text-navy">لم نعثر على هذا المدرّب</h1>
              <p className="max-w-sm text-muted-foreground leading-relaxed">
                ربما تغيّر الرابط أو لم يعُد هذا الملف متاحاً.
              </p>
              <Button asChild variant="hero" className="group">
                <Link to="/">
                  العودة إلى الصفحة الرئيسية
                  <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                </Link>
              </Button>
            </div>
          </div>
        </section>
        <Footer />
      </div>
    );
  }

  const instructorName = instructor.name || `${instructor.first_name} ${instructor.last_name}`;

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title={instructorName}
        description={instructor.biography ? String(instructor.biography).slice(0, 160) : `الملف الشخصي ودورات ${instructorName} على منصة تقدر.`}
        canonical={`/instructors/${parseInt(String(id), 10)}`}
        type="profile"
      />

      <Navbar />

      <main className="pt-32 pb-20">
        <div className="container">
          {/* Breadcrumb */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="mb-8"
          >
            <Link to="/" className="inline-flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors">
              <ArrowRight className="w-4 h-4" aria-hidden="true" />
              العودة إلى الصفحة الرئيسية
            </Link>
          </motion.div>

          <div className="grid lg:grid-cols-3 gap-12">
            {/* Right column (RTL): portrait & quick info */}
            <motion.div
              initial={{ opacity: 0, x: -30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
            >
              <div className="sticky top-32">
                {/* Profile Image */}
                <div className="rounded-3xl overflow-hidden mb-6 bg-card border border-border">
                  <img
                    src={instructor.image || `https://ui-avatars.com/api/?name=${encodeURIComponent(instructorName)}&size=400&background=00847B&color=fff`}
                    alt={`صورة المدرّب ${instructorName}`}
                    className="w-full aspect-square object-cover"
                  />
                </div>

                {/* Quick Stats Card — only show stats that have a real value */}
                {(() => {
                  const stats = [
                    Number(instructor.total_courses) > 0 && { icon: <BookOpen className="w-4 h-4 text-primary" strokeWidth={1.75} aria-hidden="true" />, value: instructor.total_courses, label: "دورة تدريبية" },
                    Number(instructor.total_students) > 0 && { icon: <Users className="w-4 h-4 text-primary" strokeWidth={1.75} aria-hidden="true" />, value: Number(instructor.total_students).toLocaleString("ar-EG"), label: "متدرّب" },
                    Number(instructor.rating) > 0 && { icon: <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />, value: instructor.rating, label: "متوسط التقييم" },
                  ].filter(Boolean) as { icon: JSX.Element; value: React.ReactNode; label: string }[];
                  if (stats.length === 0) return null;
                  return (
                <div className="card-tagdar p-6">
                  <div className={`grid gap-4 text-center ${stats.length === 1 ? "grid-cols-1" : stats.length === 2 ? "grid-cols-2" : "grid-cols-3"}`}>
                    {stats.map((s) => (
                      <div key={s.label}>
                        <div className="flex items-center justify-center gap-1 text-navy mb-1">
                          {s.icon}
                          <span className="font-bold">{s.value}</span>
                        </div>
                        <span className="text-muted-foreground text-xs">{s.label}</span>
                      </div>
                    ))}
                  </div>

                  {instructor.skills && instructor.skills.length > 0 && (
                    <div className="border-t border-border mt-4 pt-4">
                      <div className="flex items-center gap-3 text-muted-foreground">
                        <Award className="w-4 h-4 text-primary" strokeWidth={1.75} aria-hidden="true" />
                        <span className="text-sm">{instructor.skills.slice(0, 3).join("، ")}</span>
                      </div>
                    </div>
                  )}

                  {/* Social Links */}
                  {instructor.social_links && (
                    <div className="flex items-center justify-center gap-3 mt-4 pt-4 border-t border-border">
                      {instructor.social_links.linkedin && (
                        <a href={instructor.social_links.linkedin} target="_blank" rel="noopener noreferrer" aria-label={`حساب ${instructorName} على لينكدإن`} className="p-2 rounded-xl bg-secondary text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors">
                          <Linkedin className="w-5 h-5" strokeWidth={1.75} aria-hidden="true" />
                        </a>
                      )}
                      {instructor.social_links.twitter && (
                        <a href={instructor.social_links.twitter} target="_blank" rel="noopener noreferrer" aria-label={`حساب ${instructorName} على منصة إكس`} className="p-2 rounded-xl bg-secondary text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors">
                          <Twitter className="w-5 h-5" strokeWidth={1.75} aria-hidden="true" />
                        </a>
                      )}
                      {instructor.social_links.website && (
                        <a href={instructor.social_links.website} target="_blank" rel="noopener noreferrer" aria-label={`الموقع الإلكتروني لـ ${instructorName}`} className="p-2 rounded-xl bg-secondary text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors">
                          <Globe2 className="w-5 h-5" strokeWidth={1.75} aria-hidden="true" />
                        </a>
                      )}
                    </div>
                  )}
                </div>
                  );
                })()}
              </div>
            </motion.div>

            {/* Left column (RTL): details */}
            <motion.div
              initial={{ opacity: 0, x: 30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="lg:col-span-2 space-y-8"
            >
              {/* Name & Title */}
              <div>
                {instructor.title && (
                  <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 text-primary text-sm font-bold mb-4">
                    <Award className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                    {instructor.title}
                  </span>
                )}
                <h1 className="text-3xl md:text-4xl font-black text-navy mb-2 text-balance">
                  {instructorName}
                </h1>
                {instructor.total_reviews && (
                  <p className="text-muted-foreground">
                    {instructor.total_reviews.toLocaleString("ar-EG")} تقييماً
                  </p>
                )}
              </div>

              {/* Bio */}
              {instructor.biography && (
                <div className="card-tagdar p-6">
                  <h2 className="text-xl font-bold text-navy mb-4">نبذة تعريفية</h2>
                  <div className="text-muted-foreground leading-relaxed whitespace-pre-line">
                    {instructor.biography}
                  </div>
                </div>
              )}

              {/* Skills */}
              {instructor.skills && instructor.skills.length > 0 && (
                <div className="card-tagdar p-6">
                  <h2 className="text-xl font-bold text-navy mb-4">المهارات ومجالات الخبرة</h2>
                  <div className="flex flex-wrap gap-2">
                    {instructor.skills.map((skill, index) => (
                      <span
                        key={index}
                        className="px-4 py-2 rounded-full bg-primary/10 text-primary text-sm font-bold"
                      >
                        {skill}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {/* Courses */}
              {instructor.courses && instructor.courses.length > 0 && (
                <div>
                  <h2 className="text-xl font-bold text-navy mb-6">دورات {instructor.first_name}</h2>
                  <div className="grid md:grid-cols-2 gap-6">
                    {instructor.courses.map((course, index) => (
                      <motion.article
                        key={course.id}
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true }}
                        transition={{ delay: index * 0.08 }}
                        className="card-tagdar overflow-hidden group"
                      >
                        <Link
                          to={courseUrl(course.id, course.title)}
                          className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-2xl"
                        >
                          <div className="h-40 overflow-hidden bg-secondary">
                            <img
                              src={course.thumbnail || course.image_url || "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=400&h=250&fit=crop"}
                              alt={course.title}
                              loading="lazy"
                              className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                          </div>
                          <div className="p-4">
                            <h3 className="font-bold text-navy group-hover:text-primary transition-colors line-clamp-2 mb-2">
                              {course.title}
                            </h3>
                            <div className="flex items-center justify-between">
                              <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                                {course.rating || 0}
                                <span>({course.total_students?.toLocaleString("ar-EG") || 0})</span>
                              </span>
                              <span className="text-primary font-bold tabular-nums">
                                {formatFree(course.discounted_price || course.price, course.is_free)}
                              </span>
                            </div>
                          </div>
                        </Link>
                      </motion.article>
                    ))}
                  </div>
                </div>
              )}

              {/* CTA */}
              <div className="card-tagdar p-8 text-center hover:shadow-[var(--shadow-sm)] hover:translate-y-0">
                <h2 className="text-xl font-bold text-navy mb-2">هل أنت مستعد للتعلّم مع {instructor.first_name}؟</h2>
                <p className="text-muted-foreground mb-6">اختر دورة تناسب هدفك وابدأ رحلتك اليوم.</p>
                <Button asChild variant="hero" size="lg" className="group">
                  <Link to="/courses">
                    تصفّح جميع الدورات
                    <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                  </Link>
                </Button>
              </div>
            </motion.div>
          </div>
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default InstructorDetails;
