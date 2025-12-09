/**
 * Modal Handler
 * Handles modal popup display, form submission, and user interaction
 */

(function() {
    'use strict';

    // Get config from global object (set by PHP)
    const config = window.KSRAD_Config || {};

    // Cookie management
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Get modal element (handle legacy ID mismatches)
    function _getRoiModalEl() {
        return document.getElementById('roiUserModal') || document.getElementById('roiModal');
    }

    // Show modal function
    function showModal() {
        var modalEl = _getRoiModalEl();
        if (!modalEl) return;

        // Use native dialog API if supported
        if (typeof modalEl.showModal === 'function') {
            try {
                modalEl.showModal();
            } catch (e) {
                modalEl.setAttribute('open', '');
                modalEl.style.display = 'flex';
            }
        } else {
            modalEl.style.display = 'flex';
        }

        // Keep global reference
        if (typeof window.roiModal === 'undefined') window.roiModal = modalEl;
        if (window.roiModal && window.roiModal.classList) window.roiModal.classList.add('active');
        document.body.classList.add('modal-open');

        // Focus modal for accessibility
        setTimeout(function() {
            var dialog = modalEl.querySelector('.modal-dialog');
            if (dialog && typeof dialog.focus === 'function') dialog.focus();
            var firstInput = modalEl.querySelector('input, select, textarea, button');
            if (firstInput && typeof firstInput.focus === 'function') firstInput.focus();
        }, 10);

        if (typeof trapFocus === 'function') trapFocus(modalEl);
    }

    // Hide modal function
    function hideModal() {
        var modalEl = _getRoiModalEl();
        if (!modalEl) return;

        if (typeof modalEl.close === 'function') {
            try {
                modalEl.close();
            } catch (e) {
                modalEl.removeAttribute('open');
                modalEl.style.display = 'none';
            }
        } else {
            modalEl.style.display = 'none';
        }

        if (window.roiModal && window.roiModal.classList) window.roiModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    // Expose functions globally
    window.showModal = showModal;
    window.hideModal = hideModal;
    window.initModalHandlers = initModalHandlers; // Expose for AJAX reinitialization

    // Email validation using emailchecker API (via WordPress proxy)
    function validateEmail(email, callback) {
        console.log('validateEmail called for:', email);
        console.log('jQuery available?', typeof jQuery !== 'undefined');
        console.log('ksradData available?', typeof ksradData !== 'undefined');
        
        // Skip validation if jQuery not available
        if (typeof jQuery === 'undefined') {
            console.log('jQuery not available, skipping email validation');
            callback(true, null);
            return;
        }
        
        // If ksradData not available, assume valid (don't block user)
        if (typeof ksradData === 'undefined') {
            console.log('ksradData not available, skipping email validation');
            callback(true, null);
            return;
        }
        
        console.log('Validating email:', email);
        console.log('AJAX URL:', ksradData.ajaxUrl);
        
        // Call WordPress AJAX endpoint (which proxies to emailchecker API)
        jQuery.ajax({
            url: ksradData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ksrad_validate_email',
                nonce: ksradData.nonce,
                email: email
            },
            success: function(response) {
                console.log('Email validation response:', response);
                
                if (response.success) {
                    callback(true, response.data);
                } else {
                    callback(false, {reason: response.data.reason || response.data.message || 'Invalid email'});
                }
            },
            error: function(xhr, status, error) {
                console.log('Email validation API error:', status, error);
                // On error, assume valid to not block user
                callback(true, null);
            }
        });
    }

    // Phone validation with Twilio Lookup API
    function validatePhone(phoneNumber, countryCode, callback) {
        const fullPhone = countryCode + phoneNumber;
        
        // Skip validation if jQuery or ksradData not available
        if (typeof jQuery === 'undefined' || typeof ksradData === 'undefined') {
            callback(true, null); // Assume valid if we can't validate
            return;
        }
        
        jQuery.ajax({
            url: ksradData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ksrad_validate_phone',
                nonce: ksradData.nonce,
                phone: fullPhone
            },
            success: function(response) {
                if (response.success) {
                    callback(true, response.data);
                } else {
                    // Check if it's a Twilio auth error (401) - skip validation
                    if (response.data && response.data.status_code === 401) {
                        console.log('[Phone Validation] Twilio auth error, skipping validation');
                        callback(true, null);
                    } else {
                        callback(false, response.data);
                    }
                }
            },
            error: function() {
                // On error, assume valid to not block user
                callback(true, null);
            }
        });
    }

//     // Initialize modal handlers
    function initModalHandlers() {
        console.log('[initModalHandlers] Called');
        
        const roiForm = document.getElementById('roiForm');
        const resultsSection = document.getElementById('results');

        // Handle form submission
        if (roiForm) {
            // Check if already has listener to prevent duplicates
            if (roiForm.dataset.listenerAttached === 'true') {
                console.log('[initModalHandlers] Form already has listener, skipping');
                return;
            }
            
            console.log('[initModalHandlers] Attaching submit listener to form');
            roiForm.dataset.listenerAttached = 'true';
            
            roiForm.addEventListener('submit', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation(); // Stop other listeners from firing

                // Prevent double submission
                if (window.formSubmissionInProgress) {
                    console.log('[Form] Submission already in progress, ignoring');
                    return;
                }
                
                // Check if form has been submitted successfully
                if (window.formSubmittedSuccessfully) {
                    console.log('[Form] Form already submitted successfully, ignoring');
                    return;
                }
                
                // Get form data FIRST to check if fields exist
                const phoneCountryEl = document.getElementById('roiPhoneCountry');
                const phoneNumberEl = document.getElementById('roiPhone');
                const emailEl = document.getElementById('roiEmail');
                const fullNameEl = document.getElementById('roiFullName');
                
                // If form fields don't exist, this is a duplicate submission after form was replaced
                if (!emailEl || !fullNameEl) {
                    console.log('[Form] Form elements missing (already replaced), ignoring submission');
                    return;
                }
                
                // Simple validation
                if (!roiForm.checkValidity()) {
                    roiForm.reportValidity();
                    return;
                }
                
                // Mark submission as in progress
                window.formSubmissionInProgress = true;
                
                const phoneCountry = phoneCountryEl?.value || '+353';
                const phoneNumber = phoneNumberEl?.value || '';
                const fullPhone = phoneCountry + phoneNumber;
                const emailValue = emailEl?.value || '';
                const fullNameValue = fullNameEl?.value || '';

                const formData = {
                    fullName: fullNameValue,
                    email: emailValue,
                    phone: fullPhone
                };

                // Show validating state AFTER capturing values
                const formEl = document.getElementById('roiForm');
                if (formEl) {
                    formEl.innerHTML = '<div style="padding: 2rem; text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #1B4D3E; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div><p style="color: #1B4D3E; font-weight: 600;">Validating email...</p></div>';
                }

                // Validate email first
                validateEmail(formData.email, function(emailValid, emailData) {
                    
                    if (!emailValid) {
                        var errorMsg = 'Invalid email address. Please use a valid email.';
                        if (emailData && emailData.reason) {
                            errorMsg = emailData.reason;
                        }
                        alert(errorMsg);
                        location.reload();
                        return;
                    }

                    // Update message for phone validation
                    const formEl2 = document.getElementById('roiForm');
                    if (formEl2) {
                        formEl2.innerHTML = '<div style="padding: 2rem; text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #1B4D3E; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div><p style="color: #1B4D3E; font-weight: 600;">Validating phone number...</p></div>';
                    }

                    // Validate phone number
                    validatePhone(phoneNumber, phoneCountry, function(isValid, data) {
                        
                        if (!isValid) {
                            // Show error and restore form
                            alert('Invalid phone number. Please check and try again.');
                            location.reload(); // Reload to restore form
                            return;
                        }

                    // Show loading state
                    const formEl = document.getElementById('roiForm');
                    if (formEl) {
                        formEl.innerHTML = '<div style="padding: 2rem; text-align: center;"><div style="border: 4px solid #f3f3f3; border-top: 4px solid #1B4D3E; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div><p style="color: #1B4D3E; font-weight: 600;">Submitting your details...</p></div>';
                    }

                    // Submit data to server (email notification + file storage)
                    if (typeof jQuery !== 'undefined' && typeof ksradData !== 'undefined') {
                        const panelCount = (typeof window.getPanelCount === 'function') ? window.getPanelCount() : 0;
                        
                        const submissionData = {
                            action: 'ksrad_generate_gamma_pdf',
                            nonce: ksradData.nonce,
                            fullName: formData.fullName,
                            email: formData.email,
                            phone: formData.phone,
                            panelCount: panelCount,
                            location: window.FORMATTED_ADDRESS || '',
                            country: window.COUNTRY_SETTING || '',
                            buildingType: window.BUILDING_TYPE || ''
                        };
                        
                        console.log('[AJAX SUBMISSION DATA]', submissionData);
                        
                        jQuery.ajax({
                            url: ksradData.ajaxUrl,
                            type: 'POST',
                            data: submissionData,
                            success: function(response) {
                                console.log('Form submission response:', response);
                                
                                // Check if submission was successful
                                if (!response.success) {
                                    console.error('Form submission failed:', response.data);
                                    window.formSubmissionInProgress = false;
                                    alert('Error: ' + (response.data?.message || 'Form submission failed'));
                                    location.reload();
                                    return;
                                }
                                
                                // Mark form as successfully submitted
                                window.formSubmittedSuccessfully = true;
                                window.formSubmissionInProgress = false;
                                
                                // Track GA4 conversion event
                                if (typeof window.trackSolarFormSubmission === 'function') {
                                    window.trackSolarFormSubmission({
                                        fullName: formData.fullName,
                                        email: formData.email,
                                        phone: formData.phone,
                                        panelCount: panelCount,
                                        country: window.COUNTRY_SETTING || '',
                                        buildingType: window.BUILDING_TYPE || ''
                                    });
                                }
                                
                                // Show success message
                                let successMsg = '<div style="padding: 2rem; text-align: center; color: #28a745;"><i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>';
                                successMsg += '<h3 style="color: #28a745; margin-bottom: 1rem;">Thank You</h3>';
                                successMsg += '<p style="color: #666; margin-top: 1rem;">Your details have been received. Please continue with your calculations.</p>';
                                successMsg += '<button onclick="hideModal()" style="margin-top: 1.5rem; padding: 0.75rem 2rem; background-color: var(--primary-green); color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease;">Continue</button>';
                                successMsg += '</div>';
                                
                                const formEl = document.getElementById('roiForm');
                                if (formEl) {
                                    formEl.innerHTML = successMsg;
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Form submission error:', error);
                                window.formSubmissionInProgress = false;
                                alert('There was an error submitting your details. Please try again.');
                                location.reload();
                            }
                        });
                    } else {
                        console.error('jQuery or ksradData not available for form submission');
                        alert('Unable to submit form. Please refresh and try again.');
                        location.reload();
                    }

                    // Trigger calculation logic
                    if (typeof window.calculateROI === 'function') {
                        window.calculateROI();
                    } else if (window.solarForm) {
                        window.solarForm.requestSubmit();
                    }
                    
                    if (resultsSection) resultsSection.style.display = 'block';
                    }); // End validatePhone callback
                }); // End validateEmail callback
            });
        }

        // Wire cancel button to reload page
        const roiCancel = document.getElementById('roiCancelBtn');
        if (roiCancel) {
            roiCancel.addEventListener('click', function(ev) {
                ev.preventDefault();
                window.location.href = '/keiste-solar-report';
            });
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape' || ev.key === 'Esc') {
                var m = _getRoiModalEl();
                if (m && (m.hasAttribute('open') || m.style.display === 'flex')) {
                    hideModal();
                }
            }
        });

        // Global failsafe: Block default submission of roiUserForm
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form && form.id === 'roiUserForm') {
                e.preventDefault();
                console.log('Global failsafe: blocked roiUserForm submit');
            }
        }, true);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalHandlers);
    } else {
        initModalHandlers();
    }
})();
