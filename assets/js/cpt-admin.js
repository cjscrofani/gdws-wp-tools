/**
 * GDWS Custom Post Types Admin JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    var $form = $('#cpt-form');
    var $formContainer = $('.gdws-cpt-form');
    var $listContainer = $('.gdws-cpt-list');
    var $formTitle = $('#form-title');
    
    // Add New CPT button
    $('#add-new-cpt').on('click', function() {
        resetForm();
        $formTitle.text(gdws_cpt_admin.add_new_title || 'Add New Post Type');
        $formContainer.slideDown();
        $listContainer.slideUp();
    });
    
    // Cancel form
    $('#cancel-form').on('click', function() {
        $formContainer.slideUp();
        $listContainer.slideDown();
    });
    
    // Edit CPT button
    $(document).on('click', '.edit-cpt', function() {
        var cptKey = $(this).data('key');
        
        $.post(gdws_cpt.ajax_url, {
            action: 'gdws_get_cpt',
            cpt_key: cptKey,
            nonce: gdws_cpt.nonce
        }, function(response) {
            if (response.success) {
                populateForm(cptKey, response.data);
                $formTitle.text(gdws_cpt_admin.edit_title || 'Edit Post Type');
                $formContainer.slideDown();
                $listContainer.slideUp();
            }
        });
    });
    
    // Delete CPT button
    $(document).on('click', '.delete-cpt', function() {
        if (!confirm(gdws_cpt_admin.confirm_delete || 'Are you sure you want to delete this post type?')) {
            return;
        }
        
        var $button = $(this);
        var cptKey = $button.data('key');
        
        $button.prop('disabled', true);
        
        $.post(gdws_cpt.ajax_url, {
            action: 'gdws_delete_cpt',
            cpt_key: cptKey,
            nonce: gdws_cpt.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
                $button.prop('disabled', false);
            }
        });
    });
    
    // Auto-generate slug from singular name
    $('#singular-name').on('blur', function() {
        var $slug = $('#slug');
        if (!$slug.val()) {
            var singularName = $(this).val();
            var slug = singularName.toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^a-z0-9-]/g, '');
            $slug.val(slug);
        }
    });
    
    // Form submission
    $form.on('submit', function(e) {
        e.preventDefault();
        
        var $submitButton = $form.find('button[type="submit"]');
        $submitButton.prop('disabled', true);
        
        var formData = new FormData(this);
        formData.append('action', 'gdws_save_cpt');
        formData.append('nonce', gdws_cpt.nonce);
        
        // Handle checkboxes properly
        $form.find('input[type="checkbox"]:not(:checked)').each(function() {
            formData.append($(this).attr('name'), '0');
        });
        
        $.ajax({
            url: gdws_cpt.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || 'An error occurred');
                    $submitButton.prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $submitButton.prop('disabled', false);
            }
        });
    });
    
    // Reset form
    function resetForm() {
        $form[0].reset();
        $('#cpt-key').val('');
        // Set default checkboxes
        $form.find('input[name="supports[]"][value="title"]').prop('checked', true);
        $form.find('input[name="supports[]"][value="editor"]').prop('checked', true);
        $form.find('input[name="public"]').prop('checked', true);
        $form.find('input[name="has_archive"]').prop('checked', true);
        $form.find('input[name="show_in_rest"]').prop('checked', true);
        $form.find('input[name="active"]').prop('checked', true);
    }
    
    // Populate form with existing data
    function populateForm(cptKey, data) {
        resetForm();
        
        $('#cpt-key').val(cptKey);
        $('#singular-name').val(data.singular_name);
        $('#plural-name').val(data.plural_name);
        $('#slug').val(data.slug);
        $('#menu-icon').val(data.menu_icon);
        
        // Handle supports array
        $form.find('input[name="supports[]"]').prop('checked', false);
        if (data.supports && data.supports.length) {
            data.supports.forEach(function(support) {
                $form.find('input[name="supports[]"][value="' + support + '"]').prop('checked', true);
            });
        }
        
        // Handle other checkboxes
        $form.find('input[name="public"]').prop('checked', data.public);
        $form.find('input[name="has_archive"]').prop('checked', data.has_archive);
        $form.find('input[name="show_in_rest"]').prop('checked', data.show_in_rest);
        $form.find('input[name="hierarchical"]').prop('checked', data.hierarchical);
        $form.find('input[name="active"]').prop('checked', data.active);
    }
    
    // Localize strings for JavaScript
    if (typeof gdws_cpt_admin === 'undefined') {
        window.gdws_cpt_admin = {
            add_new_title: 'Add New Post Type',
            edit_title: 'Edit Post Type',
            confirm_delete: 'Are you sure you want to delete this post type? This action cannot be undone.'
        };
    }
});