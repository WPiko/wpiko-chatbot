/**
 * WPiko Chatbot Dashboard JavaScript
 * 
 * @package WPiko_Chatbot
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Auto-refresh dashboard data every 5 minutes
    var dashboardRefreshInterval = setInterval(function() {
        // Only refresh if we're on the dashboard tab
        if (window.location.href.indexOf('tab=dashboard') !== -1 || 
            (window.location.href.indexOf('tab=') === -1 && window.location.href.indexOf('page=ai-chatbot') !== -1)) {
            location.reload();
        }
    }, 300000); // 5 minutes
    
    // Add smooth animations to stat cards
    $('.stat-card').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
        $(this).addClass('animate-fade-in');
    });
    
    // Cleanup interval when page is unloaded
    $(window).on('beforeunload', function() {
        if (dashboardRefreshInterval) {
            clearInterval(dashboardRefreshInterval);
        }
    });
});

// Add CSS animations via JavaScript if not supported
if (typeof window.CSS === 'undefined' || !CSS.supports('animation', 'fade-in')) {
    var style = document.createElement('style');
    style.textContent = `
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 0.5s ease-out forwards;
        }
    `;
    document.head.appendChild(style);
}
