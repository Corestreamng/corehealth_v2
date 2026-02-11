{{--
    Journal Entry Preview Component
    Shows a preview of the journal entry that will be created for a transaction.

    Usage:
    @include('accounting.partials.journal-entry-preview', [
        'title' => 'Journal Entry Preview',
        'description' => 'This entry will be created when saved.',
        'lines' => [
            ['account' => 'Cash in Hand (1010)', 'debit' => 1000, 'credit' => 0],
            ['account' => 'Revenue (4000)', 'debit' => 0, 'credit' => 1000],
        ],
        'dynamic' => true, // if true, body id will be used for JS updates
        'bodyId' => 'jePreviewBody',
        'debitTotalId' => 'jeTotalDebit',
        'creditTotalId' => 'jeTotalCredit',
    ])

    Standard Account Code Ranges (from ChartOfAccountsSeeder):
    - 10xx: Current Assets (Cash 1010, Bank 1020, Petty Cash 1030)
    - 11xx: Receivables (AR Patients 1100, AR HMO 1110)
    - 12xx: Inventory (Pharmacy 1300, Medical Supplies 1310)
    - 14xx: Fixed Assets (Equipment 1400, Furniture 1410, Computers 1420, Vehicles 1430, Building 1440, Land 1450, Other 1460)
    - 15xx: Accumulated Depreciation (contra-asset accounts)
    - 20xx: Current Liabilities
    - 21xx: Payables (AP 2100, Customer Deposits 2200)
    - 30xx: Equity
    - 40xx: Revenue
    - 50xx-60xx: Expenses
--}}

<div class="card-modern bg-light {{ $class ?? '' }}">
    <div class="card-body py-2 px-3">
        <h6 class="mb-2">
            <i class="mdi mdi-book-open-variant mr-1"></i>{{ $title ?? 'Journal Entry Preview' }}
        </h6>
        @if(isset($description))
            <small class="text-muted d-block mb-2">{{ $description }}</small>
        @endif

        <table class="table table-sm mb-0" style="font-size: 0.85rem;">
            <thead style="background: #495057; color: white;">
                <tr>
                    <th style="width: 50%;">Account</th>
                    <th class="text-right" style="width: 25%;">Debit</th>
                    <th class="text-right" style="width: 25%;">Credit</th>
                </tr>
            </thead>
            <tbody id="{{ $bodyId ?? 'jePreviewBody' }}">
                @if(isset($lines) && count($lines) > 0)
                    @foreach($lines as $line)
                        <tr>
                            <td>{{ $line['account'] }}</td>
                            <td class="text-right">{{ $line['debit'] > 0 ? '₦' . number_format($line['debit'], 2) : '-' }}</td>
                            <td class="text-right">{{ $line['credit'] > 0 ? '₦' . number_format($line['credit'], 2) : '-' }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="3" class="text-center text-muted py-2">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            Enter details to see preview
                        </td>
                    </tr>
                @endif
            </tbody>
            <tfoot style="background: #f8f9fa; font-weight: 600;">
                <tr>
                    <td>TOTALS</td>
                    <td class="text-right" id="{{ $debitTotalId ?? 'jeTotalDebit' }}">
                        @if(isset($lines))
                            ₦{{ number_format(collect($lines)->sum('debit'), 2) }}
                        @else
                            ₦0.00
                        @endif
                    </td>
                    <td class="text-right" id="{{ $creditTotalId ?? 'jeTotalCredit' }}">
                        @if(isset($lines))
                            ₦{{ number_format(collect($lines)->sum('credit'), 2) }}
                        @else
                            ₦0.00
                        @endif
                    </td>
                </tr>
            </tfoot>
        </table>

        @if(isset($note))
            <div class="alert alert-info mt-2 mb-0 py-1 px-2" style="font-size: 0.8rem;">
                <i class="mdi mdi-information mr-1"></i>{{ $note }}
            </div>
        @endif
    </div>
</div>
