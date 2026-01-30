/**
 * Journal Entry JavaScript Module
 * Handles dynamic line management, balance calculation, and form validation
 */

const JournalEntry = {
    lineIndex: 0,
    accounts: [],

    /**
     * Initialize the journal entry form
     */
    init: function(existingLines = 0, accountsData = []) {
        this.lineIndex = existingLines || 2;
        this.accounts = accountsData;

        this.bindEvents();
        this.updateTotals();
    },

    /**
     * Bind all event listeners
     */
    bindEvents: function() {
        // Add line button
        const addLineBtn = document.getElementById('addLine');
        if (addLineBtn) {
            addLineBtn.addEventListener('click', () => this.addLine());
        }

        // Remove line buttons (delegated)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-line')) {
                this.removeLine(e.target.closest('.line-row'));
            }
        });

        // Debit/Credit inputs (delegated)
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('debit-input') ||
                e.target.classList.contains('credit-input')) {
                this.handleAmountChange(e.target);
                this.updateTotals();
            }
        });

        // Form submission validation
        const form = document.getElementById('journalEntryForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm()) {
                    e.preventDefault();
                }
            });
        }

        // Account selection change - auto-suggest account type amount
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('account-select')) {
                this.handleAccountChange(e.target);
            }
        });
    },

    /**
     * Add a new journal line
     */
    addLine: function() {
        const linesBody = document.getElementById('linesBody');
        if (!linesBody) return;

        const row = document.createElement('tr');
        row.className = 'line-row';

        let accountOptions = '<option value="">Select Account</option>';
        this.accounts.forEach(account => {
            accountOptions += `<option value="${account.id}" data-type="${account.normal_balance}">${account.code} - ${account.name}</option>`;
        });

        row.innerHTML = `
            <td>
                <select name="lines[${this.lineIndex}][account_id]" class="form-select account-select" required>
                    ${accountOptions}
                </select>
            </td>
            <td>
                <input type="number" name="lines[${this.lineIndex}][debit]"
                       class="form-control debit-input" step="0.01" min="0" value="0">
            </td>
            <td>
                <input type="number" name="lines[${this.lineIndex}][credit]"
                       class="form-control credit-input" step="0.01" min="0" value="0">
            </td>
            <td>
                <input type="text" name="lines[${this.lineIndex}][memo]" class="form-control" placeholder="Optional memo...">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        linesBody.appendChild(row);
        this.lineIndex++;
        this.updateTotals();

        // Focus on the new account select
        row.querySelector('.account-select').focus();
    },

    /**
     * Remove a journal line
     */
    removeLine: function(row) {
        const lineCount = document.querySelectorAll('.line-row').length;

        if (lineCount <= 2) {
            this.showAlert('Minimum 2 lines required for a journal entry.', 'warning');
            return;
        }

        row.remove();
        this.updateTotals();
    },

    /**
     * Handle amount input change - mutual exclusivity
     */
    handleAmountChange: function(input) {
        const row = input.closest('.line-row');
        const debitInput = row.querySelector('.debit-input');
        const creditInput = row.querySelector('.credit-input');

        if (input.classList.contains('debit-input') && parseFloat(input.value) > 0) {
            creditInput.value = 0;
        } else if (input.classList.contains('credit-input') && parseFloat(input.value) > 0) {
            debitInput.value = 0;
        }
    },

    /**
     * Handle account selection change
     */
    handleAccountChange: function(select) {
        const selectedOption = select.options[select.selectedIndex];
        const normalBalance = selectedOption.dataset.type;
        const row = select.closest('.line-row');
        const debitInput = row.querySelector('.debit-input');
        const creditInput = row.querySelector('.credit-input');

        // Highlight the expected column based on normal balance
        if (normalBalance === 'debit') {
            debitInput.classList.add('border-primary');
            creditInput.classList.remove('border-primary');
        } else if (normalBalance === 'credit') {
            creditInput.classList.add('border-primary');
            debitInput.classList.remove('border-primary');
        }
    },

    /**
     * Update totals and balance status
     */
    updateTotals: function() {
        let totalDebit = 0;
        let totalCredit = 0;

        document.querySelectorAll('.debit-input').forEach(input => {
            totalDebit += parseFloat(input.value) || 0;
        });

        document.querySelectorAll('.credit-input').forEach(input => {
            totalCredit += parseFloat(input.value) || 0;
        });

        // Update display
        const totalDebitEl = document.getElementById('totalDebit');
        const totalCreditEl = document.getElementById('totalCredit');
        const summaryTotal = document.getElementById('summaryTotal');
        const summaryLines = document.getElementById('summaryLines');
        const summaryBalance = document.getElementById('summaryBalance');
        const balanceAlert = document.getElementById('balanceAlert');
        const balanceSuccess = document.getElementById('balanceSuccess');
        const submitBtn = document.getElementById('submitBtn');

        if (totalDebitEl) totalDebitEl.textContent = totalDebit.toFixed(2);
        if (totalCreditEl) totalCreditEl.textContent = totalCredit.toFixed(2);
        if (summaryTotal) summaryTotal.textContent = '₦ ' + totalDebit.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        if (summaryLines) summaryLines.textContent = document.querySelectorAll('.line-row').length;

        const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;
        const hasAmount = totalDebit > 0 || totalCredit > 0;

        // Balance indicators
        if (balanceAlert) balanceAlert.style.display = (!isBalanced && hasAmount) ? 'block' : 'none';
        if (balanceSuccess) balanceSuccess.style.display = (isBalanced && hasAmount) ? 'block' : 'none';

        if (summaryBalance) {
            if (!hasAmount) {
                summaryBalance.innerHTML = '<span class="badge bg-secondary">Enter amounts</span>';
            } else if (isBalanced) {
                summaryBalance.innerHTML = '<span class="badge bg-success">Balanced</span>';
            } else {
                const diff = Math.abs(totalDebit - totalCredit);
                summaryBalance.innerHTML = `<span class="badge bg-danger">Off by ${diff.toFixed(2)}</span>`;
            }
        }

        // Disable submit if not balanced
        if (submitBtn) {
            submitBtn.disabled = !isBalanced || !hasAmount;
        }

        return { totalDebit, totalCredit, isBalanced, hasAmount };
    },

    /**
     * Validate form before submission
     */
    validateForm: function() {
        const { totalDebit, totalCredit, isBalanced, hasAmount } = this.updateTotals();
        const lineCount = document.querySelectorAll('.line-row').length;

        // Check minimum lines
        if (lineCount < 2) {
            this.showAlert('Minimum 2 lines required for a journal entry.', 'danger');
            return false;
        }

        // Check if entry is balanced
        if (!isBalanced) {
            this.showAlert('Entry must be balanced. Total Debits must equal Total Credits.', 'danger');
            return false;
        }

        // Check if amounts are entered
        if (!hasAmount) {
            this.showAlert('Please enter debit and credit amounts.', 'danger');
            return false;
        }

        // Check each line has an account
        let allAccountsSelected = true;
        document.querySelectorAll('.account-select').forEach(select => {
            if (!select.value) {
                allAccountsSelected = false;
                select.classList.add('is-invalid');
            } else {
                select.classList.remove('is-invalid');
            }
        });

        if (!allAccountsSelected) {
            this.showAlert('Please select an account for all lines.', 'danger');
            return false;
        }

        // Check each line has either debit or credit
        let allLinesHaveAmount = true;
        document.querySelectorAll('.line-row').forEach(row => {
            const debit = parseFloat(row.querySelector('.debit-input').value) || 0;
            const credit = parseFloat(row.querySelector('.credit-input').value) || 0;
            if (debit === 0 && credit === 0) {
                allLinesHaveAmount = false;
                row.classList.add('table-warning');
            } else {
                row.classList.remove('table-warning');
            }
        });

        if (!allLinesHaveAmount) {
            this.showAlert('Each line must have a debit or credit amount.', 'warning');
            return false;
        }

        return true;
    },

    /**
     * Show alert message
     */
    showAlert: function(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.je-alert');
        existingAlerts.forEach(alert => alert.remove());

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show je-alert`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const form = document.getElementById('journalEntryForm');
        if (form) {
            form.insertBefore(alert, form.firstChild);
        }

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    },

    /**
     * Reverse amounts (swap debits and credits)
     */
    reverseAmounts: function() {
        document.querySelectorAll('.line-row').forEach(row => {
            const debitInput = row.querySelector('.debit-input');
            const creditInput = row.querySelector('.credit-input');

            const temp = debitInput.value;
            debitInput.value = creditInput.value;
            creditInput.value = temp;
        });

        this.updateTotals();
    },

    /**
     * Clear all lines and reset form
     */
    clearForm: function() {
        if (!confirm('Are you sure you want to clear all lines?')) return;

        const linesBody = document.getElementById('linesBody');
        if (linesBody) {
            linesBody.innerHTML = '';
        }

        this.lineIndex = 0;
        this.addLine();
        this.addLine();
    },

    /**
     * Import lines from clipboard (CSV format)
     */
    importFromClipboard: async function() {
        try {
            const text = await navigator.clipboard.readText();
            const lines = text.trim().split('\n');

            lines.forEach(line => {
                const parts = line.split(/[,\t]/);
                if (parts.length >= 3) {
                    this.addLine();
                    const lastRow = document.querySelector('.line-row:last-child');
                    // Try to match account by code
                    const accountSelect = lastRow.querySelector('.account-select');
                    const accountCode = parts[0].trim();
                    const options = accountSelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.startsWith(accountCode)) {
                            accountSelect.selectedIndex = i;
                            break;
                        }
                    }
                    lastRow.querySelector('.debit-input').value = parseFloat(parts[1]) || 0;
                    lastRow.querySelector('.credit-input').value = parseFloat(parts[2]) || 0;
                    if (parts[3]) {
                        lastRow.querySelector('input[name$="[memo]"]').value = parts[3].trim();
                    }
                }
            });

            this.updateTotals();
            this.showAlert('Lines imported from clipboard.', 'success');
        } catch (err) {
            this.showAlert('Failed to read from clipboard. Please check permissions.', 'warning');
        }
    }
};

// Initialize when DOM is ready - will be called with parameters from the page
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a journal entry page
    if (document.getElementById('journalEntryForm')) {
        // Get accounts data from the page (set by blade template)
        const accountsData = window.journalEntryAccounts || [];
        const existingLines = document.querySelectorAll('.line-row').length;
        JournalEntry.init(existingLines, accountsData);
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = JournalEntry;
}
