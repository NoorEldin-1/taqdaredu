import { Lock, Play, FileText, Download } from "lucide-react";
import { Lesson } from "@/lib/api/types";
import QuizPlayer from "@/components/course/QuizPlayer";

interface LessonVideoPlayerProps {
  lesson: Lesson | null;
  fallbackThumbnail?: string;
  isEnrolled?: boolean;
}

/**
 * Extracts a Vimeo numeric ID from any of these URL shapes:
 *   https://vimeo.com/1175467015
 *   https://vimeo.com/1175467015?fl=pl&fe=sh
 *   https://player.vimeo.com/video/1175467015
 */
function extractVimeoId(url: string): string | null {
  const m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  return m ? m[1] : null;
}

/**
 * Extracts a YouTube video ID from common URL shapes.
 */
function extractYouTubeId(url: string): string | null {
  const patterns = [
    /youtu\.be\/([A-Za-z0-9_-]{6,})/,
    /youtube\.com\/watch\?v=([A-Za-z0-9_-]{6,})/,
    /youtube\.com\/embed\/([A-Za-z0-9_-]{6,})/,
  ];
  for (const p of patterns) {
    const m = url.match(p);
    if (m) return m[1];
  }
  return null;
}

/**
 * Renders the correct player for the current lesson.
 *
 * - No lesson selected → placeholder.
 * - Lesson has no video_url (locked / paid for non-enrolled users) → lock screen.
 * - Vimeo / YouTube URLs → embedded iframe with proper player.
 * - Direct media URLs (.mp4, .webm, …) → HTML5 <video> tag.
 *
 * This replaces the previous static thumbnail+fake-play-button UI which made
 * every lesson look like the course preview image.
 */
const LessonVideoPlayer = ({ lesson, fallbackThumbnail, isEnrolled }: LessonVideoPlayerProps) => {
  // Quiz / exam lesson → interactive quiz player (questions, submit, score).
  if (lesson && lesson.type === "quiz") {
    return <QuizPlayer lessonId={lesson.id} />;
  }

  // No lesson loaded yet
  if (!lesson) {
    return (
      <div className="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-b from-navy/40 to-navy/90">
        {fallbackThumbnail && (
          <img src={fallbackThumbnail} alt="" className="absolute inset-0 w-full h-full object-cover opacity-30" />
        )}
        <div className="relative z-10 text-center text-white/70">
          <Play className="w-12 h-12 mx-auto mb-3 opacity-50" />
          <p className="text-lg">Select a lesson from the sidebar to begin</p>
        </div>
      </div>
    );
  }

  // ---- Video lesson ----
  if (lesson.video_url) {
    const videoType = (lesson.video_type || "").toLowerCase();
    const url = lesson.video_url;

    // Vimeo embed
    if (videoType === "vimeo" || url.includes("vimeo.com")) {
      const id = extractVimeoId(url);
      if (id) {
        return (
          <iframe
            key={lesson.id}
            src={`https://player.vimeo.com/video/${id}?dnt=1&title=0&byline=0&portrait=0`}
            className="absolute inset-0 w-full h-full"
            allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
            allowFullScreen
            title={lesson.title}
          />
        );
      }
    }

    // YouTube embed
    if (videoType === "youtube" || url.includes("youtube.com") || url.includes("youtu.be")) {
      const id = extractYouTubeId(url);
      if (id) {
        return (
          <iframe
            key={lesson.id}
            src={`https://www.youtube.com/embed/${id}?rel=0&modestbranding=1`}
            className="absolute inset-0 w-full h-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            title={lesson.title}
          />
        );
      }
    }

    // Direct video file (HTML5)
    return (
      <video
        key={lesson.id}
        src={url}
        controls
        controlsList="nodownload"
        poster={fallbackThumbnail}
        className="absolute inset-0 w-full h-full bg-black"
      >
        Your browser does not support the video tag.
      </video>
    );
  }

  // ---- Non-video resource lesson (PDF / slides / document) ----
  if (lesson.attachment_url) {
    const at = (lesson.attachment_type || "").toLowerCase();
    const isPdf = at.includes("pdf") || /\.pdf($|\?)/i.test(lesson.attachment_url);
    if (isPdf) {
      return (
        <iframe
          key={lesson.id}
          src={lesson.attachment_url}
          className="absolute inset-0 w-full h-full bg-white"
          title={lesson.title}
        />
      );
    }
    // Non-PDF file → offer to open / download.
    return (
      <div className="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-b from-navy/60 to-navy/95 px-6 text-center">
        <FileText className="w-12 h-12 mb-3 text-primary" />
        <h3 className="text-xl font-semibold text-white mb-1">{lesson.title}</h3>
        <p className="text-white/60 mb-4">Course resource — open or download to view.</p>
        <a
          href={lesson.attachment_url}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 font-medium text-white hover:bg-primary/90 transition-colors"
        >
          <Download className="w-4 h-4" /> Open resource
        </a>
      </div>
    );
  }

  // ---- Text / article lesson (content stored in summary) ----
  const articleHtml = (lesson.summary || "").trim();
  if (articleHtml) {
    return (
      <div className="absolute inset-0 overflow-y-auto bg-navy">
        <article
          className="prose prose-invert max-w-3xl mx-auto p-6 md:p-10 text-white/90"
          dangerouslySetInnerHTML={{ __html: articleHtml }}
        />
      </div>
    );
  }

  // ---- Locked (not enrolled) or genuinely empty lesson ----
  return (
    <div className="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-b from-navy/60 to-navy/95">
      {fallbackThumbnail && (
        <img src={fallbackThumbnail} alt="" className="absolute inset-0 w-full h-full object-cover opacity-20" />
      )}
      <div className="relative z-10 text-center text-white/80 px-6">
        <Lock className="w-12 h-12 mx-auto mb-3 text-primary" />
        <h3 className="text-xl font-semibold mb-2">{lesson.title}</h3>
        <p className="text-white/60">
          {isEnrolled
            ? "No playable content has been added to this lesson yet."
            : "Enroll in this course to watch this lesson."}
        </p>
      </div>
    </div>
  );
};

export default LessonVideoPlayer;
