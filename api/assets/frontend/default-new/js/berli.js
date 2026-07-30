/*
 * File: jquery.barfiller.js
 * Version: 1.0.2 (Performance Optimized)
 * Description: A plugin that fills bars with a percentage you set.
 * Author: 9bit Studios
 * Copyright 2012, 9bit Studios
 * http://www.9bitstudios.com
 * Free to use and abuse under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 *
 * PERFORMANCE OPTIMIZATION: Refactored to eliminate forced reflows
 * - Caches element dimensions to avoid repeated reads
 * - Uses requestAnimationFrame for resize handling
 * - Batches DOM reads before writes
 */

(function ($) {
  $.fn.barfiller = function (options) {
    var defaults = $.extend(
      {
        barColor: "blue",
        tooltip: true,
        duration: 2000,
        animateOnResize: true,
        symbol: "%",
      },
      options
    );

    /******************************
        Private Variables
        *******************************/

    var object = $(this);
    var settings = $.extend(defaults, options);
    var fill = object.find(".fill");
    var toolTip = object.find(".tip");
    var fillPercentage = fill.attr("data-percentage");
    var resizeTimeout;
    var transitionSupport = false;
    var transitionPrefix;

    // OPTIMIZATION: Cache dimensions to avoid forced reflows
    var cachedBarWidth = 0;
    var cachedToolTipWidth = 0;

    /******************************
        Public Methods
        *******************************/

    var methods = {
      init: function () {
        return this.each(function () {
          if (methods.getTransitionSupport()) {
            transitionSupport = true;
            transitionPrefix = methods.getTransitionPrefix();
          }

          methods.appendHTML();
          methods.setEventHandlers();
          methods.initializeItems();
        });
      },

      /******************************
            Append HTML
            *******************************/

      appendHTML: function () {
        fill.css("background", settings.barColor);

        if (!settings.tooltip) {
          toolTip.css("display", "none");
        }
        toolTip.text(fillPercentage + settings.symbol);
      },

      /******************************
            Set Event Handlers
            *******************************/
      setEventHandlers: function () {
        if (settings.animateOnResize) {
          // OPTIMIZATION: Use requestAnimationFrame for resize handling
          $(window).on("resize", function (event) {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
              requestAnimationFrame(function () {
                methods.refill();
              });
            }, 300);
          });
        }
      },

      /******************************
            Initialize
            *******************************/

      initializeItems: function () {
        // OPTIMIZATION: Batch all DOM reads first
        cachedBarWidth = object.width();
        cachedToolTipWidth = toolTip.width();

        var pctWidth = methods.calculateFill(fillPercentage);
        object.find(".tipWrap").css({ display: "inline" });

        if (transitionSupport) methods.transitionFill(pctWidth);
        else methods.animateFill(pctWidth);
      },

      getTransitionSupport: function () {
        var thisBody = document.body || document.documentElement,
          thisStyle = thisBody.style;
        var support =
          thisStyle.transition !== undefined ||
          thisStyle.WebkitTransition !== undefined ||
          thisStyle.MozTransition !== undefined ||
          thisStyle.MsTransition !== undefined ||
          thisStyle.OTransition !== undefined;
        return support;
      },

      getTransitionPrefix: function () {
        if (
          /mozilla/.test(navigator.userAgent.toLowerCase()) &&
          !/webkit/.test(navigator.userAgent.toLowerCase())
        ) {
          return "-moz-transition";
        }
        if (/webkit/.test(navigator.userAgent.toLowerCase())) {
          return "-webkit-transition";
        }
        if (/opera/.test(navigator.userAgent.toLowerCase())) {
          return "-o-transition";
        }
        if (/msie/.test(navigator.userAgent.toLowerCase())) {
          return "-ms-transition";
        } else {
          return "transition";
        }
      },

      getTransition: function (val, time, type) {
        var CSSObj;
        if (type === "width") {
          CSSObj = { width: val };
        } else if (type === "left") {
          CSSObj = { left: val };
        }

        time = time / 1000;
        CSSObj[transitionPrefix] = type + " " + time + "s ease-in-out";
        return CSSObj;
      },

      refill: function () {
        // OPTIMIZATION: Batch writes, then read new dimensions
        fill.css("width", 0);
        toolTip.css("left", 0);

        // Read new dimensions after DOM settles
        requestAnimationFrame(function () {
          cachedBarWidth = object.width();
          cachedToolTipWidth = toolTip.width();
          methods.initializeItems();
        });
      },

      calculateFill: function (percentage) {
        percentage = percentage * 0.01;
        // OPTIMIZATION: Use cached width instead of re-reading
        var finalWidth = cachedBarWidth * percentage;
        return finalWidth;
      },

      transitionFill: function (barWidth) {
        // OPTIMIZATION: Use cached tooltip width to avoid forced reflow
        var toolTipOffset = barWidth - cachedToolTipWidth;
        fill.css(methods.getTransition(barWidth, settings.duration, "width"));
        toolTip.css(
          methods.getTransition(toolTipOffset, settings.duration, "left")
        );
      },

      animateFill: function (barWidth) {
        // OPTIMIZATION: Use cached tooltip width to avoid forced reflow
        var toolTipOffset = barWidth - cachedToolTipWidth;
        fill.stop().animate({ width: "+=" + barWidth }, settings.duration);
        toolTip
          .stop()
          .animate({ left: "+=" + toolTipOffset }, settings.duration);
      },
    };

    if (methods[options]) {
      // $("#element").pluginName('methodName', 'arg1', 'arg2');
      return methods[options].apply(
        this,
        Array.prototype.slice.call(arguments, 1)
      );
    } else if (typeof options === "object" || !options) {
      // $("#element").pluginName({ option: 1, option:2 });
      return methods.init.apply(this);
    } else {
      $.error('Method "' + method + '" does not exist in barfiller plugin!');
    }
  };
})(jQuery);
