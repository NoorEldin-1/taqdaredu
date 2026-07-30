import { useState, useEffect } from "react";
import { useParams, Link } from "react-router-dom";
import { motion } from "framer-motion";
import {
  Play, CheckCircle, Lock, Clock,
  BookOpen, MessageCircle, Download, Award, Menu, X, Loader2,
  ChevronLeft, ChevronRight
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { useCourse, useCompleteLesson, useCourseCurriculum } from "@/hooks/useApi";
import { useToast } from "@/hooks/use-toast";
import { Section, Lesson } from "@/lib/api/types";
import LessonVideoPlayer from "@/components/course/LessonVideoPlayer";

const CourseWatch = () => {
  const { id } = useParams();
  const { toast } = useToast();
  const { data: courseData, isLoading: courseLoading } = useCourse(Number(id) || 0);
  const { data: curriculumData, isLoading: curriculumLoading } = useCourseCurriculum(Number(id) || 0);
  const markComplete = useCompleteLesson();
  
  const [currentLesson, setCurrentLesson] = useState<Lesson | null>(null);
  const [showSidebar, setShowSidebar] = useState(true);

  const course = courseData?.data;
  const curriculum = curriculumData?.data;
  
  // Convert curriculum sections to Section[] format (must include video_url/video_type
  // so the player can switch source per lesson).
  const sections: Section[] = curriculum?.sections?.map(s => ({
    id: s.id,
    title: s.title,
    lessons: s.lessons?.map(l => ({
      id: l.id,
      title: l.title,
      duration: l.duration,
      type: l.type || l.lesson_type,
      is_free: l.is_free,
      is_completed: l.is_completed,
      video_url: l.video_url,
      video_type: l.video_type,
      summary: l.summary,
      attachment_url: l.attachment_url,
      attachment_type: l.attachment_type,
    }))
  })) || [];

  // Set first lesson as current when curriculum loads
  useEffect(() => {
    if (sections.length > 0 && sections[0].lessons && sections[0].lessons.length > 0 && !currentLesson) {
      setCurrentLesson(sections[0].lessons[0]);
    }
  }, [sections, currentLesson]);

  // Calculate total and completed lectures
  const totalLectures = sections.reduce((acc, section) => acc + (section.lessons?.length || 0), 0);
  const completedLectures = sections.reduce(
    (acc, section) => acc + (section.lessons?.filter(item => item.is_completed).length || 0), 0
  );
  const progressPercentage = totalLectures > 0 ? (completedLectures / totalLectures) * 100 : 0;

  const handleMarkComplete = async () => {
    if (!currentLesson?.id || !id) return;
    
    markComplete.mutate({ lessonId: currentLesson.id, courseId: Number(id) }, {
      onSuccess: () => {
        toast({
          title: "أُنجز الدرس",
          description: "حُفظ تقدّمك.",
        });
      },
      onError: () => {
        toast({
          title: "تعذّر حفظ التقدّم",
          description: "لم نتمكّن من تحديد الدرس كمكتمل. يُرجى المحاولة مرة أخرى.",
          variant: "destructive",
        });
      }
    });
  };

  const handleSelectLesson = (lesson: Lesson) => {
    if (lesson.is_free || course?.is_enrolled) {
      setCurrentLesson(lesson);
    } else {
      toast({
        title: "هذا الدرس مقفل",
        description: "اشترك في الدورة للوصول إلى هذا الدرس.",
        variant: "destructive",
      });
    }
  };

  // Find current section index
  const currentSectionIndex = sections.findIndex(section => 
    section.lessons?.some(lesson => lesson.id === currentLesson?.id)
  );

  if (courseLoading || curriculumLoading) {
    return (
      <div className="min-h-screen bg-navy flex flex-col">
        <header className="h-14 bg-card border-b border-border flex items-center px-4">
          <Skeleton className="h-6 w-48" />
        </header>
        <div className="flex-1 flex">
          <main className="flex-1">
            <Skeleton className="aspect-video w-full" />
            <div className="p-6">
              <Skeleton className="h-8 w-64 mb-4" />
              <Skeleton className="h-4 w-full" />
            </div>
          </main>
          <aside className="w-[380px] bg-card border-s border-border p-4">
            <Skeleton className="h-8 w-48 mb-4" />
            {[1, 2, 3].map(i => (
              <Skeleton key={i} className="h-16 w-full mb-2" />
            ))}
          </aside>
        </div>
      </div>
    );
  }

  if (!course) {
    return (
      <div className="min-h-screen bg-navy flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-2xl text-white mb-4">Course not found</h1>
          <Link to="/courses" className="text-primary hover:underline">
            Browse courses
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-navy flex flex-col">
      {/* Top Navigation */}
      <header className="h-14 bg-card border-b border-border flex items-center justify-between px-4 z-50">
        <div className="flex items-center gap-4">
          <Link 
            to={`/courses/${id}`}
            className="flex items-center gap-2 text-muted-foreground hover:text-foreground transition-colors"
          >
            <ChevronLeft className="w-5 h-5" />
            <span className="hidden sm:inline">Back to Course</span>
          </Link>
          <div className="h-6 w-px bg-border" />
          <h1 className="text-sm font-medium text-foreground line-clamp-1 max-w-[200px] sm:max-w-md">
            {course.title}
          </h1>
        </div>

        <div className="flex items-center gap-3">
          <div className="hidden sm:flex items-center gap-2 text-sm text-muted-foreground">
            <span>{completedLectures}/{totalLectures} lectures</span>
          </div>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setShowSidebar(!showSidebar)}
            className="lg:hidden"
          >
            {showSidebar ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </Button>
        </div>
      </header>

      <div className="flex-1 flex overflow-hidden">
        {/* Main Content - Video Player */}
        <main className={cn(
          "flex-1 flex flex-col transition-all duration-300",
          showSidebar ? "lg:me-[380px]" : ""
        )}>
          {/* Video Player Container */}
          <div className="relative bg-black aspect-video w-full">
            <LessonVideoPlayer
              lesson={currentLesson}
              fallbackThumbnail={course.thumbnail || course.thumbnail_url || course.image_url}
              isEnrolled={course.is_enrolled}
            />
          </div>

          {/* Lecture Info */}
          <div className="p-6 bg-background border-b border-border">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <p className="text-sm text-muted-foreground mb-1">
                  القسم {currentSectionIndex + 1}
                </p>
                <h2 className="text-xl font-semibold text-foreground">
                  {currentLesson?.title || "اختر درساً للبدء"}
                </h2>
              </div>

              <div className="flex items-center gap-3">
                <Button 
                  variant="default" 
                  size="sm"
                  onClick={handleMarkComplete}
                  disabled={markComplete.isPending || currentLesson?.is_completed}
                >
                  {markComplete.isPending ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                  ) : (
                    <CheckCircle className="w-4 h-4" />
                  )}
                  {currentLesson?.is_completed ? "تم إنهاؤه" : "تحديد كمكتمل"}
                </Button>
              </div>
            </div>
          </div>

          {/* Course Progress Mobile */}
          <div className="p-4 bg-background lg:hidden border-b border-border">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium text-foreground">تقدّمك في الدورة</span>
              <span className="text-sm text-muted-foreground">{Math.round(progressPercentage)}%</span>
            </div>
            <Progress value={progressPercentage} className="h-2" />
          </div>

          {/* Notes/Description Area */}
          <div className="flex-1 p-6 bg-background overflow-auto">
            <div className="max-w-3xl">
              <h3 className="text-lg font-semibold text-foreground mb-4">عن هذا الدرس</h3>
              <div
                className="text-muted-foreground leading-relaxed [&_p]:mb-3 [&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5 [&_a]:text-primary [&_a]:underline"
                dangerouslySetInnerHTML={{
                  __html: course.short_description || course.description || "لا يوجد وصف لهذا الدرس.",
                }}
              />
            </div>
          </div>
        </main>

        {/* Sidebar - Course Curriculum */}
        {/* Curriculum sits at the inline-end edge, which is the LEFT under RTL.
            `translate-x` is physical in Tailwind (not flipped by dir), so the
            off-screen state is negative here to match. */}
        <aside className={cn(
          "fixed end-0 top-14 bottom-0 w-[380px] bg-card border-s border-border z-40 transition-transform duration-300",
          showSidebar ? "translate-x-0" : "-translate-x-full",
          "lg:translate-x-0"
        )}>
          {/* Sidebar Header */}
          <div className="p-4 border-b border-border">
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-semibold text-foreground">Course Content</h3>
              <Button
                variant="ghost"
                size="icon"
                onClick={() => setShowSidebar(false)}
                className="lg:hidden"
              >
                <X className="w-5 h-5" />
              </Button>
            </div>
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Award className="w-4 h-4 text-primary" />
              <span>{completedLectures} of {totalLectures} completed</span>
            </div>
            <Progress value={progressPercentage} className="h-1.5 mt-2" />
          </div>

          {/* Curriculum List */}
          <ScrollArea className="h-[calc(100vh-140px)]">
            <Accordion 
              type="multiple" 
              defaultValue={sections.map((_, i) => `section-${i}`)} 
              className="w-full"
            >
              {sections.map((section, sectionIndex) => (
                <AccordionItem key={section.id} value={`section-${sectionIndex}`} className="border-b border-border">
                  <AccordionTrigger className="px-4 py-3 hover:bg-secondary/50 hover:no-underline">
                    <div className="flex flex-col items-start text-left">
                      <span className="font-medium text-foreground text-sm">
                        {section.title}
                      </span>
                      <span className="text-xs text-muted-foreground mt-0.5">
                        {section.lessons?.filter(l => l.is_completed).length || 0}/{section.lessons?.length || 0} • {section.total_duration || ""}
                      </span>
                    </div>
                  </AccordionTrigger>
                  <AccordionContent className="pb-0">
                    <div className="space-y-0.5">
                      {section.lessons?.map((lesson) => {
                        const isLocked = !lesson.is_free && !course.is_enrolled;
                        const isActive = lesson.id === currentLesson?.id;
                        
                        return (
                          <button
                            key={lesson.id}
                            onClick={() => handleSelectLesson(lesson)}
                            disabled={isLocked}
                            className={cn(
                              "w-full flex items-start gap-3 p-3 text-start transition-colors",
                              isActive
                                ? "bg-primary/10 border-s-2 border-primary"
                                : "hover:bg-secondary/50",
                              lesson.is_completed && "opacity-70",
                              isLocked && "opacity-50 cursor-not-allowed"
                            )}
                          >
                            <div className="flex-shrink-0 mt-0.5">
                              {isLocked ? (
                                <Lock className="w-4 h-4 text-muted-foreground" />
                              ) : lesson.is_completed ? (
                                <CheckCircle className="w-4 h-4 text-primary" />
                              ) : isActive ? (
                                <Play className="w-4 h-4 text-primary fill-primary" />
                              ) : (
                                <div className="w-4 h-4 rounded-full border-2 border-muted-foreground/30" />
                              )}
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className={cn(
                                "text-sm line-clamp-2",
                                isActive
                                  ? "text-primary font-medium"
                                  : "text-foreground"
                              )}>
                                {lesson.title}
                              </p>
                              <div className="flex items-center gap-2 mt-1">
                                <Clock className="w-3 h-3 text-muted-foreground" />
                                <span className="text-xs text-muted-foreground">{lesson.duration}</span>
                                {lesson.is_free && (
                                  <span className="text-xs text-primary font-medium">Free</span>
                                )}
                              </div>
                            </div>
                          </button>
                        );
                      })}
                    </div>
                  </AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          </ScrollArea>
        </aside>
      </div>

      {/* Sidebar Toggle Button (Desktop) */}
      <button
        onClick={() => setShowSidebar(!showSidebar)}
        aria-label={showSidebar ? "إخفاء محتوى الدورة" : "إظهار محتوى الدورة"}
        aria-expanded={showSidebar}
        className={cn(
          "hidden lg:flex fixed end-0 top-1/2 -translate-y-1/2 z-50 items-center justify-center w-6 h-12 bg-card border border-border rounded-s-xl transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
          showSidebar ? "end-[380px]" : "end-0"
        )}
      >
        {/* The panel lives at the inline-end (left under RTL), so the chevron
            points the way the panel will move. */}
        {showSidebar ? (
          <ChevronLeft className="w-4 h-4 text-muted-foreground" aria-hidden="true" />
        ) : (
          <ChevronRight className="w-4 h-4 text-muted-foreground" aria-hidden="true" />
        )}
      </button>
    </div>
  );
};

export default CourseWatch;
