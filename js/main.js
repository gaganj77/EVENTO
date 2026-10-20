/**
 * EVENTO - Main JavaScript
 * Custom interactive features and utilities
 */

// =====================================================
// DOCUMENT READY
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    initTheme();
    initPasswordToggle();
    initFileUploadPreview();
    initCountdownTimers();
    initSeatSelection();
    initCounterAnimation();
});

// =====================================================
// THEME MANAGEMENT
// =====================================================
function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

// =====================================================
// PASSWORD TOGGLE
// =====================================================
function initPasswordToggle() {
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.closest('.password-input-group').querySelector('input');
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
    });
}

// =====================================================
// FILE UPLOAD PREVIEW
// =====================================================
function initFileUploadPreview() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function(e) {
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            
            if (!preview) return;
            
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// =====================================================
// COUNTDOWN TIMERS
// =====================================================
function initCountdownTimers() {
    document.querySelectorAll('.countdown-timer').forEach(timer => {
        const targetDate = new Date(timer.dataset.date).getTime();
        
        function updateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;
            
            if (distance < 0) {
                timer.innerHTML = '<span class="text-danger">Event Started</span>';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            timer.innerHTML = `
                <div class="countdown-item">
                    <span class="countdown-value">${String(days).padStart(2, '0')}</span>
                    <span class="countdown-label">Days</span>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <span class="countdown-value">${String(hours).padStart(2, '0')}</span>
                    <span class="countdown-label">Hrs</span>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <span class="countdown-value">${String(minutes).padStart(2, '0')}</span>
                    <span class="countdown-label">Min</span>
                </div>
                <div class="countdown-separator">:</div>
                <div class="countdown-item">
                    <span class="countdown-value">${String(seconds).padStart(2, '0')}</span>
                    <span class="countdown-label">Sec</span>
                </div>
            `;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    });
}

// =====================================================
// SEAT SELECTION
// =====================================================
function initSeatSelection() {
    const seatMap = document.querySelector('.seat-map');
    if (!seatMap) return;
    
    const selectedSeats = new Set();
    const maxSeats = parseInt(seatMap.dataset.maxSeats || 10);
    const ticketPrice = parseFloat(seatMap.dataset.price || 0);
    
    seatMap.querySelectorAll('.seat:not(.booked):not(.unavailable)').forEach(seat => {
        seat.addEventListener('click', function() {
            const seatId = this.dataset.seatId;
            
            if (this.classList.contains('selected')) {
                this.classList.remove('selected');
                selectedSeats.delete(seatId);
            } else {
                if (selectedSeats.size >= maxSeats) {
                    showToast('Maximum ' + maxSeats + ' seats allowed', 'warning');
                    return;
                }
                this.classList.add('selected');
                selectedSeats.add(seatId);
            }
            
            updateBookingSummary();
        });
    });
    
    function updateBookingSummary() {
        const count = selectedSeats.size;
        const total = count * ticketPrice;
        
        document.getElementById('selectedSeatCount').textContent = count;
        document.getElementById('totalAmount').textContent = '₹' + total.toFixed(2);
        
        document.getElementById('selectedSeatsInput').value = Array.from(selectedSeats).join(',');
    }
}

// =====================================================
// TOAST NOTIFICATIONS
// =====================================================
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;
    
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    const colors = {
        success: '#10B981',
        error: '#EF4444',
        warning: '#F59E0B',
        info: '#0EA5E9'
    };
    
    const toast = document.createElement('div');
    toast.className = 'toast show';
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-left: 4px solid ${colors[type]};
        border-radius: 12px;
        padding: 16px 20px;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
    `;
    
    toast.innerHTML = `
        <i class="${icons[type]}" style="color: ${colors[type]}; font-size: 1.2rem;"></i>
        <span style="flex: 1; color: var(--text-primary); font-weight: 500;">${message}</span>
        <button onclick="this.closest('.toast').remove()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// =====================================================
// LIVE SEARCH
// =====================================================
let searchTimeout;
function initLiveSearch(inputId, resultsId, url) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    
    if (!input || !results) return;
    
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            results.innerHTML = '';
            results.classList.remove('show');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(url + '?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        results.innerHTML = data.map(item => `
                            <a href="${item.url}" class="search-result-item">
                                <img src="${item.image}" alt="${item.title}" width="48" height="48">
                                <div>
                                    <strong>${item.title}</strong>
                                    <small>${item.category} • ${item.city}</small>
                                </div>
                                <span class="ms-auto text-primary fw-bold">₹${item.price}</span>
                            </a>
                        `).join('');
                        results.classList.add('show');
                    } else {
                        results.innerHTML = '<div class="search-result-item text-center text-muted">No events found</div>';
                        results.classList.add('show');
                    }
                })
                .catch(() => {
                    results.innerHTML = '<div class="search-result-item text-center text-muted">Search failed</div>';
                    results.classList.add('show');
                });
        }, 300);
    });
    
    // Close on click outside
    document.addEventListener('click', function(e) {
        if (!results.contains(e.target) && e.target !== input) {
            results.classList.remove('show');
        }
    });
}

// =====================================================
// CONFIRM DIALOG
// =====================================================
function confirmAction(message, callback) {
    const modal = document.getElementById('confirmModal');
    if (!modal) {
        if (confirm(message)) callback();
        return;
    }
    
    modal.querySelector('.modal-body').textContent = message;
    modal.querySelector('.btn-confirm').onclick = function() {
        callback();
        bootstrap.Modal.getInstance(modal).hide();
    };
    
    new bootstrap.Modal(modal).show();
}

// =====================================================
// COPY TO CLIPBOARD
// =====================================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Copied to clipboard!', 'success');
    });
}

// =====================================================
// SMOOTH SCROLL
// =====================================================
function smoothScroll(target) {
    const element = document.querySelector(target);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// =====================================================
// PRICE FORMATTING
// =====================================================
function formatPrice(amount) {
    return '₹' + parseFloat(amount).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// =====================================================
// DATE FORMATTING
// =====================================================
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-IN', options);
}

function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

// =====================================================
// RATING STARS
// =====================================================
function createRatingStars(rating, maxRating = 5) {
    const full = Math.floor(rating);
    const half = rating % 1 >= 0.5;
    const empty = maxRating - full - (half ? 1 : 0);
    
    return '★'.repeat(full) + (half ? '½' : '') + '☆'.repeat(empty);
}

// =====================================================
// LOADING STATE
// =====================================================
function showLoading(button) {
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
}

function hideLoading(button) {
    button.disabled = false;
    button.innerHTML = button.dataset.originalText || 'Submit';
}

// =====================================================
// STAT COUNTER ANIMATION (Hero stats)
// Animates from 0 to the target data-count value.
// =====================================================
function initCounterAnimation() {
    const counters = document.querySelectorAll('.stat-number[data-count]');
    if (!counters.length) return;

    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'), 10);
        const duration = 1500; // ms
        const startTime = performance.now();

        // If target is 0, just show 0 (no animation needed)
        if (isNaN(target) || target <= 0) {
            counter.textContent = isNaN(target) ? '0' : target;
            return;
        }

        // Reset to 0 first so the count-up animation is visible
        // (initial HTML content shows the real value as an SEO/no-JS fallback)
        counter.textContent = 0;

        function updateCounter(now) {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease-out for a smooth finish
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(eased * target);
            counter.textContent = current;

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        }

        requestAnimationFrame(updateCounter);
    });
}
