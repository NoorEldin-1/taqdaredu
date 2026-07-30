import { useState, useEffect, useRef } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { motion, AnimatePresence } from "framer-motion";
import {
  Menu,
  X,
  Search,
  ChevronDown,
  User,
  LogIn,
  ShoppingCart,
  Heart,
  BookOpen,
  LogOut,
  GraduationCap,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useAuth } from "@/contexts/AuthContext";
import { useCart, useCategories } from "@/hooks/useApi";
import { useGuestCart } from "@/lib/guestCart";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { API_BASE_URL } from "@/lib/api/config";

/**
 * The bar is a solid teal band carrying the dot pattern — the client's own
 * header from branding/student-portal.html. It stays teal at every scroll
 * position and on every route: one header, one colour, so the marketing site
 * and the student portal read as the same product. On the landing page it sits
 * directly on the hero's teal band and the two merge into a single field.
 *
 * Orange is spent only on the identity spark and the cart/CTA — the 10% rule.
 */

const navLinks = [
  { name: "الرئيسية", path: "/" },
  { name: "الدورات", path: "/courses" },
  { name: "المدونة", path: "/blogs" },
  { name: "انضم كمدرّب", path: "/become-instructor" },
  { name: "من نحن", path: "/about-us" },
  { name: "تواصل معنا", path: "/contact-us" },
];

// The dropdown is keyed off the route, not the label, so translating the
// labels above can never silently break it.
const COURSES_PATH = "/courses";

const Navbar = () => {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [activeDropdown, setActiveDropdown] = useState<string | null>(null);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const searchInputRef = useRef<HTMLInputElement>(null);
  const location = useLocation();
  const navigate = useNavigate();
  const { user, isAuthenticated, logout } = useAuth();
  const avatarUrl = user?.image && !user.image.includes("placeholder")
    ? (user.image.startsWith("http") ? user.image : `${API_BASE_URL}${user.image}`)
    : null;
  const { data: cartData } = useCart();
  const { data: categoriesData } = useCategories();
  const guestCart = useGuestCart();

  const categories = categoriesData?.data || [];
  // Show the actual course categories in the dropdown: a parent's sub-categories
  // when it has them, otherwise the parent itself. Updates as the admin adds/removes categories.
  const menuCategories = categories.flatMap((c: { id: number; name: string; sub_categories?: { id: number; name: string }[] }) =>
    Array.isArray(c.sub_categories) && c.sub_categories.length > 0 ? c.sub_categories : [{ id: c.id, name: c.name }]
  );
  const cartItemsCount = isAuthenticated ? (cartData?.data?.items?.length || 0) : guestCart.length;

  useEffect(() => {
    const handleScroll = () => {
      setIsScrolled(window.scrollY > 20);
    };
    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, []);

  useEffect(() => {
    setIsMobileMenuOpen(false);
    setIsSearchOpen(false);
  }, [location]);

  useEffect(() => {
    if (isSearchOpen && searchInputRef.current) {
      searchInputRef.current.focus();
    }
  }, [isSearchOpen]);

  // Handle search submit
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/courses?search=${encodeURIComponent(searchQuery.trim())}`);
      setIsSearchOpen(false);
      setSearchQuery("");
    }
  };

  // Close search on escape
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setIsSearchOpen(false);
      }
    };
    document.addEventListener("keydown", handleEscape);
    return () => document.removeEventListener("keydown", handleEscape);
  }, []);

  const handleLogout = () => {
    logout();
    navigate("/");
  };

  // Shared shape for the round icon buttons on the teal ground.
  const iconButtonClass =
    "p-2.5 rounded-xl text-white/85 hover:text-white hover:bg-white/15 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 focus-visible:ring-offset-2 focus-visible:ring-offset-teal";

  const cartBadge = cartItemsCount > 0 && (
    // Ringed in the band colour so the orange reads as a pin, not a smudge.
    <span className="absolute -top-1 -end-1 min-w-5 h-5 px-1 bg-spark text-navy text-xs font-bold rounded-full flex items-center justify-center ring-2 ring-teal">
      {cartItemsCount}
    </span>
  );

  return (
    <header className="relative lg:fixed top-0 inset-x-0 z-50 px-4 pt-4">
      <motion.div
        initial={{ y: -100, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ duration: 0.5 }}
        className={`container mx-auto relative overflow-hidden rounded-2xl bg-teal transition-shadow duration-300 ${
          isScrolled ? "shadow-card" : "shadow-sm"
        }`}
      >
        <div className="absolute inset-0 dot-pattern opacity-20 pointer-events-none" aria-hidden="true" />

        <div className="relative flex items-center justify-between px-5 py-3">
          {/* Logo — graduation cap with the identity spark, matching the hero mark */}
          <Link
            to="/"
            aria-label="تقدر — الصفحة الرئيسية"
            className="flex items-center gap-2.5 shrink-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
          >
            <motion.span
              whileHover={{ scale: 1.05 }}
              transition={{ duration: 0.2 }}
              className="relative inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/15"
            >
              <GraduationCap className="w-6 h-6 text-white" strokeWidth={1.75} aria-hidden="true" />
              <span className="absolute bottom-1.5 left-1.5 w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
            </motion.span>
            <span className="text-xl font-black text-white">تقدر</span>
          </Link>

          {/* Desktop Navigation */}
          <nav className="hidden lg:flex items-center gap-1" aria-label="التنقل الرئيسي">
            {navLinks.map((link) => (
              <div
                key={link.path}
                className="relative"
                onMouseEnter={() => link.path === COURSES_PATH && setActiveDropdown(COURSES_PATH)}
                onMouseLeave={() => setActiveDropdown(null)}
              >
                <Link
                  to={link.path}
                  className={`flex items-center gap-1 px-3.5 py-2.5 rounded-xl text-sm transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 ${
                    location.pathname === link.path
                      ? "bg-white/20 text-white font-bold"
                      : "text-white/85 font-medium hover:bg-white/10 hover:text-white"
                  }`}
                >
                  {link.name}
                  {link.path === COURSES_PATH && menuCategories.length > 0 && (
                    <ChevronDown
                      className={`w-4 h-4 transition-transform duration-200 ${
                        activeDropdown === COURSES_PATH ? "rotate-180" : ""
                      }`}
                      aria-hidden="true"
                    />
                  )}
                </Link>

                {/* Categories Dropdown */}
                <AnimatePresence>
                  {link.path === COURSES_PATH && activeDropdown === COURSES_PATH && menuCategories.length > 0 && (
                    <motion.div
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: 8 }}
                      transition={{ duration: 0.18 }}
                      className="absolute top-full start-0 pt-2"
                    >
                      <div className="bg-card border border-border rounded-2xl shadow-card p-2 min-w-[220px]">
                        {menuCategories.slice(0, 8).map((category, index) => (
                          <motion.div
                            key={category.id}
                            initial={{ opacity: 0, x: 10 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ delay: index * 0.04 }}
                          >
                            <Link
                              to={`/courses?category=${category.id}`}
                              className="block px-4 py-2.5 rounded-xl text-sm font-medium text-navy hover:bg-primary/10 hover:text-primary transition-colors duration-200"
                            >
                              {category.name}
                            </Link>
                          </motion.div>
                        ))}
                      </div>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            ))}
          </nav>

          {/* Right Side Actions */}
          <div className="hidden lg:flex items-center gap-2">
            {/* Search */}
            <AnimatePresence>
              {isSearchOpen ? (
                <motion.form
                  initial={{ width: 0, opacity: 0 }}
                  animate={{ width: 280, opacity: 1 }}
                  exit={{ width: 0, opacity: 0 }}
                  transition={{ duration: 0.3 }}
                  onSubmit={handleSearchSubmit}
                  role="search"
                  className="relative overflow-hidden"
                >
                  <Input
                    ref={searchInputRef}
                    type="text"
                    placeholder="ابحث عن دورة تدريبية..."
                    aria-label="ابحث عن دورة تدريبية"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full h-10 bg-white/15 border-transparent text-white placeholder:text-white/70 pe-10 rounded-full focus-visible:ring-white/70 focus-visible:ring-offset-0"
                  />
                  <button
                    type="button"
                    aria-label="إغلاق البحث"
                    onClick={() => setIsSearchOpen(false)}
                    className="absolute end-3 top-1/2 -translate-y-1/2 text-white/70 hover:text-white"
                  >
                    <X className="w-4 h-4" aria-hidden="true" />
                  </button>
                </motion.form>
              ) : (
                <motion.button
                  whileHover={{ scale: 1.08 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setIsSearchOpen(true)}
                  aria-label="بحث"
                  className={iconButtonClass}
                >
                  <Search className="w-5 h-5" aria-hidden="true" />
                </motion.button>
              )}
            </AnimatePresence>

            {isAuthenticated ? (
              <>
                {/* Wishlist */}
                <Link to="/wishlist">
                  <motion.button
                    whileHover={{ scale: 1.08 }}
                    whileTap={{ scale: 0.95 }}
                    aria-label="المفضلة"
                    className={iconButtonClass}
                  >
                    <Heart className="w-5 h-5" aria-hidden="true" />
                  </motion.button>
                </Link>

                {/* Cart */}
                <Link to="/cart">
                  <motion.button
                    whileHover={{ scale: 1.08 }}
                    whileTap={{ scale: 0.95 }}
                    aria-label="سلة المشتريات"
                    className={`relative ${iconButtonClass}`}
                  >
                    <ShoppingCart className="w-5 h-5" aria-hidden="true" />
                    {cartBadge}
                  </motion.button>
                </Link>

                {/* User Menu */}
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <motion.button
                      whileHover={{ scale: 1.03 }}
                      whileTap={{ scale: 0.95 }}
                      aria-label="قائمة الحساب"
                      className="flex items-center gap-2 px-2.5 py-1.5 rounded-xl text-white/90 hover:bg-white/15 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    >
                      <span className="w-8 h-8 bg-white/20 rounded-full overflow-hidden flex items-center justify-center text-white font-bold text-sm">
                        {avatarUrl ? (
                          <img src={avatarUrl} alt="" className="w-full h-full object-cover" />
                        ) : (
                          user?.first_name?.charAt(0) || "ت"
                        )}
                      </span>
                      <span className="hidden xl:inline text-sm font-medium">{user?.first_name}</span>
                      <ChevronDown className="w-4 h-4" aria-hidden="true" />
                    </motion.button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56 rounded-2xl border-border bg-popover p-2 shadow-card">
                    <DropdownMenuItem asChild className="rounded-xl px-3 py-2.5 text-navy focus:bg-primary/10 focus:text-primary">
                      <Link to="/profile" className="flex items-center gap-2">
                        <User className="w-4 h-4" aria-hidden="true" />
                        الملف الشخصي
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild className="rounded-xl px-3 py-2.5 text-navy focus:bg-primary/10 focus:text-primary">
                      <Link to="/my-courses" className="flex items-center gap-2">
                        <BookOpen className="w-4 h-4" aria-hidden="true" />
                        دوراتي
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild className="rounded-xl px-3 py-2.5 text-navy focus:bg-primary/10 focus:text-primary">
                      <Link to="/wishlist" className="flex items-center gap-2">
                        <Heart className="w-4 h-4" aria-hidden="true" />
                        المفضلة
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="my-1 bg-border" />
                    <DropdownMenuItem
                      onClick={handleLogout}
                      className="rounded-xl px-3 py-2.5 gap-2 text-destructive focus:bg-destructive/10 focus:text-destructive"
                    >
                      <LogOut className="w-4 h-4" aria-hidden="true" />
                      تسجيل الخروج
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </>
            ) : (
              <>
                {/* Cart (visible to guests too) */}
                <Link to="/cart">
                  <motion.button
                    whileHover={{ scale: 1.08 }}
                    whileTap={{ scale: 0.95 }}
                    aria-label="سلة المشتريات"
                    className={`relative ${iconButtonClass}`}
                  >
                    <ShoppingCart className="w-5 h-5" aria-hidden="true" />
                    {cartBadge}
                  </motion.button>
                </Link>

                {/* Login Button */}
                <Link to="/login">
                  <motion.div whileHover={{ scale: 1.03 }} whileTap={{ scale: 0.95 }}>
                    <Button
                      variant="ghost"
                      size="default"
                      className="rounded-xl px-4 text-white/90 hover:bg-white/15 hover:text-white"
                    >
                      <LogIn className="w-4 h-4" aria-hidden="true" />
                      تسجيل الدخول
                    </Button>
                  </motion.div>
                </Link>

                {/* Sign Up — the single orange CTA in the bar */}
                <Link to="/register">
                  <motion.div whileHover={{ scale: 1.03 }} whileTap={{ scale: 0.95 }}>
                    <Button
                      size="default"
                      className="rounded-xl px-5 bg-spark hover:bg-spark-dark text-navy font-bold border-0 shadow-none"
                    >
                      <User className="w-4 h-4" aria-hidden="true" />
                      إنشاء حساب
                    </Button>
                  </motion.div>
                </Link>
              </>
            )}
          </div>

          {/* Mobile Menu Button */}
          <div className="lg:hidden flex items-center gap-1">
            {/* Mobile Search Button */}
            <motion.button
              whileHover={{ scale: 1.08 }}
              whileTap={{ scale: 0.95 }}
              onClick={() => setIsSearchOpen(!isSearchOpen)}
              aria-label="بحث"
              aria-expanded={isSearchOpen}
              className={iconButtonClass}
            >
              <Search className="w-5 h-5" aria-hidden="true" />
            </motion.button>

            {/* Cart (visible to guests too, matching desktop behavior) */}
            <Link to="/cart">
              <motion.button
                whileHover={{ scale: 1.08 }}
                whileTap={{ scale: 0.95 }}
                aria-label="سلة المشتريات"
                className={`relative ${iconButtonClass}`}
              >
                <ShoppingCart className="w-5 h-5" aria-hidden="true" />
                {cartBadge}
              </motion.button>
            </Link>

            <motion.button
              whileHover={{ scale: 1.08 }}
              whileTap={{ scale: 0.95 }}
              aria-label={isMobileMenuOpen ? "إغلاق القائمة" : "فتح القائمة"}
              aria-expanded={isMobileMenuOpen}
              className={iconButtonClass}
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            >
              {isMobileMenuOpen ? (
                <X className="w-6 h-6" aria-hidden="true" />
              ) : (
                <Menu className="w-6 h-6" aria-hidden="true" />
              )}
            </motion.button>
          </div>
        </div>

        {/* Mobile Search Bar */}
        <AnimatePresence>
          {isSearchOpen && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              className="relative lg:hidden border-t border-white/15 overflow-hidden"
            >
              <form onSubmit={handleSearchSubmit} role="search" className="p-4">
                <div className="relative">
                  <Search
                    className="absolute start-4 top-1/2 -translate-y-1/2 w-5 h-5 text-white/70"
                    aria-hidden="true"
                  />
                  <Input
                    type="text"
                    placeholder="ابحث عن دورة تدريبية..."
                    aria-label="ابحث عن دورة تدريبية"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full h-11 ps-11 bg-white/15 border-transparent text-white placeholder:text-white/70 rounded-full focus-visible:ring-white/70 focus-visible:ring-offset-0"
                    autoFocus
                  />
                </div>
              </form>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Mobile Menu */}
        <AnimatePresence>
          {isMobileMenuOpen && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              exit={{ opacity: 0, height: 0 }}
              className="relative lg:hidden border-t border-white/15 overflow-hidden"
            >
              <div className="px-4 py-4 space-y-1">
                {navLinks.map((link, index) => (
                  <motion.div
                    key={link.path}
                    // Slides in from the start edge, which is the right in RTL.
                    initial={{ opacity: 0, x: 20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: index * 0.05 }}
                  >
                    <Link
                      to={link.path}
                      className={`block py-3 px-4 rounded-xl transition-colors duration-200 ${
                        location.pathname === link.path
                          ? "bg-white/20 text-white font-bold"
                          : "text-white/85 font-medium hover:bg-white/10 hover:text-white"
                      }`}
                    >
                      {link.name}
                    </Link>
                  </motion.div>
                ))}

                <motion.div
                  className="pt-4 mt-2 border-t border-white/15 space-y-2"
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.2 }}
                >
                  {isAuthenticated ? (
                    <>
                      <Link to="/my-courses" className="block">
                        <Button variant="ghost" className="w-full rounded-xl justify-start text-white/90 hover:bg-white/15 hover:text-white">
                          <BookOpen className="w-4 h-4" aria-hidden="true" />
                          دوراتي
                        </Button>
                      </Link>
                      <Link to="/profile" className="block">
                        <Button variant="ghost" className="w-full rounded-xl justify-start text-white/90 hover:bg-white/15 hover:text-white">
                          <User className="w-4 h-4" aria-hidden="true" />
                          الملف الشخصي
                        </Button>
                      </Link>
                      <Button
                        variant="ghost"
                        className="w-full rounded-xl justify-start text-white/90 hover:bg-white/15 hover:text-white"
                        onClick={handleLogout}
                      >
                        <LogOut className="w-4 h-4" aria-hidden="true" />
                        تسجيل الخروج
                      </Button>
                    </>
                  ) : (
                    <>
                      <Link to="/login" className="block">
                        <Button variant="ghost" className="w-full rounded-xl text-white/90 hover:bg-white/15 hover:text-white">
                          <LogIn className="w-4 h-4" aria-hidden="true" />
                          تسجيل الدخول
                        </Button>
                      </Link>
                      <Link to="/register" className="block">
                        <Button className="w-full rounded-xl bg-spark hover:bg-spark-dark text-navy font-bold border-0 shadow-none">
                          <User className="w-4 h-4" aria-hidden="true" />
                          إنشاء حساب
                        </Button>
                      </Link>
                    </>
                  )}
                </motion.div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </motion.div>
    </header>
  );
};

export default Navbar;
