import { motion } from "framer-motion";
import { BookOpen, Users, Star, ArrowLeft, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
import { useTopInstructors } from "@/hooks/useApi";
import SectionEmpty from "./SectionEmpty";

const TopInstructorsSection = () => {
  const { data, isLoading } = useTopInstructors(1);
  const instructors = data?.data || [];

  const top = instructors[0];

  const instructor = top
    ? {
        id: top.id,
        name: top.name || `${top.first_name} ${top.last_name}`,
        title: top.title || "مدرّب",
        specialty: top.skills?.[0] || "خبير",
        // No branded fallback portrait: a neutral initials avatar keeps the card
        // honest when the API has no image.
        image:
          top.image ||
          `https://ui-avatars.com/api/?name=${encodeURIComponent(
            top.name || `${top.first_name} ${top.last_name}`
          )}&background=00847B&color=fff&size=512`,
        rating: top.rating || 0,
        students: top.total_students || 0,
        courses: top.total_courses || 0,
        bio: top.biography || "",
      }
    : null;

  if (isLoading) {
    return (
      <section className="py-20 md:py-24">
        <div className="flex items-center justify-center py-20">
          <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
        </div>
      </section>
    );
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
            نخبة المدرّبين
          </span>
          <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
            تعلّم على يد من مارس المهنة
          </h2>
          <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
            مدرّبونا ممارسون قبل أن يكونوا معلّمين — يشرحون من واقع مشاريع أنجزوها فعلاً.
          </p>
        </motion.div>

        {!instructor ? (
          <SectionEmpty
            illustration="mentor"
            message="لم يُعلن عن المدرّبين بعد. نختار حالياً نخبة من الممارسين لينضمّوا إلينا."
          />
        ) : (
        <motion.article
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
          className="card-tagdar p-6 md:p-8"
        >
          <div className="grid md:grid-cols-[minmax(0,320px)_1fr] gap-8 items-center">
            <div className="rounded-2xl overflow-hidden aspect-square w-full max-w-[320px] mx-auto bg-secondary">
              <img
                src={instructor.image}
                alt={`صورة المدرّب ${instructor.name}`}
                loading="lazy"
                className="w-full h-full object-cover"
              />
            </div>

            <div>
              <span className="inline-flex items-center px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-bold">
                {instructor.specialty}
              </span>

              <h3 className="mt-4 text-2xl md:text-3xl font-black text-navy">
                {instructor.name}
              </h3>
              <p className="mt-1 text-muted-foreground font-medium">{instructor.title}</p>

              {instructor.bio && (
                <p className="mt-4 text-muted-foreground leading-relaxed">
                  {instructor.bio}
                </p>
              )}

              <div className="mt-6 flex flex-wrap items-center gap-x-8 gap-y-4">
                <div>
                  <div className="flex items-center gap-2 text-navy">
                    <BookOpen className="w-4 h-4 text-primary" strokeWidth={1.75} aria-hidden="true" />
                    <span className="font-bold">{instructor.courses}</span>
                  </div>
                  <span className="text-sm text-muted-foreground">دورة تدريبية</span>
                </div>

                {instructor.students > 0 && (
                  <div>
                    <div className="flex items-center gap-2 text-navy">
                      <Users className="w-4 h-4 text-primary" strokeWidth={1.75} aria-hidden="true" />
                      <span className="font-bold">{instructor.students.toLocaleString("ar-EG")}</span>
                    </div>
                    <span className="text-sm text-muted-foreground">متدرّب</span>
                  </div>
                )}

                {instructor.rating > 0 && (
                  <div>
                    <div className="flex items-center gap-2 text-navy">
                      <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                      <span className="font-bold">{instructor.rating}</span>
                    </div>
                    <span className="text-sm text-muted-foreground">متوسط التقييم</span>
                  </div>
                )}
              </div>

              <Button asChild variant="hero" className="mt-7 group">
                <Link to={`/instructors/${instructor.id}`}>
                  عرض الملف الشخصي
                  <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
                </Link>
              </Button>
            </div>
          </div>
        </motion.article>
        )}
      </div>
    </section>
  );
};

export default TopInstructorsSection;
