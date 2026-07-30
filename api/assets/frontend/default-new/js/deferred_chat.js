/**
 * PERFORMANCE OPTIMIZED - Interaction-Based Chat Widget Loader
 * Loads chat widget on first user interaction or after 10 seconds
 * Reduces network dependency chain and improves perceived performance
 */
(function () {
  "use strict";

  function loadChatWidget() {
    // Prevent multiple loads
    if (window.chatWidgetLoaded) return;
    window.chatWidgetLoaded = true;

    console.log("Loading chat widget...");

    // Load chat widget script
    var chatScript = document.createElement("script");
    chatScript.src = "https://app.chaticmedia.com/webchat/plugin.js?v=6";
    chatScript.async = true;

    // ADVANCED FIX: Monkey Patch DOM methods to intercept chat elements BEFORE they are added
    // This ensures elements enter the DOM with correct attributes, satisfying Lighthouse immediately.

    function fixChatElement(node) {
      if (!node || !node.tagName) return;

      // Fix Iframe Title
      if (
        node.tagName === "IFRAME" &&
        (node.id === "ktt10-iframe" || node.src.includes("chaticmedia"))
      ) {
        node.setAttribute("title", "Live chat support");
      }

      // Fix Close Icon Alt via searching within added nodes
      if (node.querySelector) {
        var closeIcon = node.querySelector("span.ktt10-close > img");
        if (closeIcon && !closeIcon.hasAttribute("alt")) {
          closeIcon.setAttribute("alt", "Close Chat");
        }
      }

      // Also check if the node itself is the image
      if (
        node.tagName === "IMG" &&
        node.parentNode &&
        node.parentNode.classList.contains("ktt10-close")
      ) {
        node.setAttribute("alt", "Close Chat");
      }
    }

    // Intercept appendChild
    var originalAppendChild = Element.prototype.appendChild;
    Element.prototype.appendChild = function (node) {
      fixChatElement(node);
      return originalAppendChild.call(this, node);
    };

    // Intercept insertBefore
    var originalInsertBefore = Element.prototype.insertBefore;
    Element.prototype.insertBefore = function (node, referenceNode) {
      fixChatElement(node);
      return originalInsertBefore.call(this, node, referenceNode);
    };

    // Keep MutationObserver as a fallback backup
    var observer = new MutationObserver(function (mutations) {
      var closeIcon = document.querySelector(
        "div.ktt10-flt > span.ktt10-close > img"
      );
      var iframe = document.getElementById("ktt10-iframe");
      if (iframe && !iframe.hasAttribute("title"))
        iframe.setAttribute("title", "Live chat support");
      if (closeIcon && !closeIcon.hasAttribute("alt"))
        closeIcon.setAttribute("alt", "Close Chat");
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.body.appendChild(chatScript);

    // Load chat widget CSS
    var chatStyle = document.createElement("link");
    chatStyle.rel = "stylesheet";
    chatStyle.href = "https://app.chaticmedia.com/webchat//plugin.css?v=5";
    document.head.appendChild(chatStyle);
  }

  // Load on first user interaction
  const interactionEvents = ["mousemove", "scroll", "touchstart", "keydown"];
  interactionEvents.forEach(function (event) {
    window.addEventListener(event, loadChatWidget, {
      once: true,
      passive: true,
    });
  });

  // Fallback: Load after 10 seconds if no interaction
  setTimeout(loadChatWidget, 10000);
})();
