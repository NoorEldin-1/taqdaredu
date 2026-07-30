import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Link, useSearchParams } from "react-router-dom";
import { courseUrl } from "@/lib/slugify";
import {
  Search,
  SlidersHorizontal,
  Star,
  Clock,
  ChevronDown,
  Grid3X3,
  List,
  ArrowLeft,
  ArrowRight,
  Loader2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Slider } from "@/components/ui/slider";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import BrandIllustration from "@/components/BrandIllustration";
import { useCourses, useCategories } from "@/hooks/useApi";
import { useCurrency } from "@/lib/currency";
import Seo from "@/components/Seo";

const levels = ["beginner", "intermediate", "advanced"];

// The API stores levels as English slugs; translate for display only and fall
// back to the raw value so an unexpected level still renders.
const levelLabels: Record<string, string> = {
  beginner: "مبتدئ",
  intermediate: "متوسط",
  advanced: "متقدّم",
};

const sortOptions = [
  { value: "newest", label: "الأحدث" },
  { value: "popular", label: "الأكثر رواجاً" },
  { value: "price_low", label: "السعر: من الأقل إلى الأعلى" },
  { value: "price_high", label: "السعر: من الأعلى إلى الأقل" },
];

const MAX_PRICE = 1500;

const Courses = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [searchQuery, setSearchQuery] = useState(searchParams.get("search") || "");
  const [selectedCategory, setSelectedCategory] = useState<number | null>(
    searchParams.get("category") ? Number(searchParams.get("category")) : null
  );
  const [selectedLevels, setSelectedLevels] = useState<string[]>([]);
  const [priceRange, setPriceRange] = useState([0, MAX_PRICE]);
  const [showFilters, setShowFilters] = useState(true);
  const [viewMode, setViewMode] = useState<"grid" | "list">("grid");
  const [sortBy, setSortBy] = useState("newest");
  const [page, setPage] = useState(1);
  const { format, formatFree } = useCurrency();

  // Keep filters in sync with the URL query so navbar links (?category= / ?search=)
  // re-filter even when already on the Courses page.
  useEffect(() => {
    const cat = searchParams.get("category");
    setSelectedCategory(cat ? Number(cat) : null);
    setSearchQuery(searchParams.get("search") || "");
    setPage(1);
  }, [searchParams]);

  // API calls
  const { data: categoriesData } = useCategories();
  const { data: coursesData, isLoading } = useCourses({
    page,
    limit: 12,
    category_id: selectedCategory || undefined,
    search: searchQuery || undefined,
    level: selectedLevels.length === 1 ? selectedLevels[0] : undefined,
    sort: sortBy,
  });

  const rawCategories = categoriesData?.data || [];
  // Show the actual sub-categories instead of the generic parent
  const categories = rawCategories.flatMap((c: { id: number; name: string; sub_categories?: { id: number; name: string }[] }) =>
    Array.isArray(c.sub_categories) && c.sub_categories.length > 0 ? c.sub_categories : [{ id: c.id, name: c.name }]
  );
  const courses = coursesData?.data || [];
  const pagination = coursesData?.pagination;

  const toggleLevel = (level: string) => {
    setSelectedLevels((prev) =>
      prev.includes(level) ? prev.filter((l) => l !== level) : [...prev, level]
    );
  };

  const clearFilters = () => {
    setSelectedCategory(null);
    setSelectedLevels([]);
    setPriceRange([0, MAX_PRICE]);
    setSearchQuery("");
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setPage(1);
  };

  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        title="الدورات التدريبية"
        description="تصفّح دورات تقدر التدريبية واختر المسار الذي يناسب مستواك ووقتك، بشرحٍ عربي واضح من مدرّبين ممارسين."
        canonical="/courses"
      />
      <Navbar />

      <main>
        {/* Page header — the teal band is the brand's own header motif, and it
            gives the catalogue a top edge without a dark hero. */}
        <section className="relative bg-teal overflow-hidden">
          <div className="absolute inset-0 dot-pattern opacity-20 pointer-events-none" aria-hidden="true" />

          <div className="container relative pt-32 pb-24 md:pb-28">
            <motion.div
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6 }}
              className="max-w-2xl mx-auto text-center"
            >
              <h1 className="text-3xl md:text-4xl lg:text-5xl font-black text-white leading-[1.3] text-balance">
                اختر الدورة التي تنقلك خطوة للأمام
              </h1>
              <p className="mt-4 text-lg text-white/85 leading-relaxed">
                تصفّح المسارات المتاحة، وصفّها بما يناسب مستواك وميزانيتك، وابدأ
                من حيث أنت.
              </p>
            </motion.div>

            <motion.form
              onSubmit={handleSearch}
              role="search"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.15 }}
              className="max-w-2xl mx-auto mt-8"
            >
              <div className="relative">
                <Search
                  className="absolute start-4 top-1/2 w-5 h-5 -translate-y-1/2 text-muted-foreground"
                  aria-hidden="true"
                />
                <Input
                  type="search"
                  placeholder="ابحث عن دورة…"
                  aria-label="ابحث عن دورة"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="h-14 rounded-xl border-transparent bg-white ps-12 pe-4 text-base"
                />
              </div>
            </motion.form>
          </div>

          {/* Curved seam — the band resolves into the page instead of cutting it */}
          <svg
            className="absolute bottom-0 inset-x-0 w-full h-10 md:h-14 text-secondary"
            viewBox="0 0 1440 60"
            preserveAspectRatio="none"
            aria-hidden="true"
          >
            <path d="M0,60 C360,0 1080,0 1440,60 Z" fill="currentColor" />
          </svg>
        </section>

        <section className="py-12 md:py-16">
          <div className="container">
            <div className="flex gap-8">
              {/* Sidebar filters */}
              <AnimatePresence>
                {showFilters && (
                  <motion.aside
                    initial={{ opacity: 0, width: 0 }}
                    animate={{ opacity: 1, width: 280 }}
                    exit={{ opacity: 0, width: 0 }}
                    transition={{ duration: 0.3 }}
                    className="hidden lg:block shrink-0"
                    aria-label="تصفية الدورات"
                  >
                    <div className="card-tagdar sticky top-28 p-6 hover:shadow-none hover:translate-y-0">
                      <div className="flex items-center justify-between mb-6">
                        <h2 className="flex items-center gap-2 text-base font-bold text-navy">
                          <SlidersHorizontal className="w-5 h-5 text-primary" aria-hidden="true" />
                          التصفية
                        </h2>
                        <Button variant="ghost" size="sm" onClick={clearFilters}>
                          مسح الكل
                        </Button>
                      </div>

                      {/* Categories */}
                      <div className="mb-7">
                        <h3 className="mb-3 text-sm font-bold text-navy">التصنيف</h3>
                        <div className="space-y-1.5">
                          <button
                            type="button"
                            onClick={() => setSelectedCategory(null)}
                            className={`w-full rounded-xl px-4 py-2.5 text-start text-sm font-medium transition-colors ${
                              selectedCategory === null
                                ? "bg-primary text-white"
                                : "text-muted-foreground hover:bg-secondary hover:text-navy"
                            }`}
                          >
                            كل التصنيفات
                          </button>
                          {categories.map((category) => (
                            <button
                              type="button"
                              key={category.id}
                              onClick={() => setSelectedCategory(category.id)}
                              className={`w-full rounded-xl px-4 py-2.5 text-start text-sm font-medium transition-colors ${
                                selectedCategory === category.id
                                  ? "bg-primary text-white"
                                  : "text-muted-foreground hover:bg-secondary hover:text-navy"
                              }`}
                            >
                              {category.name}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Levels */}
                      <div className="mb-7">
                        <h3 className="mb-3 text-sm font-bold text-navy">المستوى</h3>
                        <div className="space-y-3">
                          {levels.map((level) => (
                            <label key={level} className="group flex cursor-pointer items-center gap-3">
                              <Checkbox
                                checked={selectedLevels.includes(level)}
                                onCheckedChange={() => toggleLevel(level)}
                              />
                              <span className="text-sm text-muted-foreground transition-colors group-hover:text-navy">
                                {levelLabels[level] || level}
                              </span>
                            </label>
                          ))}
                        </div>
                      </div>

                      {/* Price range */}
                      <div>
                        <h3 className="mb-3 text-sm font-bold text-navy">نطاق السعر</h3>
                        <Slider
                          dir="rtl"
                          value={priceRange}
                          onValueChange={setPriceRange}
                          max={MAX_PRICE}
                          step={10}
                          aria-label="نطاق السعر"
                          className="mb-3"
                        />
                        <div className="flex items-center justify-between text-sm text-muted-foreground tabular-nums">
                          <span>{format(priceRange[0])}</span>
                          <span>
                            {format(priceRange[1])}
                            {priceRange[1] >= MAX_PRICE ? "+" : ""}
                          </span>
                        </div>
                      </div>
                    </div>
                  </motion.aside>
                )}
              </AnimatePresence>

              {/* Course grid */}
              <div className="flex-1">
                {/* Toolbar */}
                <div className="mb-8 flex flex-wrap items-center justify-between gap-4">
                  <div className="flex items-center gap-4">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setShowFilters(!showFilters)}
                      className="hidden lg:flex"
                    >
                      <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
                      {showFilters ? "إخفاء التصفية" : "إظهار التصفية"}
                    </Button>
                    <p className="text-muted-foreground">
                      <span className="font-bold text-navy tabular-nums">
                        {pagination?.total || courses.length}
                      </span>{" "}
                      دورة متاحة
                    </p>
                  </div>

                  <div className="flex items-center gap-3">
                    {/* Sort */}
                    <div className="relative">
                      <select
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        aria-label="ترتيب الدورات"
                        className="appearance-none rounded-xl border border-border bg-card px-4 py-2 pe-10 text-sm text-navy focus:outline-none focus:ring-2 focus:ring-ring"
                      >
                        {sortOptions.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                      </select>
                      <ChevronDown
                        className="pointer-events-none absolute end-3 top-1/2 w-4 h-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                      />
                    </div>

                    {/* View toggle */}
                    <div className="flex items-center rounded-xl border border-border bg-card p-1">
                      <button
                        type="button"
                        onClick={() => setViewMode("grid")}
                        aria-label="عرض شبكي"
                        aria-pressed={viewMode === "grid"}
                        className={`rounded-lg p-2 transition-colors ${
                          viewMode === "grid" ? "bg-primary text-white" : "text-muted-foreground hover:text-navy"
                        }`}
                      >
                        <Grid3X3 className="w-4 h-4" aria-hidden="true" />
                      </button>
                      <button
                        type="button"
                        onClick={() => setViewMode("list")}
                        aria-label="عرض قائمة"
                        aria-pressed={viewMode === "list"}
                        className={`rounded-lg p-2 transition-colors ${
                          viewMode === "list" ? "bg-primary text-white" : "text-muted-foreground hover:text-navy"
                        }`}
                      >
                        <List className="w-4 h-4" aria-hidden="true" />
                      </button>
                    </div>
                  </div>
                </div>

                {isLoading ? (
                  <div className="flex items-center justify-center py-20">
                    <Loader2 className="w-8 h-8 text-primary animate-spin" />
                    <span className="sr-only">جارٍ تحميل الدورات</span>
                  </div>
                ) : courses.length === 0 ? (
                  <div className="card-tagdar flex flex-col items-center justify-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
                    <BrandIllustration name="noResults" className="w-64 max-w-full" />
                    <div>
                      <h2 className="text-xl font-bold text-navy">لا توجد دورات مطابقة</h2>
                      <p className="mt-2 max-w-sm text-muted-foreground leading-relaxed">
                        جرّب توسيع نطاق التصفية أو تعديل كلمات البحث.
                      </p>
                    </div>
                    <Button variant="outline" onClick={clearFilters}>
                      مسح عوامل التصفية
                    </Button>
                  </div>
                ) : (
                  <>
                    <motion.div
                      layout
                      className={
                        viewMode === "grid"
                          ? "grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5"
                          : "flex flex-col gap-5"
                      }
                    >
                      <AnimatePresence mode="popLayout">
                        {courses.map((course, index) => (
                          <motion.div
                            layout
                            key={course.id}
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -20 }}
                            transition={{ duration: 0.3, delay: index * 0.05 }}
                            className={viewMode === "grid" ? "h-full" : ""}
                          >
                            <Link
                              to={courseUrl(course.id, course.title)}
                              className={`card-tagdar group overflow-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
                                viewMode === "list" ? "flex flex-col sm:flex-row" : "flex h-full flex-col"
                              }`}
                            >
                              <div
                                className={`overflow-hidden bg-secondary ${
                                  viewMode === "list" ? "sm:w-64 sm:shrink-0" : ""
                                }`}
                              >
                                <img
                                  src={course.thumbnail || course.image_url || "https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=400&h=250&fit=crop"}
                                  alt={course.title}
                                  loading="lazy"
                                  className={`w-full object-cover transition-transform duration-500 group-hover:scale-105 ${
                                    viewMode === "list" ? "h-44 sm:h-full" : "h-44"
                                  }`}
                                />
                              </div>

                              <div className={`flex flex-1 flex-col p-6 ${viewMode === "list" ? "" : ""}`}>
                                {/* One teal chip for every category, plus at most one
                                    state badge — no per-category colour coding. */}
                                <div className="flex flex-wrap items-center gap-2">
                                  {course.category?.name && (
                                    <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                                      {course.category.name}
                                    </span>
                                  )}
                                  {course.status === "upcoming" ? (
                                    <span className="rounded-full bg-spark/15 px-3 py-1 text-xs font-bold text-navy">
                                      قريباً
                                    </span>
                                  ) : course.is_free ? (
                                    <span className="rounded-full bg-secondary px-3 py-1 text-xs font-bold text-navy">
                                      مجاناً
                                    </span>
                                  ) : null}
                                </div>

                                <h3 className="mt-3 font-bold text-navy leading-relaxed line-clamp-2 transition-colors group-hover:text-primary">
                                  {course.title}
                                </h3>

                                {course.instructor && (
                                  <p className="mt-2 text-sm text-muted-foreground">
                                    {course.instructor.first_name} {course.instructor.last_name}
                                  </p>
                                )}

                                {course.duration && (
                                  <div className="mt-3 flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Clock className="w-4 h-4" aria-hidden="true" />
                                    {course.duration}
                                  </div>
                                )}

                                {/* wrapper carries mt-auto so every card's price row
                                    lines up at the same baseline */}
                                <div className="mt-auto pt-5">
                                  <div className="flex items-center justify-between border-t border-border pt-4">
                                    <div className="flex items-center gap-1">
                                      <Star className="w-4 h-4 fill-spark text-spark" aria-hidden="true" />
                                      <span className="font-bold text-navy tabular-nums">
                                        {course.rating || "جديدة"}
                                      </span>
                                      <span className="text-sm text-muted-foreground tabular-nums">
                                        ({course.total_ratings || 0})
                                      </span>
                                    </div>
                                    <div className="text-lg font-black text-primary tabular-nums">
                                      {course.status === "upcoming" ? (
                                        <span className="text-base text-muted-foreground">قريباً</span>
                                      ) : course.discounted_price && course.discounted_price < course.price ? (
                                        <>
                                          <span className="me-2 text-sm font-medium text-muted-foreground line-through">
                                            {format(course.price)}
                                          </span>
                                          <span>{format(course.discounted_price)}</span>
                                        </>
                                      ) : (
                                        <span>{formatFree(course.price || 0, course.is_free)}</span>
                                      )}
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </Link>
                          </motion.div>
                        ))}
                      </AnimatePresence>
                    </motion.div>

                    {/* Pagination */}
                    {pagination && pagination.total_pages > 1 && (
                      <div className="mt-12 flex flex-col items-center gap-3">
                        <p className="text-sm text-muted-foreground">
                          عرض{" "}
                          <span className="font-bold text-navy tabular-nums">
                            {(page - 1) * (pagination.per_page || 12) + 1}–
                            {Math.min(page * (pagination.per_page || 12), pagination.total)}
                          </span>{" "}
                          من أصل{" "}
                          <span className="font-bold text-navy tabular-nums">{pagination.total}</span> دورة
                        </p>
                        <div className="flex justify-center gap-2">
                          <Button variant="outline" disabled={page === 1} onClick={() => setPage(page - 1)}>
                            <ArrowRight className="w-4 h-4" aria-hidden="true" />
                            السابق
                          </Button>
                          <span className="flex items-center px-4 text-muted-foreground tabular-nums">
                            صفحة {page} من {pagination.total_pages}
                          </span>
                          <Button
                            variant="outline"
                            disabled={page === pagination.total_pages}
                            onClick={() => setPage(page + 1)}
                          >
                            التالي
                            <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                          </Button>
                        </div>
                      </div>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </section>
      </main>

      <Footer />
    </div>
  );
};

export default Courses;
