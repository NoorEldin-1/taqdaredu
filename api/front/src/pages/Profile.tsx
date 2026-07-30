import { useRef, useState, useEffect } from "react";
import { motion } from "framer-motion";
import { User, Mail, BookOpen, Loader2, Camera, Heart, ArrowLeft } from "lucide-react";
import Navbar from "@/components/layout/Navbar";
import Footer from "@/components/layout/Footer";
import { useAuth } from "@/contexts/AuthContext";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import BrandIllustration from "@/components/BrandIllustration";
import { authService } from "@/lib/api/services";
import { API_BASE_URL } from "@/lib/api/config";
import { useToast } from "@/hooks/use-toast";

const Profile = () => {
  const { user, isAuthenticated, isLoading, updateUser } = useAuth();
  const { toast } = useToast();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [imgError, setImgError] = useState(false);

  // Editable profile fields — seeded from the loaded user (re-seeded only when
  // the signed-in account changes, so typing is never reset mid-edit).
  const [firstName, setFirstName] = useState(user?.first_name || "");
  const [lastName, setLastName] = useState(user?.last_name || "");
  const [email, setEmail] = useState(user?.email || "");
  const [saving, setSaving] = useState(false);
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [changingPassword, setChangingPassword] = useState(false);

  useEffect(() => {
    if (user) {
      setFirstName(user.first_name || "");
      setLastName(user.last_name || "");
      setEmail(user.email || "");
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id]);

  const handleSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const res = await authService.updateProfile({ first_name: firstName, last_name: lastName, email });
      if (res.status) {
        updateUser({ first_name: firstName, last_name: lastName, email });
        toast({ title: "تم تحديث الملف الشخصي", description: "حُفظت بياناتك بنجاح." });
      } else {
        toast({ title: "تعذّر الحفظ", description: res.message || "لم نتمكن من حفظ التعديلات.", variant: "destructive" });
      }
    } catch {
      toast({ title: "تعذّر الحفظ", description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.", variant: "destructive" });
    } finally {
      setSaving(false);
    }
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    const strong = newPassword.length >= 8 && /[A-Z]/.test(newPassword) && /[a-z]/.test(newPassword) && /[0-9]/.test(newPassword);
    if (!strong) {
      toast({ title: "كلمة مرور ضعيفة", description: "٨ أحرف على الأقل، تتضمّن حرفاً كبيراً وآخر صغيراً ورقماً.", variant: "destructive" });
      return;
    }
    if (newPassword !== confirmPassword) {
      toast({ title: "كلمتا المرور غير متطابقتين", description: "يُرجى إعادة إدخال كلمة المرور الجديدة.", variant: "destructive" });
      return;
    }
    setChangingPassword(true);
    try {
      const res = await authService.changePassword(currentPassword, newPassword);
      if (res.status) {
        toast({ title: "تم تغيير كلمة المرور", description: "استخدم كلمة المرور الجديدة في تسجيل دخولك القادم." });
        setCurrentPassword(""); setNewPassword(""); setConfirmPassword("");
      } else {
        toast({ title: "تعذّر تغيير كلمة المرور", description: res.message || "تحقّق من كلمة المرور الحالية.", variant: "destructive" });
      }
    } catch {
      toast({ title: "تعذّر تغيير كلمة المرور", description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.", variant: "destructive" });
    } finally {
      setChangingPassword(false);
    }
  };

  const avatarUrl = user?.image && !user.image.includes("placeholder")
    ? (user.image.startsWith("http") ? user.image : `${API_BASE_URL}${user.image}`)
    : null;

  const handleImageChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
      toast({ title: "ملف غير صالح", description: "يُرجى اختيار ملف صورة.", variant: "destructive" });
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      toast({ title: "حجم الصورة كبير", description: "الحد الأقصى ٥ ميجابايت.", variant: "destructive" });
      return;
    }

    setUploading(true);
    try {
      const res = await authService.uploadProfileImage(file);
      if (res.status && res.data?.image) {
        setImgError(false);
        updateUser({ image: res.data.image });
        toast({ title: "تم تحديث الصورة", description: "غُيّرت صورتك الشخصية بنجاح." });
      } else {
        toast({ title: "تعذّر الرفع", description: res.message || "لم نتمكن من تحديث الصورة.", variant: "destructive" });
      }
    } catch {
      toast({ title: "تعذّر الرفع", description: "حدث خطأ غير متوقع. يُرجى المحاولة مرة أخرى.", variant: "destructive" });
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-secondary flex items-center justify-center">
        <Loader2 className="w-8 h-8 text-primary animate-spin" aria-label="جارٍ التحميل" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-secondary">
        <Navbar />
        <main className="pt-32 pb-20">
          <div className="container max-w-2xl">
            <div className="card-tagdar flex flex-col items-center gap-7 border-dashed px-6 py-16 text-center hover:shadow-none hover:translate-y-0">
              <BrandIllustration name="profile" className="w-64 max-w-full" />
              <div>
                <h1 className="text-2xl font-black text-navy">سجّل دخولك أولاً</h1>
                <p className="mx-auto mt-3 max-w-sm text-muted-foreground leading-relaxed">
                  تحتاج إلى تسجيل الدخول لعرض ملفك الشخصي وإدارة بياناتك.
                </p>
              </div>
              <Link to="/login">
                <Button size="lg">
                  تسجيل الدخول
                  <ArrowLeft className="w-4 h-4" aria-hidden="true" />
                </Button>
              </Link>
            </div>
          </div>
        </main>
        <Footer />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-secondary">
      <Navbar />
      <main className="pt-32 pb-20">
        <div className="container max-w-2xl">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="card-tagdar p-8 hover:translate-y-0"
          >
            <div className="mb-6 flex items-center gap-4">
              <div className="relative h-20 w-20 shrink-0">
                <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-primary/10">
                  {avatarUrl && !imgError ? (
                    <img
                      src={avatarUrl}
                      alt="صورتك الشخصية"
                      className="h-full w-full object-cover"
                      onError={() => setImgError(true)}
                    />
                  ) : (
                    <User className="h-10 w-10 text-primary" aria-hidden="true" />
                  )}
                </div>
                {/* Sits at the reading-end corner so it flips with the document direction. */}
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  disabled={uploading}
                  className="absolute -bottom-1 -end-1 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white shadow-sm transition-colors hover:bg-primary-hover disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  aria-label="تغيير الصورة الشخصية"
                >
                  {uploading ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Camera className="h-4 w-4" aria-hidden="true" />}
                </button>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={handleImageChange}
                />
              </div>
              <div className="min-w-0">
                <h1 className="text-2xl font-black text-navy">{user?.first_name} {user?.last_name}</h1>
                <p className="mt-1 flex items-center gap-2 text-muted-foreground">
                  <Mail className="h-4 w-4 shrink-0" aria-hidden="true" />
                  <span className="truncate" dir="ltr">{user?.email}</span>
                </p>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <Link
                to="/my-courses"
                className="rounded-xl border border-border bg-secondary p-4 text-center transition-colors hover:border-primary/50 hover:bg-primary/5"
              >
                <BookOpen className="mx-auto mb-2 h-6 w-6 text-primary" aria-hidden="true" />
                <span className="font-medium text-navy">دوراتي</span>
              </Link>
              <Link
                to="/wishlist"
                className="rounded-xl border border-border bg-secondary p-4 text-center transition-colors hover:border-primary/50 hover:bg-primary/5"
              >
                <Heart className="mx-auto mb-2 h-6 w-6 text-primary" aria-hidden="true" />
                <span className="font-medium text-navy">قائمة الرغبات</span>
              </Link>
            </div>
          </motion.div>

          {/* Edit profile details */}
          <motion.form
            onSubmit={handleSaveProfile}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="card-tagdar mt-6 space-y-5 p-8 hover:translate-y-0"
          >
            <h2 className="text-xl font-bold text-navy">تعديل البيانات</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="firstName" className="text-navy font-medium">الاسم الأول</Label>
                <Input id="firstName" value={firstName} onChange={(e) => setFirstName(e.target.value)} className="h-12 rounded-xl" autoComplete="given-name" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="lastName" className="text-navy font-medium">اسم العائلة</Label>
                <Input id="lastName" value={lastName} onChange={(e) => setLastName(e.target.value)} className="h-12 rounded-xl" autoComplete="family-name" />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="email" className="text-navy font-medium">البريد الإلكتروني</Label>
              <div className="relative">
                <Mail className="absolute start-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="h-12 rounded-xl ps-11" autoComplete="email" dir="ltr" />
              </div>
            </div>
            <Button type="submit" size="lg" disabled={saving}>
              {saving ? <Loader2 className="h-4 w-4 animate-spin" aria-label="جارٍ الحفظ" /> : "حفظ التعديلات"}
            </Button>
          </motion.form>

          {/* Change password */}
          <motion.form
            onSubmit={handleChangePassword}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="card-tagdar mt-6 space-y-5 p-8 hover:translate-y-0"
          >
            <h2 className="text-xl font-bold text-navy">تغيير كلمة المرور</h2>
            <div className="space-y-2">
              <Label htmlFor="currentPassword" className="text-navy font-medium">كلمة المرور الحالية</Label>
              <Input id="currentPassword" type="password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} className="h-12 rounded-xl" autoComplete="current-password" />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="newPassword" className="text-navy font-medium">كلمة المرور الجديدة</Label>
                <Input id="newPassword" type="password" value={newPassword} onChange={(e) => setNewPassword(e.target.value)} className="h-12 rounded-xl" autoComplete="new-password" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirmPassword" className="text-navy font-medium">تأكيد كلمة المرور</Label>
                <Input id="confirmPassword" type="password" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} className="h-12 rounded-xl" autoComplete="new-password" />
              </div>
            </div>
            <Button type="submit" size="lg" disabled={changingPassword}>
              {changingPassword ? <Loader2 className="h-4 w-4 animate-spin" aria-label="جارٍ التحديث" /> : "تحديث كلمة المرور"}
            </Button>
          </motion.form>
        </div>
      </main>
      <Footer />
    </div>
  );
};

export default Profile;
