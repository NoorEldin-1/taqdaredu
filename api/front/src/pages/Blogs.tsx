import { motion } from "framer-motion";
import { Calendar, ArrowLeft, ArrowRight, Clock, User, Search, Tag, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { useState } from "react";
import { Link } from "react-router-dom";
import { useBlogs, useBlogCategories } from "@/hooks/useApi";
import { blogUrl } from "@/lib/slugify";
import Seo from "@/components/Seo";

const Blogs = () => {
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [page, setPage] = useState(1);

  const { data: categoriesData } = useBlogCategories();
  const { data: blogsData, isLoading } = useBlogs(page, 10, selectedCategory || undefined);

  const categories = categoriesData?.data || [];
  const blogs = blogsData?.data || [];
  const pagination = blogsData?.pagination;

  // Filter by search query (client-side for now)
  const filteredBlogs = blogs.filter((blog) => {
    const matchesSearch = !searchQuery ||
      blog.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
      blog.excerpt?.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

  // An empty result means two different things to a visitor: nothing published
  // yet, or their filter excluded everything. Each gets its own illustration.
  const isFiltering = Boolean(searchQuery) || selectedCategory !== null;

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="المدوّنة"
        description="مقالات ونصائح مهنية يكتبها مدرّبو منصة تقدر لمن يريد أن يتقدّم خطوة كل أسبوع."
        canonical="/blogs"
      />
      <Navbar />

      <main className="pt-32 pb-20">
        <div className="container">
          {/* Header */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="max-w-2xl mb-12"
          >
            <span className="eyebrow">
              <span className="w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
              من المدوّنة
            </span>
            <h1 className="mt-3 text-4xl md:text-5xl font-black text-navy text-balance">
              مقالات تختصر عليك الطريق
            </h1>
            <p className="mt-4 text-lg text-muted-foreground leading-relaxed">
              خلاصات عملية ونصائح مهنية يكتبها مدرّبونا، لتقرأ ما ينفعك في وقتك المتاح.
            </p>
          </motion.div>

          {/* Search and Filter */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.15 }}
            className="flex flex-col lg:flex-row gap-4 mb-12"
          >
            <div className="relative lg:max-w-md lg:flex-1">
              <Search
                className="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted-foreground"
                strokeWidth={1.75}
                aria-hidden="true"
              />
              <Input
                type="search"
                placeholder="ابحث في المقالات..."
                aria-label="البحث في المقالات"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full ps-12 h-12"
              />
            </div>

            <div className="flex gap-2 flex-wrap lg:justify-end lg:flex-1">
              <Button
                variant={selectedCategory === null ? "hero" : "outline"}
                size="sm"
                onClick={() => { setSelectedCategory(null); setPage(1); }}
              >
                <Tag className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                جميع التصنيفات
              </Button>
              {categories.map((category) => (
                <Button
                  key={category.id}
                  variant={selectedCategory === category.id ? "hero" : "outline"}
                  size="sm"
                  onClick={() => { setSelectedCategory(category.id); setPage(1); }}
                >
                  <Tag className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                  {category.name}
                </Button>
              ))}
            </div>
          </motion.div>

          {/* Loading State */}
          {isLoading ? (
            <div className="flex items-center justify-center py-20">
              <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
            </div>
          ) : filteredBlogs.length === 0 ? (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="card-tagdar flex flex-col items-center justify-center gap-7 px-6 py-16 text-center border-dashed hover:shadow-none hover:translate-y-0"
            >
              <BrandIllustration
                name={isFiltering ? "noResults" : "writing"}
                className="w-64 max-w-full"
              />
              <p className="max-w-sm text-muted-foreground leading-relaxed">
                {isFiltering
                  ? "لا توجد مقالات مطابقة لبحثك. جرّب كلمات أخرى أو تصنيفاً مختلفاً."
                  : "لم تُنشر مقالات بعد. نعدّ الآن محتوى يستحق وقتك."}
              </p>
            </motion.div>
          ) : (
            <>
              {/* Blog Grid */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {filteredBlogs.map((blog, index) => (
                  <motion.article
                    key={blog.id}
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.45, delay: index * 0.08 }}
                    className="card-tagdar overflow-hidden group flex flex-col"
                  >
                    <Link
                      to={blogUrl(blog.id, blog.title)}
                      className="flex flex-col flex-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 rounded-2xl"
                    >
                      <div className="relative overflow-hidden bg-secondary">
                        <img
                          src={blog.thumbnail || blog.image_url || "https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=800&h=500&fit=crop"}
                          alt={blog.title}
                          loading="lazy"
                          className="w-full h-52 object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <span className="absolute top-4 start-4 px-3 py-1 rounded-full bg-card text-primary text-xs font-bold border border-border">
                          {blog.category?.name || "مقال"}
                        </span>
                      </div>

                      <div className="p-6 flex flex-1 flex-col">
                        <div className="flex items-center gap-4 text-sm text-muted-foreground mb-3">
                          <span className="flex items-center gap-1.5">
                            <Calendar className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                            <time dateTime={blog.created_at}>
                              {new Date(blog.created_at).toLocaleDateString('ar-EG', { month: 'long', day: 'numeric', year: 'numeric' })}
                            </time>
                          </span>
                          <span className="flex items-center gap-1.5">
                            <Clock className="w-4 h-4" strokeWidth={1.75} aria-hidden="true" />
                            {blog.read_time || "٥ دقائق قراءة"}
                          </span>
                        </div>

                        <h2 className="text-xl font-bold text-navy line-clamp-2 group-hover:text-primary transition-colors">
                          {blog.title}
                        </h2>

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

              {/* Pagination */}
              {pagination && pagination.total_pages > 1 && (
                <nav className="flex justify-center items-center gap-3 mt-12" aria-label="تصفّح صفحات المقالات">
                  <Button
                    variant="outline"
                    disabled={page === 1}
                    onClick={() => setPage(page - 1)}
                  >
                    <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    السابق
                  </Button>
                  <span className="flex items-center px-4 text-muted-foreground">
                    الصفحة {page.toLocaleString("ar-EG")} من {pagination.total_pages.toLocaleString("ar-EG")}
                  </span>
                  <Button
                    variant="outline"
                    disabled={page === pagination.total_pages}
                    onClick={() => setPage(page + 1)}
                  >
                    التالي
                    <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                  </Button>
                </nav>
              )}
            </>
          )}
        </div>
      </main>

      <Footer />
    </div>
  );
};

export default Blogs;
