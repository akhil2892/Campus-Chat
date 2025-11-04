// Campus Talk - Main JavaScript Functions

// Global variables
let chatRefreshInterval;
let isTyping = false;
let lastMessageId = 0;

// Initialize on document ready
$(document).ready(function() {
    initializeApp();
});

function initializeApp() {
    // Set up CSRF token for AJAX requests
    $.ajaxSetup({
        beforeSend: function(xhr, settings) {
            if (!/^(GET|HEAD|OPTIONS|TRACE)$/i.test(settings.type) && !this.crossDomain) {
                xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
            }
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize modals
    initializeModals();
    
    // Set up real-time updates if on chat pages
    if (window.location.pathname.includes('chat.php') || 
        window.location.pathname.includes('groups.php') || 
        window.location.pathname.includes('rooms.php')) {
        startChatRefresh();
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeOut();
    }, 5000);
}

// Modal initialization
function initializeModals() {
    // Close modal on outside click
    $('.modal').on('click', function(e) {
        if (e.target === this) {
            $(this).modal('hide');
        }
    });
}

// Anonymous mode toggle
function toggleAnonymousMode(isAnonymous) {
    showLoadingSpinner();
    
    $.ajax({
        url: SITE_CONFIG.baseUrl + 'php/user/toggle_anonymous.php',
        method: 'POST',
        data: { anonymous: isAnonymous },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                SITE_CONFIG.isAnonymous = response.anonymous;
                updateAnonymousIndicator(response.anonymous);
                showNotification(response.message, 'success');
            } else {
                showNotification('Failed to toggle anonymous mode', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Anonymous toggle error:', error);
            showNotification('Failed to toggle anonymous mode', 'error');
        },
        complete: function() {
            hideLoadingSpinner();
        }
    });
}

// Update anonymous mode indicator in navigation
function updateAnonymousIndicator(isAnonymous) {
    const indicator = $('#anonymousToggle');
    if (isAnonymous) {
        indicator.html('<i class="fas fa-user-secret"></i> Anonymous');
    } else {
        indicator.html('<i class="fas fa-eye"></i> Visible');
    }
}

// Show loading spinner
function showLoadingSpinner() {
    $('#loadingSpinner').removeClass('d-none');
}

// Hide loading spinner
function hideLoadingSpinner() {
    $('#loadingSpinner').addClass('d-none');
}

// Show notification
function showNotification(message, type = 'info', duration = 5000) {
    const alertClass = type === 'error' ? 'alert-danger' : 
                      type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const icon = type === 'error' ? 'fas fa-exclamation-circle' : 
                type === 'success' ? 'fas fa-check-circle' : 
                type === 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-info-circle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 80px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <i class="${icon}"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Auto-hide after duration
    setTimeout(function() {
        $('.alert').last().fadeOut(function() {
            $(this).remove();
        });
    }, duration);
}

// Format time ago
function formatTimeAgo(timestamp) {
    const now = new Date();
    const messageTime = new Date(timestamp);
    const diffInSeconds = Math.floor((now - messageTime) / 1000);
    
    if (diffInSeconds < 60) {
        return 'just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }
}

// Sanitize HTML input
function sanitizeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Validate email format
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Auto-resize textarea
function autoResizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Initialize auto-resize for all textareas
$(document).on('input', 'textarea.auto-resize', function() {
    autoResizeTextarea(this);
});

// Confirm action dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Copy text to clipboard
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showNotification('Copied to clipboard', 'success');
        }).catch(function() {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Copied to clipboard', 'success');
    } catch (err) {
        showNotification('Failed to copy to clipboard', 'error');
    }
    
    document.body.removeChild(textArea);
}

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search functionality
function initializeSearch() {
    const searchInput = $('#searchInput');
    if (searchInput.length) {
        const debouncedSearch = debounce(performSearch, 300);
        searchInput.on('input', function() {
            const query = $(this).val();
            if (query.length >= 2) {
                debouncedSearch(query);
            } else {
                clearSearchResults();
            }
        });
    }
}

function performSearch(query) {
    showLoadingSpinner();
    
    $.ajax({
        url: SITE_CONFIG.baseUrl + 'php/search.php',
        method: 'GET',
        data: { q: query },
        dataType: 'json',
        success: function(response) {
            displaySearchResults(response);
        },
        error: function(xhr, status, error) {
            console.error('Search error:', error);
            showNotification('Search failed', 'error');
        },
        complete: function() {
            hideLoadingSpinner();
        }
    });
}

function displaySearchResults(results) {
    const resultsContainer = $('#searchResults');
    if (resultsContainer.length) {
        let html = '';
        
        if (results.length === 0) {
            html = '<p class="text-muted">No results found</p>';
        } else {
            results.forEach(function(result) {
                html += `
                    <div class="search-result-item p-2 border-bottom">
                        <h6>${sanitizeHtml(result.title)}</h6>
                        <p class="text-muted small">${sanitizeHtml(result.description)}</p>
                    </div>
                `;
            });
        }
        
        resultsContainer.html(html);
        resultsContainer.show();
    }
}

function clearSearchResults() {
    $('#searchResults').hide();
}

// Start chat refresh interval
function startChatRefresh() {
    if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
    }
    
    chatRefreshInterval = setInterval(function() {
        refreshChatData();
    }, SITE_CONFIG.chatRefreshInterval || 3000);
}

// Stop chat refresh interval
function stopChatRefresh() {
    if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
        chatRefreshInterval = null;
    }
}

// Refresh chat data
function refreshChatData() {
    // This will be implemented in chat.js
    if (typeof refreshMessages === 'function') {
        refreshMessages();
    }
}

// Handle page visibility change
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopChatRefresh();
    } else {
        if (window.location.pathname.includes('chat.php') || 
            window.location.pathname.includes('groups.php') || 
            window.location.pathname.includes('rooms.php')) {
            startChatRefresh();
        }
    }
});

// Handle online/offline status
window.addEventListener('online', function() {
    showNotification('Connection restored', 'success');
    if (window.location.pathname.includes('chat.php') || 
        window.location.pathname.includes('groups.php') || 
        window.location.pathname.includes('rooms.php')) {
        startChatRefresh();
    }
});

window.addEventListener('offline', function() {
    showNotification('Connection lost', 'warning');
    stopChatRefresh();
});

// Prevent accidental form submission on Enter
$(document).on('keydown', 'form input:not([type="submit"]):not(.allow-enter)', function(e) {
    if (e.keyCode === 13) {
        e.preventDefault();
        // Move to next input field
        const inputs = $(this).closest('form').find('input, select, textarea');
        const currentIndex = inputs.index(this);
        if (currentIndex < inputs.length - 1) {
            inputs.eq(currentIndex + 1).focus();
        }
    }
});

// Handle form submission with loading state
$(document).on('submit', 'form.ajax-form', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitBtn = form.find('[type="submit"]');
    const originalText = submitBtn.html();
    
    // Show loading state
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
    
    $.ajax({
        url: form.attr('action') || window.location.pathname,
        method: form.attr('method') || 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(response.message || 'Success', 'success');
                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            } else {
                showNotification(response.message || 'An error occurred', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Form submission error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        },
        complete: function() {
            // Restore button state
            submitBtn.html(originalText).prop('disabled', false);
        }
    });
});

// Character counter for textareas with limits
$(document).on('input', 'textarea[data-max-length]', function() {
    const textarea = $(this);
    const maxLength = parseInt(textarea.data('max-length'));
    const currentLength = textarea.val().length;
    
    let counter = textarea.next('.character-counter');
    if (!counter.length) {
        counter = $('<div class="character-counter text-muted small"></div>');
        textarea.after(counter);
    }
    
    counter.text(`${currentLength}/${maxLength}`);
    
    if (currentLength > maxLength) {
        counter.addClass('text-danger');
        textarea.addClass('is-invalid');
    } else {
        counter.removeClass('text-danger');
        textarea.removeClass('is-invalid');
    }
});

// Initialize search on pages that have search functionality
$(document).ready(function() {
    initializeSearch();
});