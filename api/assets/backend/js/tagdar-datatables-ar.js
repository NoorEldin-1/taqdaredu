/**
 * Arabic + RTL defaults for every DataTable in the admin panel.
 *
 * DataTables renders its own chrome — the search box, pagination, "showing X of
 * Y", the empty-table message — from a `language` object, not from the app's
 * get_phrase() translations. So even with the panel fully Arabic those strings
 * stayed English on every table.
 *
 * Setting the defaults once here covers all of them without touching the ~152
 * view files that each call .DataTable(). Views that pass their own `language`
 * object still win, which is the intended override path.
 *
 * Must load AFTER jquery.dataTables.min.js and BEFORE the initialisers.
 */
(function () {
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.dataTable) return;

  jQuery.extend(true, jQuery.fn.dataTable.defaults, {
    language: {
      emptyTable: 'لا توجد بيانات في الجدول',
      info: 'إظهار _START_ إلى _END_ من أصل _TOTAL_ سجل',
      infoEmpty: 'لا توجد سجلات لعرضها',
      infoFiltered: '(منقّاة من إجمالي _MAX_ سجل)',
      lengthMenu: 'إظهار _MENU_ سجل',
      loadingRecords: 'جارٍ التحميل…',
      processing: 'جارٍ المعالجة…',
      search: 'بحث:',
      zeroRecords: 'لم يُعثر على سجلات مطابقة',
      paginate: {
        first: 'الأول',
        last: 'الأخير',
        next: 'التالي',
        previous: 'السابق'
      },
      aria: {
        sortAscending: ': تفعيل لترتيب العمود تصاعدياً',
        sortDescending: ': تفعيل لترتيب العمود تنازلياً'
      }
    }
  });
})();
