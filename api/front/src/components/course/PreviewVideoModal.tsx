import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";

interface PreviewVideoModalProps {
  open: boolean;
  onClose: () => void;
  videoUrl: string | null;
  videoType?: string | null;
  title?: string;
}

function extractVimeoId(url: string): string | null {
  const m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  return m ? m[1] : null;
}

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
 * Renders the right player for the given URL inside a centered modal.
 * Used in CourseDetails for both "Preview this course" (course-level video)
 * and per-lesson Preview badges (free lessons in the curriculum).
 * Closing the dialog unmounts the iframe, which stops playback automatically.
 */
const PreviewVideoModal = ({ open, onClose, videoUrl, videoType, title }: PreviewVideoModalProps) => {
  const renderPlayer = () => {
    if (!videoUrl) return null;

    const t = (videoType || "").toLowerCase();
    const url = videoUrl;

    if (t === "vimeo" || url.includes("vimeo.com")) {
      const id = extractVimeoId(url);
      if (id) {
        return (
          <iframe
            src={`https://player.vimeo.com/video/${id}?dnt=1&autoplay=1&title=0&byline=0&portrait=0`}
            className="absolute inset-0 w-full h-full"
            allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
            allowFullScreen
            title={title || "Course preview"}
          />
        );
      }
    }

    if (t === "youtube" || url.includes("youtube.com") || url.includes("youtu.be")) {
      const id = extractYouTubeId(url);
      if (id) {
        return (
          <iframe
            src={`https://www.youtube.com/embed/${id}?rel=0&autoplay=1&modestbranding=1`}
            className="absolute inset-0 w-full h-full"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            title={title || "Course preview"}
          />
        );
      }
    }

    return (
      <video
        src={url}
        controls
        autoPlay
        controlsList="nodownload"
        className="absolute inset-0 w-full h-full bg-black"
      >
        Your browser does not support the video tag.
      </video>
    );
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-4xl p-0 bg-black border-0 overflow-hidden">
        <DialogTitle className="sr-only">{title || "Course preview"}</DialogTitle>
        <div className="relative aspect-video w-full bg-black">{renderPlayer()}</div>
      </DialogContent>
    </Dialog>
  );
};

export default PreviewVideoModal;
