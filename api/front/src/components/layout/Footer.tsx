import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import {
  Facebook,
  Twitter,
  Linkedin,
  Instagram,
  Youtube,
  Mail,
  Phone,
  MapPin,
  ArrowUpLeft,
  GraduationCap,
} from "lucide-react";
import BrandIcon from "@/components/BrandIcon";
import { useSiteSettings } from "@/hooks/useApi";

/**
 * The footer is the page's closing band: solid navy carrying the same dot
 * pattern the header uses, so the light page is bracketed by brand colour at
 * both ends. Navy (not glass) — the surface is opaque and the tiles are flat.
 */

const quickLinks = [
  { name: "الرئيسية", path: "/" },
  { name: "من نحن", path: "/about-us" },
  { name: "جميع الدورات", path: "/courses" },
  { name: "المدونة", path: "/blogs" },
  { name: "انضم كمدرّب", path: "/become-instructor" },
  { name: "تواصل معنا", path: "/contact-us" },
];

const legalLinks = [
  { name: "سياسة الخصوصية", path: "/privacy-policy" },
  { name: "الشروط والأحكام", path: "/terms" },
  { name: "سياسة الاسترجاع", path: "/refund-policy" },
  { name: "الأسئلة الشائعة", path: "/faq" },
];

const Footer = () => {
  const { data: settingsData } = useSiteSettings();
  const settings = settingsData?.data;

  // TODO(client): Tagdar's real social profiles must be entered in the admin
  // site settings. The "#" fallbacks are placeholders and are filtered out
  // below, so nothing renders until real URLs exist — we never ship a dead or
  // wrong-account social icon.
  const socialLinks = [
    { icon: Facebook, href: settings?.facebook || "#", label: "فيسبوك" },
    { icon: Twitter, href: settings?.twitter || "#", label: "إكس" },
    { icon: Linkedin, href: settings?.linkedin || "#", label: "لينكد إن" },
    { icon: Instagram, href: settings?.instagram || "#", label: "إنستغرام" },
    { icon: Youtube, href: settings?.youtube || "#", label: "يوتيوب" },
  ].filter(s => s.href && s.href !== "#");

  // TODO(client): replace these placeholders with Tagdar's real address, phone
  // and email — either here or, preferably, in the admin site settings. Rows
  // without a real value render as inert text rather than a broken tel:/mailto:.
  const contactItems = [
    {
      icon: MapPin,
      value: settings?.address,
      placeholder: "العنوان — بانتظار بيانات العميل",
      href: null as string | null,
    },
    {
      icon: Phone,
      value: settings?.phone,
      placeholder: "رقم الهاتف — بانتظار بيانات العميل",
      href: settings?.phone ? `tel:${settings.phone}` : null,
    },
    {
      icon: Mail,
      value: settings?.email,
      placeholder: "البريد الإلكتروني — بانتظار بيانات العميل",
      href: settings?.email ? `mailto:${settings.email}` : null,
    },
  ];

  return (
    <footer className="relative z-10 bg-navy overflow-hidden">
      <div className="absolute inset-0 dot-pattern opacity-10 pointer-events-none" aria-hidden="true" />

      {/* Content */}
      <div className="relative container py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
          {/* Logo & Description */}
          <motion.div
            className="space-y-6"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <Link to="/" aria-label="تقدر — الصفحة الرئيسية" className="flex items-center gap-2.5 w-fit">
              <span className="relative inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white/10">
                <GraduationCap className="w-6 h-6 text-white" strokeWidth={1.75} aria-hidden="true" />
                <span className="absolute bottom-2 left-2 w-1.5 h-1.5 rounded-full bg-spark" aria-hidden="true" />
              </span>
              <span className="text-2xl font-black text-white">تقدر</span>
            </Link>
            <p className="text-white/70 leading-relaxed">
              منصة تعليمية عربية تجمع نخبة المدرّبين في مسارات واضحة ومرنة، لتتعلّم
              مهارة جديدة بإيقاعك وتُنهيها بشهادة معتمدة تضيف وزناً حقيقياً إلى مسارك
              المهني.
            </p>
            {socialLinks.length > 0 && (
              <div className="flex gap-2">
                {socialLinks.map((social, index) => (
                  <motion.a
                    key={social.label}
                    href={social.href}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={social.label}
                    initial={{ opacity: 0, scale: 0.8 }}
                    whileInView={{ opacity: 1, scale: 1 }}
                    viewport={{ once: true }}
                    transition={{ delay: index * 0.05 }}
                    whileHover={{ scale: 1.08, y: -2 }}
                    className="w-10 h-10 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center text-white/75 hover:text-white hover:bg-white/20 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                  >
                    <social.icon className="w-5 h-5" aria-hidden="true" />
                  </motion.a>
                ))}
              </div>
            )}
          </motion.div>

          {/* Quick Links */}
          <motion.nav
            aria-label="روابط سريعة"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5, delay: 0.1 }}
          >
            <h3 className="text-lg font-bold text-white mb-6">روابط سريعة</h3>
            <ul className="space-y-3">
              {quickLinks.map((link, index) => (
                <motion.li
                  key={link.path}
                  initial={{ opacity: 0, x: 10 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.05 }}
                >
                  <Link
                    to={link.path}
                    className="group inline-flex items-center gap-2 text-white/70 hover:text-white transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 rounded"
                  >
                    <span>{link.name}</span>
                    {/* Points up-and-left: "forward" in an RTL reading direction */}
                    <ArrowUpLeft
                      className="w-4 h-4 text-spark opacity-0 translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200"
                      aria-hidden="true"
                    />
                  </Link>
                </motion.li>
              ))}
            </ul>
          </motion.nav>

          {/* Legal */}
          <motion.nav
            aria-label="روابط قانونية"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5, delay: 0.2 }}
          >
            <h3 className="text-lg font-bold text-white mb-6">الشروط والسياسات</h3>
            <ul className="space-y-3">
              {legalLinks.map((link, index) => (
                <motion.li
                  key={link.path}
                  initial={{ opacity: 0, x: 10 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: index * 0.05 }}
                >
                  <Link
                    to={link.path}
                    className="group inline-flex items-center gap-2 text-white/70 hover:text-white transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 rounded"
                  >
                    <span>{link.name}</span>
                    <ArrowUpLeft
                      className="w-4 h-4 text-spark opacity-0 translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200"
                      aria-hidden="true"
                    />
                  </Link>
                </motion.li>
              ))}
            </ul>
          </motion.nav>

          {/* Contact Info */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5, delay: 0.3 }}
          >
            <h3 className="text-lg font-bold text-white mb-6">تواصل معنا</h3>
            <ul className="space-y-3">
              {contactItems.map((item) => {
                const rowClass =
                  "flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/10 transition-colors duration-200";
                const content = (
                  <>
                    {/* BrandIcon recoloured for the navy ground; it keeps the spark dot */}
                    <BrandIcon icon={item.icon} size="sm" className="bg-white/10 text-white" />
                    <span className="text-sm text-white/70">{item.value || item.placeholder}</span>
                  </>
                );

                return (
                  <li key={item.placeholder}>
                    {item.href ? (
                      <a
                        href={item.href}
                        className={`${rowClass} hover:bg-white/10 hover:border-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70`}
                      >
                        {content}
                      </a>
                    ) : (
                      <div className={rowClass}>{content}</div>
                    )}
                  </li>
                );
              })}
            </ul>
          </motion.div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="relative border-t border-white/10">
        <div className="container py-6 flex flex-col md:flex-row items-center justify-between gap-4">
          <p className="text-white/70 text-sm">
            © {new Date().getFullYear()} تقدر. جميع الحقوق محفوظة.
          </p>
          <p className="text-white/70 text-sm flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-spark" aria-hidden="true" />
            تعلّم مهارة اليوم، واصنع فرصة الغد
          </p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
