/* global jQuery, LSLicensesDT */
(function($){
  $(function(){

    var table = $("#ls-licenses").DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      ajax: {
        url: (window.LSLicensesDT && LSLicensesDT.ajaxUrl) ? LSLicensesDT.ajaxUrl : ajaxurl,
        type: "POST",
        data: function(d){
          d.action = "ls_licenses_dt";
          d.nonce  = (window.LSLicensesDT && LSLicensesDT.nonce) ? LSLicensesDT.nonce : "";
        }
      },
      pageLength: 25,
      lengthMenu: [[10,25,50,100],[10,25,50,100]],
      order: [[0,"desc"]], // hidden id column
      columns: [
        {data:"id", visible:false},
        {data:"key_value", orderable:true, render:function(data,type,row){
          if (type === "display") {
            var esc = $("<div>").text(data || "").html();
            return ''+
              '<div class="ls-key-wrap">'+
                '<code class="ls-key" title="'+esc+'">'+esc+'</code>'+
                '<button type="button" class="button ls-admin-copy" aria-label="Copy key" title="Copy" data-key="'+esc+'">'+
                  // Inline SVG (copy icon)
                  '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">'+
                    '<path d="M16 1H6a2 2 0 0 0-2 2v10h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 13H10V7h9v11z"></path>'+
                  '</svg>'+
                '</button>'+
              '</div>';
          }
          return data;
        }},
        {data:"order_id", orderable:true, render:function(data,type,row){
          if (type === "display" && data) {
            var link = row.order_link || "#";
            return '<a class="ls-chip" href="'+link+'" target="_blank">#'+data+'</a>';
          }
          return data;
        }},
        {data:"product_id", orderable:true, render:function(data,type,row){
          if (type === "display" && data) {
            var txt  = "#"+data+(row.product_name ? " – "+row.product_name : "");
            var esc  = $("<div>").text(txt).html();
            var link = row.product_link || "#";
            return '<a class="ls-chip" href="'+link+'" target="_blank">'+esc+'</a>';
          }
          return data;
        }},
        {data:"sku",   orderable:true},
        {data:"email", orderable:true},
        {data:"created_at", orderable:true, render:function(data,type,row){
            if (type === "display" && data) {
              // Format date nicely
              var d = new Date(data.replace(' ', 'T')); 
              return d.toLocaleString();
            }
            return data;
        }},
        { 
          data: "action", 
          orderable: false, 
          searchable: false, 
          render: function(data, type, row) {
            if (type === "display") {
              return '' +
                '<a href="admin.php?page=ls-license-shipper-edit&id='+row.id+'" class="button button-small ls-edit-btn" title="Edit">' +
                  '<span class="dashicons dashicons-edit"></span>' +
                '</a> ' +
                '<a href="admin.php?page=ls-license-shipper-change&id='+row.id+'" class="button button-small" title="Change">' +
                  '<span class="dashicons dashicons-update"></span>' +
                '</a>';
            }
            return data;
          }
        }



      ],
      dom: "Bfrtip",
      buttons: ["copy","csv","print"]
    });

    // Copy key button
  $(document).on("click", ".ls-admin-copy", function () {
    var key = $(this).data("key") || "";
    if (!key) return;

    var $btn = $(this);
    var originalSvg = $btn.html(); // store original icon

    navigator.clipboard.writeText(String(key)).then(function () {
      // Change to a checkmark icon
      $btn
        .html(
          '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">' +
            '<path fill="currentColor" d="M20.285 6.709a1 1 0 0 0-1.414-1.418l-9.192 9.215-4.55-4.543a1 1 0 1 0-1.415 1.414l5.257 5.25a1 1 0 0 0 1.414 0l9.9-9.918z"></path>' +
          '</svg>'
        )
        .addClass("ls-copy-success")
        .prop("disabled", true);

      // Restore after delay
      setTimeout(function () {
        $btn.html(originalSvg).removeClass("ls-copy-success").prop("disabled", false);
      }, 1200);
    });
  });




  // Change License page: fetch replacement key by SKU
  $(document).on('click', '#ls-fetch-btn', function () {
    var sku = $('#ls-change-sku').val();
    var $btn = $(this);

    if (!sku) {
      alert('SKU is missing.');
      return;
    }

    $btn.prop('disabled', true);

    $.post((window.LSLicensesDT && LSLicensesDT.ajaxUrl) || ajaxurl, {
      action: 'ls_fetch_license_by_sku',
      nonce: (window.LSLicensesDT && LSLicensesDT.changeNonce) || '',
      sku: sku
    })
      .done(function (res) {
        if (res && res.success && res.data) {
          $('#ls-new-key').val(res.data.key_value || '');
          $('#ls-new-link').val(res.data.download_link || '');
          $('#ls-new-guide').val(res.data.activation_guide || '');
        } else {
          alert((res && res.data && res.data.message) || 'Failed to fetch license.');
        }
      })
      .fail(function () {
        alert('Request failed.');
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  });
})(jQuery);
