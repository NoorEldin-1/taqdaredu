import { motion } from "framer-motion";
import { Star, Clock, ArrowLeft, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
import { useTopCourses } from "@/hooks/useApi";
import { courseUrl } from "@/lib/slugify";
import { useCurrency } from "@/lib/currency";
import SectionEmpty from "./SectionEmpty";

const FeaturedCourses = () => {
  const { data, isLoading, error } = useTopCourses(4);
  const { formatFree } = useCurrency();

  const courses = data?.data || [];

  return (
    <section className="py-20 md:py-24">
      <div className="container">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12"
        >
          <div className="max-w-2xl">
            <span className="eyebrow">
              <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
              الأعلى تقييماً
            </span>
            <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
              دورات اختارها المتدرّبون قبلنا
            </h2>
            <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
              هذه ليست قائمة نحن من رتّبها، بل الدورات التي حصدت أعلى التقييمات من
              متدرّبين أنهوها فعلاً.
            </p>
          </div>

          <Button asChild variant="outline" size="lg" className="shrink-0 group">
            <Link to="/courses">
              عرض كل الدورات
              <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            </Link>
          </Button>
        </motion.div>

        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="w-8 h-8 text-primary animate-spin" />
            <span className="sr-only">جارٍ تحميل الدورات</span>
          </div>
        ) : error ? (
          <div className="text-center py-20 text-muted-foreground">
            تعذّر تحميل الدورات في الوقت الحالي. يُرجى المحاولة بعد قليل.
          </div>
        ) : courses.length === 0 ? (
          <SectionEmpty
            illustration="catalogue"
            message="لم تُنشر دورات بعد. تابعنا — أول المسارات في طريقها إليك قريباً."
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            {courses.map((course, index) => (
              <motion.div
                key={course.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.45, delay: index * 0.08 }}
                className="h-full"
              >
                <Link
                  to={courseUrl(course.id, course.title)}
                  className="card-tagdar group flex h-full flex-col overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                  <div className="overflow-hidden bg-secondary">
                    <img
                      src={course.thumbnail || course.image_url || "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=400&h=250&fit=crop"}
                      alt={course.title}
                      loading="lazy"
                      className="w-full h-44 object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                  </div>

                  <div className="flex flex-1 flex-col p-6">
                    {course.category?.name && (
                      <span className="self-start rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                        {course.category.name}
                      </span>
                    )}

                    <h3 className="mt-3 font-bold text-navy leading-relaxed line-clamp-2 group-hover:text-primary transition-colors">
                      {course.title}
                    </h3>

                    <div className="mt-3 flex items-center gap-1.5 text-sm text-muted-foreground">
                      <Clock className="w-4 h-4" aria-hidden="true" />
                      {course.duration || "مدة مرنة"}
                    </div>

                    {/* wrapper carries mt-auto + spacing so the rule sits clear of the
                        text and every card's price row lines up at the same baseline */}
                    <div className="mt-auto pt-5">
                      <div className="flex items-center justify-between border-t border-border pt-4">
                        <div className="flex items-center gap-1">
                          {/* Star is the one place orange earns its keep outside a CTA:
                              it reads as a rating mark, not as decoration. */}
                          <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                          <span className="font-bold text-navy tabular-nums">
                            {course.rating || "جديدة"}
                          </span>
                        </div>
                        <div className="text-lg font-black text-primary tabular-nums">
                          {formatFree(course.discounted_price || course.price || 0, course.is_free)}
                        </div>
                      </div>
                    </div>
                  </div>
                </Link>
              </motion.div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
};

export default FeaturedCourses;
