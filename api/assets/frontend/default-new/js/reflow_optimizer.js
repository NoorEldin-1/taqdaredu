/**
 * REFLOW OPTIMIZER UTILITY
 * Prevents forced reflows by batching DOM reads and writes
 * Uses requestAnimationFrame to schedule layout operations efficiently
 *
 * Usage:
 *   ReflowOptimizer.read(() => {
 *     const width = element.offsetWidth;
 *     return width;
 *   }).then(width => {
 *     ReflowOptimizer.write(() => {
 *       element.style.width = width + 'px';
 *     });
 *   });
 */

(function (window) {
  "use strict";

  const ReflowOptimizer = {
    // Queues for batching operations
    readQueue: [],
    writeQueue: [],
    scheduled: false,

    /**
     * Schedule a DOM read operation
     * @param {Function} fn - Function that reads from DOM
     * @returns {Promise} - Resolves with the return value of fn
     */
    read: function (fn) {
      return new Promise((resolve) => {
        this.readQueue.push({
          fn: fn,
          resolve: resolve,
        });
        this.scheduleFlush();
      });
    },

    /**
     * Schedule a DOM write operation
     * @param {Function} fn - Function that writes to DOM
     * @returns {Promise} - Resolves when write is complete
     */
    write: function (fn) {
      return new Promise((resolve) => {
        this.writeQueue.push({
          fn: fn,
          resolve: resolve,
        });
        this.scheduleFlush();
      });
    },

    /**
     * Schedule the flush of queued operations
     */
    scheduleFlush: function () {
      if (!this.scheduled) {
        this.scheduled = true;
        requestAnimationFrame(() => this.flush());
      }
    },

    /**
     * Execute all queued operations in optimal order
     * All reads first, then all writes to prevent layout thrashing
     */
    flush: function () {
      // Execute all reads first
      const readResults = [];
      while (this.readQueue.length > 0) {
        const operation = this.readQueue.shift();
        try {
          const result = operation.fn();
          operation.resolve(result);
          readResults.push(result);
        } catch (error) {
          console.error("ReflowOptimizer read error:", error);
          operation.resolve(null);
        }
      }

      // Then execute all writes
      while (this.writeQueue.length > 0) {
        const operation = this.writeQueue.shift();
        try {
          operation.fn();
          operation.resolve();
        } catch (error) {
          console.error("ReflowOptimizer write error:", error);
          operation.resolve();
        }
      }

      this.scheduled = false;
    },

    /**
     * Debounced resize handler
     * @param {Function} callback - Function to call on resize
     * @param {Number} delay - Debounce delay in ms (default: 150)
     * @returns {Function} - Debounced function
     */
    debounceResize: function (callback, delay) {
      delay = delay || 150;
      let timeout;
      return function () {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          requestAnimationFrame(() => {
            callback.apply(context, args);
          });
        }, delay);
      };
    },

    /**
     * Cache element dimensions to avoid repeated reads
     * @param {Element} element - DOM element
     * @returns {Object} - Cached dimensions {width, height}
     */
    cacheDimensions: function (element) {
      if (!element) return { width: 0, height: 0 };

      return this.read(() => {
        return {
          width: element.offsetWidth,
          height: element.offsetHeight,
          clientWidth: element.clientWidth,
          clientHeight: element.clientHeight,
        };
      });
    },

    /**
     * Get element dimensions synchronously (only use during initialization)
     * @param {Element} element - DOM element
     * @returns {Object} - Dimensions {width, height}
     */
    getDimensionsSync: function (element) {
      if (!element) return { width: 0, height: 0 };
      return {
        width: element.offsetWidth,
        height: element.offsetHeight,
        clientWidth: element.clientWidth,
        clientHeight: element.clientHeight,
      };
    },
  };

  // Export to global scope
  window.ReflowOptimizer = ReflowOptimizer;

  // Also export for jQuery plugins
  if (window.jQuery) {
    window.jQuery.ReflowOptimizer = ReflowOptimizer;
  }
})(window);
