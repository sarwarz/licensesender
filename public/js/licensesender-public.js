(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	jQuery(document).ready(function ($) {


		//for bulk action
		$(document).on('click', '.ls-get-all-keys-btn', function (e) {
		    e.preventDefault();

		    const $btn = $(this);
		    const orderId = $btn.data('order-id');

		    // Target only visible "Get Key" buttons in this order
		    const $rows = $(`.ls-view-license-btn[data-order-id="${orderId}"]:visible`);

		    if ($rows.length === 0) {
		        Swal.fire('Nothing to Fetch', 'All license keys have already been retrieved.', 'info');
		        return;
		    }

		    Swal.fire({
		        title: ls_ajax_object.bulkTitle || 'Fetch All Keys?',
		        text: ls_ajax_object.bulkText || 'This will retrieve all license keys for this order.',
		        icon: 'warning',
		        showCancelButton: true,
		        confirmButtonText: ls_ajax_object.bulkConfirmBtn || 'Yes, fetch all',
		        cancelButtonText: ls_ajax_object.bulkCancelBtn || 'Cancel',
		        confirmButtonColor: ls_ajax_object.confirmColor || '#4f46e5',
		        cancelButtonColor: ls_ajax_object.cancelColor || '#6b7280'
		    }).then((result) => {
		        if (!result.isConfirmed) return;

		        // Update UI state
		        $btn.prop('disabled', true);
		        $btn.find('.ls-btn-text').text('Fetching...');
		        $btn.addClass('is-loading');

		        const rows = $rows.toArray();

		        function processNext(index) {
		            if (index >= rows.length) {
		                $btn.prop('disabled', false);
		                $btn.find('.ls-btn-text').text('Get All Keys');
		                $btn.removeClass('is-loading');

		                Swal.fire(
		                    ls_ajax_object.bulkDoneTitle || 'Done!',
		                    ls_ajax_object.bulkDoneText || 'All license keys have been processed.',
		                    'success'
		                );
		                return;
		            }

		            const $btnEl = $(rows[index]);
		            const productId = $btnEl.data('product-id');
		            const email = $btnEl.data('email');
		            const qnty = $btnEl.data('order-qnty');
		            const $row = $btnEl.closest('tr');
		            const $detailsRow = $row.next('.ls-license-details-row');
		            const $content = $detailsRow.find('.ls-license-details-content');

		            $detailsRow.slideDown();
		            $content.html('<p class="ls-loading">Fetching license...</p>');

		            $.ajax({
		                url: ls_ajax_object.ajax_url,
		                method: 'POST',
		                dataType: 'json',
		                data: {
		                    action: 'ls_fetch_license',
		                    order_id: orderId,
		                    product_id: productId,
		                    email: email,
		                    qnty: qnty,
		                    order_key: $btnEl.data('order-key') || '',
		                    _ajax_nonce: ls_ajax_object.nonce
		                },
		                success: function (res) {
		                    if (res.success) {
		                        $content.html(res.data.html);

		                        const viewBtn = $('<button>')
		                            .addClass('button wp-element-button ls-toggle-license-btn')
		                            .text('View Key');

		                        $btnEl.replaceWith(viewBtn);
		                    } else {
		                        $content.html(lsBuildErrorHtml(res));
		                    }
		                },
		                error: function (xhr, status, errorThrown) {
		                    const r = xhr.responseJSON || {};
							$content.html(lsBuildErrorHtml(r));

		                },
		                complete: function () {
		                    // Process the next product after slight delay
		                    setTimeout(() => processNext(index + 1), 300);
		                }
		            });
		        }

		        // Start the first fetch
		        processNext(0);
		    });
		});


		


		//for view button (delegated for dynamic rows)
	    $(document).on('click', '.ls-view-license-btn', function (e) {
	        e.preventDefault();

	        let button = $(this);
	        let orderId = button.data('order-id');
	        let productId = button.data('product-id');
			let product   = button.data('product-name');
	        let email = button.data('email');
	        let qnty = button.data('order-qnty');
	        let orderKey = button.data('order-key') || '';
	        let row = button.closest('tr').next('.ls-license-details-row');
	        let content = row.find('.ls-license-details-content');
			
			// Replace {product} placeholder
    		let confirmText = ls_ajax_object.confirmText.replace('{product}', product);

	        Swal.fire({
	            title: ls_ajax_object.confirmTitle,
	            text:  confirmText,
	            icon: 'question',
	            showCancelButton: true,
	            confirmButtonText: ls_ajax_object.confirmBtn || 'Yes, fetch',
	            cancelButtonText: ls_ajax_object.cancelBtn || 'Cancel',
	            confirmButtonColor: ls_ajax_object.confirmColor || '#4f46e5',
	            cancelButtonColor: ls_ajax_object.cancelColor || '#6b7280',
	        }).then((result) => {
	            if (result.isConfirmed) {
	                // Show loading row
	                row.show();
	                content.html('<p class="ls-loading">Loading license...</p>');

	                $.ajax({
	                    url: ls_ajax_object.ajax_url,
	                    type: 'POST',
	                    dataType: 'json',
	                    data: {
	                        action: 'ls_fetch_license',
	                        order_id: orderId,
	                        product_id: productId,
	                        email: email,
	                        qnty: qnty,
	                        order_key: orderKey,
	                        _ajax_nonce: ls_ajax_object.nonce
	                    },
	                    success: function (response) {
	                        if (response.success) {
	                            content.html(response.data.html);

	                            const viewBtn = $('<button>').addClass('button wp-element-button ls-toggle-license-btn').text('View Key');
	                        	button.replaceWith(viewBtn);

	                        } else {
	                            content.html(lsBuildErrorHtml(response));
	                        }
	                    },
	                    error: function (xhr, status, errorThrown) {
	                        const r = xhr.responseJSON || {};
							content.html(lsBuildErrorHtml(r));

	                    }
	                });
	            }
	        });
	    });


	    function lsBuildErrorHtml(res) {

		    // Escape helper
		    const esc = (s) => String(s)
		        .replace(/&/g, '&amp;')
		        .replace(/</g, '&lt;')
		        .replace(/>/g, '&gt;');

		    // Never inject remote HTML — messages are escaped text only.
		    const msgRaw = (res && res.data && res.data.message) ? String(res.data.message) : 'Request failed.';
		    const meta   = (res && res.data && res.data.meta) ? res.data.meta : {};
		    const reason = (typeof meta?.reason === 'string') ? meta.reason.trim() : '';
		    const scope  = (typeof meta?.scope  === 'string') ? meta.scope.trim()  : '';

		    let finalMsg = msgRaw;
		    let msgLower = finalMsg.toLowerCase();

		    // Append reason if not already present
		    if (reason && !msgLower.includes('reason:')) {
		        finalMsg += ' ' + 'Reason: ' + reason;
		        msgLower = finalMsg.toLowerCase();
		    }

		    // Append scope tag if not already present
		    if (scope) {
		        const scopeTag = `[${scope.charAt(0).toUpperCase() + scope.slice(1)} block]`;
		        if (!msgLower.includes(' block]') && !msgLower.includes(scopeTag.toLowerCase())) {
		            finalMsg += ' ' + scopeTag;
		        }
		    }

		    return '<div class="notice notice-error ls-custom-notice"><p>' + esc(finalMsg) + '</p></div>';
		}



	    //for toggle
	    $(document).on('click', '.ls-toggle-license-btn', function () {
	        let $btn = $(this);
	        let $licenseRow = $btn.closest('tr').next('.ls-license-details-row');

	        $licenseRow.slideToggle(300);
	    });

	    //for copy button
		$(document).on('click', '.ls-copy-license-btn', function () {
		    const $btn = $(this);
		    const licenseKey = $btn.closest('tr').find('code.ls-license-key').text().trim();

		    const showCopied = function () {
		        Swal.fire({
		            toast: true,
		            position: 'top-end',
		            icon: 'success',
		            title: 'License key copied!',
		            showConfirmButton: false,
		            timer: 1500,
		            timerProgressBar: true,
		            background: '#32ab13',
		            color: '#fff'
		        });
		    };

		    if (navigator.clipboard && navigator.clipboard.writeText) {
		        navigator.clipboard.writeText(licenseKey).then(showCopied);
		        return;
		    }

		    const $temp = $('<input>');
		    $('body').append($temp);
		    $temp.val(licenseKey).select();
		    document.execCommand('copy');
		    $temp.remove();
		    showCopied();
		});


		$(document).on('click', '.ls-show-more-btn', function () {
			$(this).closest('table').find('tr.ls-hidden-row').slideDown();
			$(this).hide().siblings('.ls-show-less-btn').show();
		});

		$(document).on('click', '.ls-show-less-btn', function () {
			const $table = $(this).closest('table');
			$table.find('tr.ls-hidden-row').slideUp();
			$(this).hide().siblings('.ls-show-more-btn').show();
		});



	   

	});




})( jQuery );
