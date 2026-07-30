import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import HeroSection from "@/components/home/HeroSection";
import BenefitsSection from "@/components/home/BenefitsSection";
import FeaturedCourses from "@/components/home/FeaturedCourses";
import CategoriesSection from "@/components/home/CategoriesSection";
import UpcomingCoursesSection from "@/components/home/UpcomingCoursesSection";
import TopInstructorsSection from "@/components/home/TopInstructorsSection";
import TestimonialsSection from "@/components/home/TestimonialsSection";
import BlogsSection from "@/components/home/BlogsSection";
import CTASection from "@/components/home/CTASection";
import Seo from "@/components/Seo";

/**
 * Tagdar landing.
 *
 * The ground is deliberately light (slate-50, the brand book's 60%) — the page
 * gets its structure from the teal band in the hero and from white cards, not
 * from a dark backdrop. No ambient orbs or glow: contrast comes from the band.
 */
const Index = () => {
  return (
    <div className="min-h-screen bg-secondary">
      <Seo
        description="منصة تقدر التعليمية — تعلّم من نخبة المدربين، وطوّر مهاراتك بمسارات مصممة لتحقيق أهدافك، وشهادات معتمدة تفتح لك أبواب فرص أفضل."
        canonical="/"
      />
      <Navbar />
      <main>
        <HeroSection />
        <BenefitsSection />
        <CategoriesSection />
        <FeaturedCourses />
        <UpcomingCoursesSection />
        <TopInstructorsSection />
        <TestimonialsSection />
        {/* A "download the app" section was removed with the previous owner's
            store listings and app screenshot. Re-add once Tagdar ships an app. */}
        <BlogsSection />
        <CTASection />
      </main>
      <Footer />
    </div>
  );
};

export default Index;
