import { motion } from "framer-motion";
import { Calendar, ArrowLeft, Clock, User, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
import { useBlogs } from "@/hooks/useApi";
import { blogUrl } from "@/lib/slugify";
import SectionEmpty from "./SectionEmpty";

const BlogsSection = () => {
  const { data, isLoading, error } = useBlogs(1, 3);

  const blogs = data?.data || [];

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
              من المدوّنة
            </span>
            <h2 className="mt-3 text-3xl md:text-4xl font-black text-navy text-balance">
              مقالات تختصر عليك الطريق
            </h2>
            <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
              خلاصات عملية ونصائح مهنية يكتبها مدرّبونا لمن يريد أن يتقدّم خطوة كل أسبوع.
            </p>
          </div>
          <Button asChild variant="outline" size="lg" className="shrink-0 group">
            <Link to="/blogs">
              كل المقالات
              <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" />
            </Link>
          </Button>
        </motion.div>

        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
          </div>
        ) : error ? (
          <div className="text-center py-20 text-muted-foreground">
            تعذّر تحميل المقالات. يُرجى المحاولة مرة أخرى لاحقاً.
          </div>
        ) : blogs.length === 0 ? (
          <SectionEmpty
            illustration="writing"
            message="لم تُنشر مقالات بعد. نعدّ الآن محتوى يستحق وقتك."
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {blogs.map((blog, index) => (
              <motion.article
                key={blog.id}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.45, delay: index * 0.08 }}
                className="card-tagdar overflow-hidden group"
              >
                <Link
                  to={blogUrl(blog.id, blog.title)}
                  className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-2xl"
                >
                  <div className="relative overflow-hidden bg-secondary">
                    <img
                      src={blog.thumbnail || blog.image_url || "https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=400&h=250&fit=crop"}
                      alt={blog.title}
                      loading="lazy"
                      className="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                    <span className="absolute top-4 right-4 px-3 py-1 rounded-full bg-card text-primary text-xs font-bold border border-border">
                      {blog.category?.name || "مقال"}
                    </span>
                  </div>

                  <div className="p-6">
                    <div className="flex items-center gap-4 text-sm text-muted-foreground mb-3">
                      <span className="flex items-center gap-1.5">
                        <Calendar className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                        {new Date(blog.created_at).toLocaleDateString('ar-EG', { month: 'long', day: 'numeric', year: 'numeric' })}
                      </span>
                      <span className="flex items-center gap-1.5">
                        <Clock className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                        {blog.read_time || "٥ دقائق قراءة"}
                      </span>
                    </div>

                    <h3 className="text-xl font-bold text-navy line-clamp-2 group-hover:text-primary transition-colors">
                      {blog.title}
                    </h3>

                    <p className="mt-2 text-muted-foreground line-clamp-2 leading-relaxed">
                      {blog.excerpt}
                    </p>

                    <div className="mt-5 pt-4 border-t border-border flex items-center justify-between">
                      <span className="flex items-center gap-2 text-sm text-muted-foreground">
                        <User className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                        {blog.author?.name || "فريق تقدر"}
                      </span>
                      <span className="text-primary font-bold text-sm flex items-center gap-1">
                        اقرأ المزيد
                        <ArrowLeft className="w-4 h-4 group-hover:-translate-x-1 transition-transform" aria-hidden="true" />
                      </span>
                    </div>
                  </div>
                </Link>
              </motion.article>
            ))}
          </div>
        )}
      </div>
    </section>
  );
};

export default BlogsSection;
