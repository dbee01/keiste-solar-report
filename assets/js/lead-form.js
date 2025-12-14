/**
 * Lead Form JavaScript for Keiste Solar Report
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Lead form submission handler
        $('#ksrad-lead-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('.ksrad-submit-btn');
            var messageDiv = form.find('.ksrad-form-message');
            
            // Validate required fields
            var isValid = true;
            form.find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).css('border-color', '#d63638');
                } else {
                    $(this).css('border-color', '#ddd');
                }
            });
            
            if (!isValid) {
                showMessage(messageDiv, 'error', 'Please fill in all required fields.');
                return;
            }
            
            // Validate email format
            var email = form.find('#ksrad-lead-email').val();
            if (!isValidEmail(email)) {
                showMessage(messageDiv, 'error', 'Please enter a valid email address.');
                form.find('#ksrad-lead-email').css('border-color', '#d63638');
                return;
            }
            
            // Validate consent
            if (!form.find('input[name="consent"]').is(':checked')) {
                showMessage(messageDiv, 'error', 'Please agree to the terms to continue.');
                return;
            }
            
            // Disable form during submission
            submitBtn.prop('disabled', true).text('Submitting...');
            form.find('input, textarea, button').prop('disabled', true);
            messageDiv.hide();
            
            // Submit via AJAX
            $.ajax({
                url: ksradLeadAjax.ajaxurl,
                type: 'POST',
                data: form.serialize(),
                success: function(response) {
                    if (response.success) {
                        showMessage(messageDiv, 'success', response.data.message);
                        form[0].reset();
                        
                        // Scroll to success message
                        $('html, body').animate({
                            scrollTop: messageDiv.offset().top - 50
                        }, 500);
                        
                        // Re-enable form after delay.
                        setTimeout(function() {
                            form.find('input, textarea, button').prop('disabled', false);
                            submitBtn.text(submitBtn.data('original-text') || 'Get My Free Quote');
                        }, 20000);
                    } else {
                        showMessage(messageDiv, 'error', response.data.message || 'An error occurred. Please try again.');
                        form.find('input, textarea, button').prop('disabled', false);
                        submitBtn.prop('disabled', false).text(submitBtn.data('original-text') || 'Get My Free Quote');
                    }
                },
                error: function() {
                    showMessage(messageDiv, 'error', 'An error occurred. Please try again.');
                    form.find('input, textarea, button').prop('disabled', false);
                    submitBtn.prop('disabled', false).text(submitBtn.data('original-text') || 'Get My Free Quote');
                }
            });
        });
        
        /**
         * Show form message
         */
        function showMessage(element, type, message) {
            element.removeClass('success error')
                   .addClass(type)
                   .text(message)
                   .fadeIn();
        }
        
        /**
         * Validate email format
         */
        function isValidEmail(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }
        
        // Store original button text
        $('.ksrad-submit-btn').each(function() {
            $(this).data('original-text', $(this).text());
        });
        
        // Clear error styling on input
        $('#ksrad-lead-form input, #ksrad-lead-form textarea').on('input', function() {
            $(this).css('border-color', '#ddd');
        });
        
        // Phone number formatting (optional)
        $('#ksrad-lead-phone').on('input', function() {
            var phone = $(this).val().replace(/\D/g, '');
            if (phone.length >= 10) {
                var formatted = '(' + phone.substr(0, 3) + ') ' + phone.substr(3, 3) + '-' + phone.substr(6, 4);
                $(this).val(formatted);
            }
        });
    });
    
})(jQuery);
