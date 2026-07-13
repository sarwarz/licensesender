/**
 * licensesender – Activation Guides (Unified Script)
 * Handles both:
 *  1️ List Page (DataTable + Delete)
 *  2️ Form Page (Add/Edit + Save)
 *
 * Dependencies:
 *  - jQuery
 *  - SweetAlert2
 *  - DataTables
 *  - TinyMCE
 *  - Select2
 *
 * Localized Variables (via wp_localize_script):
 *  - lsActivation (common strings and ajax URL)
 */

jQuery(function ($) {
    "use strict";

    if (typeof lsActivation === "undefined") {
        console.error("lsActivation not localized.");
        return;
    }

    const ajaxUrl = lsActivation.ajaxUrl;
    const manageNonce = lsActivation.nonce || '';

    /**  Toast helper */
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1600,
        timerProgressBar: true,
        didOpen: (t) => {
            t.addEventListener("mouseenter", Swal.stopTimer);
            t.addEventListener("mouseleave", Swal.resumeTimer);
        },
    });

    /** ======================================================
     *  LIST PAGE — Manage Activation Guides
     * ====================================================== */
    if ($("#ls-activation-guides-table").length) {
        const table = $("#ls-activation-guides-table").DataTable({
            ajax: {
                url: ajaxUrl,
                type: "POST",
                data: { action: "ls_get_activation_guides", nonce: manageNonce },
            },
            columns: [
                { data: "id", width: "50px", className: "text-center" },
                { data: "product_name" },
                { data: "type", width: "120px", className: "text-center" },
                { data: "created_at", width: "160px", className: "text-center" },
                {
                    data: "actions",
                    orderable: false,
                    searchable: false,
                    width: "120px",
                    className: "text-center",
                },
            ],
            responsive: true,
            pageLength: 10,
            order: [[0, "desc"]],
            language: {
                emptyTable: lsActivation.emptyTable || "No activation guides found.",
            },
        });

        //  Delete action
        $(document).on("click", ".ls-delete-guide", function (e) {
            e.preventDefault();
            const id = $(this).data("id");
            if (!id) return;

            Swal.fire({
                title: lsActivation.confirmDelete || "Are you sure?",
                text:
                    lsActivation.deleteMsg ||
                    "This will permanently delete the activation guide.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: lsActivation.confirmText || "Yes, delete it!",
                cancelButtonText: lsActivation.cancelText || "Cancel",
                confirmButtonColor: "#e3342f",
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: ajaxUrl,
                        type: "POST",
                        data: { action: "ls_delete_activation_guide", id, nonce: manageNonce },
                        beforeSend: () => {
                            Swal.fire({
                                title: "Deleting...",
                                didOpen: () => Swal.showLoading(),
                                allowOutsideClick: false,
                            });
                        },
                        success: function (res) {
                            Swal.close();
                            if (res.success) {
                                Toast.fire({
                                    icon: "success",
                                    title:
                                        res.data ||
                                        lsActivation.successMsg ||
                                        "Deleted successfully.",
                                });
                                table.ajax.reload(null, false);
                            } else {
                                Swal.fire(
                                    "Error",
                                    res.data ||
                                        lsActivation.errorMsg ||
                                        "Failed to delete record.",
                                    "error"
                                );
                            }
                        },
                        error: function () {
                            Swal.close();
                            Swal.fire(
                                "Error",
                                lsActivation.serverError ||
                                    "Could not connect to the server.",
                                "error"
                            );
                        },
                    });
                }
            });
        });
    }

    /** ======================================================
     *  FORM PAGE — Add / Edit Activation Guide
     * ====================================================== */
    if ($("#ls-activation-guide-form").length) {
        const $form = $("#ls-activation-guide-form");
        const $btn = $form.find('button[type="submit"]');

        //  Toggle between plain text and PDF upload
        function toggleFields() {
            const type = $("#type").val();
            if (type === "pdf") {
                $("#pdf-upload-row").show();
                $("#text-content-row").hide();
            } else {
                $("#pdf-upload-row").hide();
                $("#text-content-row").show();
            }
        }

        toggleFields();
        $("#type").on("change", toggleFields);

        //  Initialize Select2
        $("#product_id").select2({
            placeholder: "Select a product...",
            width: "100%",
        });

        //  Handle form submit
        $form.on("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append("action", "ls_save_activation_guide");
            if (manageNonce) {
                formData.append("nonce", manageNonce);
            }

            // TinyMCE content
            if (typeof tinymce !== "undefined" && tinymce.get("content")) {
                formData.set("content", tinymce.get("content").getContent());
            }

            $btn.prop("disabled", true).text("Saving...");

            $.ajax({
                url: ajaxUrl,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $btn.prop("disabled", false).text("Save");

                    if (res.success) {
                        Swal.fire({
                            toast: true,
                            position: "top-end",
                            icon: "success",
                            title:
                                res.data ||
                                lsActivation.successMsg ||
                                "Saved successfully!",
                            showConfirmButton: false,
                            timer: 1500,
                        });
                        setTimeout(
                            () =>
                                (window.location.href =
                                    lsActivation.redirectUrl ||
                                    "admin.php?page=ls-activation-guides"),
                            1000
                        );
                    } else {
                        Swal.fire(
                            "Error",
                            res.data ||
                                lsActivation.errorMsg ||
                                "Save failed.",
                            "error"
                        );
                    }
                },
                error: function () {
                    $btn.prop("disabled", false).text("Save");
                    Swal.fire(
                        "Error",
                        lsActivation.serverError ||
                            "Server connection failed.",
                        "error"
                    );
                },
            });
        });
    }
});
