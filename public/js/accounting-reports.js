/**
 * Accounting Reports JavaScript Module
 * Handles report generation, filtering, and export functionality
 */

const AccountingReports = {
    /**
     * Initialize the reports module
     */
    init: function() {
        this.bindEvents();
        this.initDateRangePicker();
        this.initPeriodSelector();
    },

    /**
     * Bind event listeners
     */
    bindEvents: function() {
        // Auto-submit on period selection
        const periodSelect = document.querySelector('select[name="fiscal_period_id"]');
        if (periodSelect) {
            periodSelect.addEventListener('change', function() {
                if (this.value) {
                    // When a period is selected, clear custom dates and submit
                    const startDate = document.querySelector('input[name="start_date"]');
                    const endDate = document.querySelector('input[name="end_date"]');
                    if (startDate) startDate.value = '';
                    if (endDate) endDate.value = '';
                    this.closest('form').submit();
                }
            });
        }

        // Clear period when custom dates are used
        const dateInputs = document.querySelectorAll('input[name="start_date"], input[name="end_date"]');
        dateInputs.forEach(input => {
            input.addEventListener('change', function() {
                const periodSelect = document.querySelector('select[name="fiscal_period_id"]');
                if (periodSelect) {
                    periodSelect.value = '';
                }
            });
        });

        // Print button
        const printBtns = document.querySelectorAll('[data-action="print"]');
        printBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                window.print();
            });
        });

        // Export buttons with loading state
        const exportBtns = document.querySelectorAll('[data-export]');
        exportBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';
                this.disabled = true;

                // Re-enable after 5 seconds (export should complete by then)
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 5000);
            });
        });
    },

    /**
     * Initialize date range picker if available
     */
    initDateRangePicker: function() {
        // If using a date range picker library, initialize here
        // Example for flatpickr:
        if (typeof flatpickr !== 'undefined') {
            flatpickr('input[name="start_date"]', {
                dateFormat: 'Y-m-d',
                maxDate: new Date()
            });
            flatpickr('input[name="end_date"]', {
                dateFormat: 'Y-m-d',
                maxDate: new Date()
            });
        }
    },

    /**
     * Initialize period selector with smart defaults
     */
    initPeriodSelector: function() {
        // Add quick select buttons if container exists
        const quickSelectContainer = document.getElementById('quick-period-select');
        if (quickSelectContainer) {
            const buttons = [
                { label: 'This Month', period: 'this_month' },
                { label: 'Last Month', period: 'last_month' },
                { label: 'This Quarter', period: 'this_quarter' },
                { label: 'This Year', period: 'this_year' }
            ];

            buttons.forEach(btn => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-secondary me-1';
                button.textContent = btn.label;
                button.addEventListener('click', () => this.setQuickPeriod(btn.period));
                quickSelectContainer.appendChild(button);
            });
        }
    },

    /**
     * Set quick period dates
     */
    setQuickPeriod: function(period) {
        const startInput = document.querySelector('input[name="start_date"]');
        const endInput = document.querySelector('input[name="end_date"]');
        const periodSelect = document.querySelector('select[name="fiscal_period_id"]');

        if (!startInput || !endInput) return;

        const today = new Date();
        let startDate, endDate;

        switch (period) {
            case 'this_month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'last_month':
                startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'this_quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                break;
            case 'this_year':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
        }

        startInput.value = this.formatDate(startDate);
        endInput.value = this.formatDate(endDate);

        if (periodSelect) {
            periodSelect.value = '';
        }
    },

    /**
     * Format date as YYYY-MM-DD
     */
    formatDate: function(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    },

    /**
     * Format number as currency
     */
    formatCurrency: function(amount, currency = '₦') {
        return currency + ' ' + parseFloat(amount).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    /**
     * Toggle report section visibility
     */
    toggleSection: function(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.toggle('collapsed');
            const icon = section.previousElementSibling?.querySelector('.toggle-icon');
            if (icon) {
                icon.classList.toggle('fa-chevron-down');
                icon.classList.toggle('fa-chevron-right');
            }
        }
    },

    /**
     * Save report filter as preset
     */
    saveFilterPreset: function(name) {
        const form = document.querySelector('form');
        if (!form) return;

        const formData = new FormData(form);
        const preset = {};
        formData.forEach((value, key) => {
            preset[key] = value;
        });

        // Save to localStorage
        const presets = JSON.parse(localStorage.getItem('accounting_report_presets') || '{}');
        presets[name] = preset;
        localStorage.setItem('accounting_report_presets', JSON.stringify(presets));

        alert('Filter preset saved: ' + name);
    },

    /**
     * Load report filter preset
     */
    loadFilterPreset: function(name) {
        const presets = JSON.parse(localStorage.getItem('accounting_report_presets') || '{}');
        const preset = presets[name];

        if (!preset) {
            alert('Preset not found: ' + name);
            return;
        }

        Object.keys(preset).forEach(key => {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = preset[key];
            }
        });
    },

    /**
     * Get saved filter presets
     */
    getFilterPresets: function() {
        return JSON.parse(localStorage.getItem('accounting_report_presets') || '{}');
    },

    /**
     * Delete filter preset
     */
    deleteFilterPreset: function(name) {
        const presets = JSON.parse(localStorage.getItem('accounting_report_presets') || '{}');
        delete presets[name];
        localStorage.setItem('accounting_report_presets', JSON.stringify(presets));
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    AccountingReports.init();
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AccountingReports;
}
