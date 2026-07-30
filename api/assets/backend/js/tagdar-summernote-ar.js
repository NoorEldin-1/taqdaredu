/**
 * Make Arabic the default language for every Summernote editor in the panel.
 *
 * Summernote draws its own toolbar, dropdowns and dialogs from a `lang` bundle
 * rather than from the app's get_phrase() translations, so the editor kept
 * showing "Transparent", "Select", "Reset to default", "Text to display" and
 * the rest in English on an otherwise Arabic page.
 *
 * The ar-AR bundle ships with the vendor's own Summernote dist; it is copied to
 * assets/backend/js/vendor/ and loaded just before this file. Setting the
 * default here covers every editor without touching the views that create them.
 * A view that passes its own `lang` still wins, which is the intended override.
 *
 * Must load AFTER summernote-bs4.min.js + summernote-ar-AR.min.js and BEFORE
 * any editor is initialised.
 */
(function () {
  if (!window.jQuery || !jQuery.summernote) return;

  // Only switch if the bundle actually registered; otherwise leave English
  // rather than blanking the toolbar labels.
  if (jQuery.summernote.lang && jQuery.summernote.lang['ar-AR']) {
    jQuery.extend(jQuery.summernote.options, { lang: 'ar-AR' });
  }
})();
