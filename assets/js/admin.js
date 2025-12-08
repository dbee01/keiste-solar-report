/**
 * Admin JavaScript for Keiste Solar Report
 */

jQuery(document).ready(function($) {
    // Handle upgrade notice dismissal
    $('.ksrad-dismiss-notice').on('click', function(e) {
        e.preventDefault();
        $('.ksrad-upgrade-notice').fadeOut();
        $.post(ajaxurl, {
            action: 'ksrad_dismiss_upgrade_notice',
            nonce: ksradAdmin.dismissNonce
        });
    });
});

/**
 * Show lead details in modal
 */
function showLeadDetails(lead) {
    var html = '<h2>' + lead.name + '</h2>';
    html += '<table class="form-table">';
    html += '<tr><th>Email:</th><td><a href="mailto:' + lead.email + '">' + lead.email + '</a></td></tr>';
    html += '<tr><th>Phone:</th><td><a href="tel:' + lead.phone + '">' + lead.phone + '</a></td></tr>';
    html += '<tr><th>Address:</th><td>' + lead.address + '</td></tr>';
    html += '<tr><th>Monthly Bill:</th><td>$' + parseFloat(lead.monthly_bill).toFixed(2) + '</td></tr>';
    html += '<tr><th>Roof Type:</th><td>' + lead.roof_type + '</td></tr>';
    html += '<tr><th>System Size:</th><td>' + parseFloat(lead.estimated_system_size).toFixed(2) + ' kW</td></tr>';
    html += '<tr><th>Estimated Cost:</th><td>$' + parseFloat(lead.estimated_cost).toFixed(2) + '</td></tr>';
    html += '<tr><th>Annual Savings:</th><td>$' + parseFloat(lead.estimated_savings).toFixed(2) + '</td></tr>';
    if (lead.notes) {
        html += '<tr><th>Notes:</th><td>' + lead.notes + '</td></tr>';
    }
    html += '<tr><th>IP Address:</th><td>' + lead.ip_address + '</td></tr>';
    html += '<tr><th>Date:</th><td>' + lead.created_at + '</td></tr>';
    html += '</table>';
    
    document.getElementById('ksrad-lead-details').innerHTML = html;
    document.getElementById('ksrad-lead-modal').style.display = 'block';
}

/**
 * Close lead details modal
 */
function closeLeadModal() {
    document.getElementById('ksrad-lead-modal').style.display = 'none';
}
