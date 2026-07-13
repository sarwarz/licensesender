jQuery(document).ready(function ($) {

   /** =========================================================
    *  1️ Constants & Toast Configuration
    * ========================================================= */
   const ajaxUrl = ls_ajax_object.ajax_url;
   const manageNonce = ls_ajax_object.nonce || '';

   // Global reusable SweetAlert Toast
   const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 1800,
      timerProgressBar: true,
      didOpen: (t) => {
         t.addEventListener('mouseenter', Swal.stopTimer);
         t.addEventListener('mouseleave', Swal.resumeTimer);
      },
   });

   /** =========================================================
    *  2️ DataTable Initialization
    * ========================================================= */
   const table = $('#ls-download-links-table').DataTable({
      ajax: {
         url: ajaxUrl,
         type: 'POST',
         data: {
            action: 'ls_get_download_links',
            nonce: manageNonce
         },
      },
      columns: [{
            data: 'id'
         },
         {
            data: 'product_name'
         },
         {
            data: 'link'
         },
         {
            data: 'actions',
            orderable: false,
            searchable: false
         },
      ],
      searching: true,
      responsive: true,
   });

   /** =========================================================
    *  3️ Select2 Initialization Helper
    * ========================================================= */
   function initSelect2() {
      const $field = $('#ls-product-id');
      if ($field.hasClass('select2-hidden-accessible')) {
         $field.select2('destroy');
      }

      $field.select2({
         placeholder: 'Select a product...',
         dropdownParent: $('#TB_window'),
         width: '100%',
      });
   }

   /** =========================================================
    *  4️ Add New Record
    * ========================================================= */
   $('#ls-add-link-btn').on('click', function (e) {
      e.preventDefault();

      // Reset form
      $('#ls-id').val('');
      $('#ls-link').val('');
      $('#ls-product-id').val('').trigger('change');

      // Show Thickbox modal
      tb_show('Add Download Link', '#TB_inline?width=500&height=320&inlineId=ls-download-modal');
      setTimeout(initSelect2, 100);
   });

   /** =========================================================
    *  5️ Edit Record
    * ========================================================= */
   $(document).on('click', '.ls-edit-link', function (e) {
      e.preventDefault();
      const id = $(this).data('id');

      $.post(ajaxUrl, {
         action: 'ls_get_single_download_link',
         id,
         nonce: manageNonce
      }, function (res) {
         if (res.success) {
            $('#ls-id').val(res.data.id);
            $('#ls-link').val(res.data.link);
            $('#ls-product-id').val(res.data.product_id).trigger('change');

            tb_show('Edit Download Link', '#TB_inline?width=500&height=320&inlineId=ls-download-modal');
            setTimeout(initSelect2, 100);
         } else {
            Toast.fire({
               icon: 'error',
               title: res.data || 'Failed to load record.'
            });
         }
      }).fail(() => {
         Toast.fire({
            icon: 'error',
            title: 'Network error.'
         });
      });
   });

   /** =========================================================
    *  6️ Save (Add / Update)
    * ========================================================= */
   $('#ls-download-form').on('submit', function (e) {
      e.preventDefault();

      const $form = $(this);
      const $btn = $form.find('button[type="submit"]');

      // Disable button to prevent double submission
      $btn.prop('disabled', true).text('Saving...');

      $.post(ajaxUrl, $form.serialize() + '&action=ls_save_download_link&nonce=' + encodeURIComponent(manageNonce), function (res) {
         $btn.prop('disabled', false).text('Save');

         if (res.success) {
            tb_remove(); // close modal
            table.ajax.reload();
            Toast.fire({
               icon: 'success',
               title: res.data || 'Saved successfully.'
            });

         } else if (typeof res.data === 'string' && res.data.includes('already exists')) {
            Toast.fire({
               icon: 'warning',
               title: 'A link already exists for this product.'
            });

         } else {
            Toast.fire({
               icon: 'error',
               title: res.data || 'Something went wrong.'
            });
         }
      }).fail(() => {
         $btn.prop('disabled', false).text('Save');
         Toast.fire({
            icon: 'error',
            title: 'Could not connect to the server.'
         });
      });
   });

   /** =========================================================
    *  7️ Delete Record
    * ========================================================= */
   $(document).on('click', '.ls-delete-link', function (e) {
      e.preventDefault();
      const id = $(this).data('id');

      Swal.fire({
         icon: 'warning',
         title: 'Delete this record?',
         text: 'This action cannot be undone.',
         showCancelButton: true,
         confirmButtonText: 'Delete',
         cancelButtonText: 'Cancel',
         confirmButtonColor: '#d33',
      }).then((result) => {
         if (!result.isConfirmed) return;

         $.post(ajaxUrl, {
            action: 'ls_delete_download_link',
            id,
            nonce: manageNonce
         }, function (res) {
            if (res.success) {
               table.ajax.reload();
               Toast.fire({
                  icon: 'success',
                  title: 'Deleted successfully.'
               });
            } else {
               Toast.fire({
                  icon: 'error',
                  title: res.data || 'Delete failed.'
               });
            }
         }).fail(() => {
            Toast.fire({
               icon: 'error',
               title: 'Network error.'
            });
         });
      });
   });

   /** =========================================================
    *  8️ Modal Cleanup (Select2 + Memory)
    * ========================================================= */
   $(document).on('tb_unload', function () {
      const $field = $('#ls-product-id');
      if ($field.hasClass('select2-hidden-accessible')) {
         $field.select2('destroy');
      }
   });

   /** =========================================================
    *  9️ Cancel Button (Close Modal)
    * ========================================================= */
   $(document).on('click', '.ls-modal-close', function (e) {
      e.preventDefault();
      tb_remove();
   });

});