/**
 * Calculator JavaScript for Keiste Solar Report
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        var calculatorData = {};
        
        // Calculate button click handler
        $('#ksrad-calculate-btn').on('click', function() {
            var monthlyBill = parseFloat($('#ksrad-monthly-bill').val());
            var roofType = $('#ksrad-roof-type').val();
            var electricityRate = parseFloat($('#ksrad-electricity-rate').val());
            
            // Validate inputs
            if (!monthlyBill || monthlyBill <= 0) {
                showError('Please enter a valid monthly electric bill amount.');
                return;
            }
            
            if (!electricityRate || electricityRate <= 0) {
                showError('Please enter a valid electricity rate.');
                return;
            }
            
            // Show loading state
            $('.ksrad-calculator-form').hide();
            $('.ksrad-results').hide();
            $('.ksrad-error').hide();
            $('.ksrad-loading').show();
            
            // Make AJAX request
            $.ajax({
                url: ksradAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ksrad_calculate',
                    nonce: ksradAjax.nonce,
                    monthly_bill: monthlyBill,
                    roof_type: roofType,
                    electricity_rate: electricityRate
                },
                success: function(response) {
                    $('.ksrad-loading').hide();
                    
                    if (response.success) {
                        calculatorData = response.data;
                        displayResults(response.data);
                        populateLeadFormData(monthlyBill, roofType, response.data);
                    } else {
                        showError(response.data.message || 'An error occurred during calculation.');
                        $('.ksrad-calculator-form').show();
                    }
                },
                error: function() {
                    $('.ksrad-loading').hide();
                    showError('An error occurred. Please try again.');
                    $('.ksrad-calculator-form').show();
                }
            });
        });
        
        /**
         * Display calculation results
         */
        function displayResults(data) {
            $('#ksrad-system-size').text(formatNumber(data.system_size_kw, 2) + ' kW');
            $('#ksrad-estimated-cost').text('$' + formatNumber(data.cost_after_incentives, 0));
            $('#ksrad-annual-savings').text('$' + formatNumber(data.annual_savings, 0));
            $('#ksrad-lifetime-savings').text('$' + formatNumber(data.lifetime_savings, 0));
            $('#ksrad-payback-period').text(formatNumber(data.payback_period, 1) + ' years');
            $('#ksrad-co2-offset').text(formatNumber(data.lifetime_co2_offset, 0) + ' lbs');
            
            $('.ksrad-results').fadeIn();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $('.ksrad-results').offset().top - 50
            }, 500);
        }
        
        /**
         * Populate lead form with calculator data
         */
        function populateLeadFormData(monthlyBill, roofType, data) {
            $('#ksrad-lead-monthly-bill').val(monthlyBill);
            $('#ksrad-lead-roof-type').val(roofType);
            $('#ksrad-lead-system-size').val(data.system_size_kw);
            $('#ksrad-lead-cost').val(data.cost_after_incentives);
            $('#ksrad-lead-savings').val(data.annual_savings);
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            $('.ksrad-error').text(message).show();
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $('.ksrad-error').offset().top - 50
            }, 500);
        }
        
        /**
         * Format number with commas and decimals
         */
        function formatNumber(num, decimals) {
            if (typeof num !== 'number') {
                num = parseFloat(num);
            }
            
            if (isNaN(num)) {
                return '0';
            }
            
            return num.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        // Allow recalculation
        $(document).on('click', '.ksrad-results', function() {
            // Add a recalculate button if needed
        });
        
        // Enter key support for inputs
        $('.ksrad-calculator-form input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#ksrad-calculate-btn').click();
            }
        });
    });
    
})(jQuery);
