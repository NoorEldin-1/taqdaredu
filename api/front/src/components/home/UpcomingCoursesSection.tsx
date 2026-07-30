import { motion } from "framer-motion";
import { Clock, User, ArrowLeft, BookOpen, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
import { useLatestCourses } from "@/hooks/useApi";
import { courseUrl } from "@/lib/slugify";
import { stripHtml } from "@/lib/utils";

// The API returns levels as English slugs; map for display only, and fall back
// to the raw value so an unexpected level still renders rather than vanishing.
const levelLabels: Record<string, string> = {
  beginner: "مبتدئ",
  intermediate: "متوسط",
  advanced: "متقدّم",
  expert: "احترافي",
};

const UpcomingCoursesSection = () => {
  const { data, isLoading } = useLatestCourses(50);
  const apiCourses = (data?.data || [])
    .filter((course) => course.status === "upcoming")
    .slice(0, 3);

  const upcomingCourses = apiCourses.map((course) => ({
    id: course.id,
    title: course.title,
    description: stripHtml(course.short_description || course.description || ""),
    category: course.category?.name || "عام",
    instructor: course.instructor
      ? (course.instructor.name || `${course.instructor.first_name} ${course.instructor.last_name}`)
      : "مدرّب تقدر",
    startDate: course.start_date || course.created_at || "",
    duration: course.duration || "بإيقاعك الخاص",
    enrolledCount: course.total_students || 0,
    maxEnrollment: 500,
    image: course.thumbnail || course.thumbnail_url || course.image_url || "",
    tags: [
      ...(course.is_free ? ["مجانية"] : []),
      ...(course.is_featured ? ["مميّزة"] : []),
      ...(course.level ? [levelLabels[course.level.toLowerCase()] || course.level] : []),
    ].slice(0, 2),
  }));

  if (isLoading) {
    return (
      <section className="py-20 md:py-24">
        <div className="flex items-center justify-center py-20">
          <Loader2 className="w-8 h-8 text-primary animate-spin" />
          <span className="sr-only">جارٍ تحميل الدورات القادمة</span>
        </div>
      </section>
    );
  }

  if (upcomingCourses.length === 0) {
    return null;
  }

  return (
    <section className="py-20 md:py-24">
      <div className="container">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="max-w-2xl mb-12"
        >
          <span className="eyebrow">
            <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
            قريباً
          </span>
          <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
            دورات على وشك الانطلاق
          </h2>
          <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
            احجز مقعدك مبكراً في الدورات التي نجهّزها الآن، واستلم إشعاراً فور فتح
            باب التسجيل.
          </p>
        </motion.div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          {upcomingCourses.map((course, index) => (
            <motion.div
              key={course.id}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.45, delay: index * 0.1 }}
              className="h-full"
            >
              <Link
                to={courseUrl(course.id, course.title)}
                className="card-tagdar group flex h-full flex-col overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
              >
                <div className="relative h-44 overflow-hidden bg-secondary">
                  {course.image ? (
                    <img
                      src={course.image}
                      alt={course.title}
                      loading="lazy"
                      className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center">
                      <BookOpen className="w-10 h-10 text-muted-foreground/40" aria-hidden="true" />
                    </div>
                  )}
                </div>

                <div className="flex flex-1 flex-col p-6">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                      {course.category}
                    </span>
                    {course.tags.map((tag) => (
                      <span
                        key={tag}
                        className="rounded-full bg-secondary px-3 py-1 text-xs font-bold text-navy"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>

                  <h3 className="mt-4 text-lg font-bold text-navy leading-relaxed group-hover:text-primary transition-colors">
                    {course.title}
                  </h3>

                  {course.description && (
                    <p className="mt-2 text-muted-foreground leading-relaxed line-clamp-2">
                      {course.description}
                    </p>
                  )}

                  <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                      <User className="w-4 h-4" aria-hidden="true" />
                      {course.instructor}
                    </span>
                    <span className="flex items-center gap-1.5">
                      <Clock className="w-4 h-4" aria-hidden="true" />
                      {course.duration}
                    </span>
                  </div>

                  {/* span, not a nested button — the whole card is already the link */}
                  <span className="mt-auto pt-6 inline-flex items-center gap-2 text-sm font-bold text-primary">
                    عرض تفاصيل الدورة
                    <ArrowLeft
                      className="w-4 h-4 group-hover:-translate-x-1 transition-transform"
                      aria-hidden="true"
                    />
                  </span>
                </div>
              </Link>
            </motion.div>
          ))}
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.45, delay: 0.2 }}
          className="mt-12 text-center"
        >
          <Button asChild variant="outline" size="lg" className="group">
            <Link to="/courses">
              تصفّح كل الدورات القادمة
              <ArrowLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform" />
            </Link>
          </Button>
        </motion.div>
      </div>
    </section>
  );
};

export default UpcomingCoursesSection;
