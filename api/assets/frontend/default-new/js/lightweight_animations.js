/**
 * Lightweight Animation System - Replacement for WOW.js
 * Uses IntersectionObserver for GPU-accelerated animations
 * ~1KB minified vs 8.4KB for WOW.js
 */
(function () {
  "use strict";

  // Check for IntersectionObserver support
  if (!("IntersectionObserver" in window)) {
    // Fallback: Show all elements immediately for older browsers
    document.addEventListener("DOMContentLoaded", function () {
      document
        .querySelectorAll(".animate__animated, .wow")
        .forEach(function (el) {
          el.classList.add("animated");
          el.style.opacity = "1";
        });
    });
    return;
  }

  // Create observer with optimized settings
  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var target = entry.target;
          var delay = target.getAttribute("data-wow-delay") || "0ms";

          // Apply delay if specified
          if (delay !== "0ms") {
            setTimeout(function () {
              target.classList.add("animated");
            }, parseFloat(delay));
          } else {
            target.classList.add("animated");
          }

          // Stop observing this element
          observer.unobserve(target);
        }
      });
    },
    {
      threshold: 0.1, // Trigger when 10% visible
      rootMargin: "0px 0px -50px 0px", // Trigger slightly before element enters viewport
    }
  );

  // Initialize on DOM ready
  function initAnimations() {
    // Find all elements with animation classes
    var elements = document.querySelectorAll(
      ".animate__animated, .wow, .opacityOnUp"
    );

    elements.forEach(function (el) {
      // Add base class for CSS transitions
      if (!el.classList.contains("animate__animated")) {
        el.classList.add("animate__animated");
      }

      // Start observing
      observer.observe(el);
    });
  }

  // Run on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAnimations);
  } else {
    initAnimations();
  }
})();
