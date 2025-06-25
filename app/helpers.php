<?php

use App\Models\ApplicationStatu;
use App\Models\User;
use App\Models\StoreStoke;
use App\Models\patient;
use App\Models\LabService;
use App\Models\ModeOfPayment;
use App\Models\BudgetYear;
use App\Models\Dependant;
use App\Models\StoreStock;
use Illuminate\Support\Facades\Lang;

$NAIRA_CODE = '₦';
$REFERENCE_RANDOM_NUMBER_LENGTH = 6;
$REFERENCE_FILE_NUMBER_LENGTH = 4;
$CASH_TRANSACTION_NUMBER_LENGTH = 4;

if (!function_exists('generateForm')) {
    function generateForm($formData)
    {
        $html = '';

        foreach ($formData as $data) {
            $html .= '<div class="form-group">';
            $html .= '<label class="form-label">' . $data->name;
            if ($data->is_required == 'required') {
                $html .= "<span class = 'text-danger'> *</span>";
            }
            $html .= '</label>';

            if ($data->type == 'text') {
                $html .= '<input type="text" class="form-control form-control-sm" name="' . $data->label . '" value="' . old($data->label) . '" ' . ($data->is_required == 'required' ? 'required' : '') . '>';
            } elseif ($data->type == 'textarea') {
                $html .= '<textarea class="form-control form-control-sm" name="' . $data->label . '" ' . ($data->is_required == 'required' ? 'required' : '') . '>' . old($data->label) . '</textarea>';
            } elseif ($data->type == 'select') {
                $html .= '<select class="form-control form-control-sm" name="' . $data->label . '" ' . ($data->is_required == 'required' ? 'required' : '') . '>';
                $html .= '<option value="">Select One</option>';
                foreach ($data->options as $item) {
                    $html .= '<option value="' . $item . '" ' . (($item == old($data->label)) ? 'selected' : '') . '>' . $item . '</option>';
                }
                $html .= '</select>';
            } elseif ($data->type == 'checkbox') {
                foreach ($data->options as $option) {
                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" name="' . $data->label . '[]" type="checkbox" value="' . $option . '" id="' . $data->label . '_' . titleToKey($option) . '">';
                    $html .= '<label class="form-check-label" for="' . $data->label . '_' . titleToKey($option) . '">' . $option;
                    $html .= '</label>';
                    $html .= '</div>';
                }
            } elseif ($data->type == 'radio') {
                foreach ($data->options as $option) {
                    $html .= '<div class="form-check">';
                    $html .= '<input class="form-check-input" name="' . $data->label . '" type="radio" value="' . $option . '" id="' . $data->label . '_' . titleToKey($option) . '" ' . (($option == old($data->label) ? 'checked' : '')) . '>';
                    $html .= '<label class="form-check-label" for="' . $data->label . '_' . titleToKey($option) . '">' . $option;
                    $html .= '</label>';
                    $html .= '</div>';
                }
            } elseif ($data->type == 'file') {
                $html .= '<input type="file" class="form-control form-control-sm custom-input-field" name="' . $data->label . '" ' . ($data->is_required == 'required' ? 'required' : '') . ' accept="' . implode(',', array_map(function ($ext) {
                    return '.' . $ext;
                }, explode(',', $data->extensions))) . '">';
                $html .= '<pre class="text--base mt-1">Supported mimes: ' . $data->extensions . '</pre>';
            }

            $html .= '</div>';
        }

        return $html;
    }
}

if (!function_exists('keyToTitle')) {

    function keyToTitle($text)
    {
        return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
    }
}

if (!function_exists('titleToKey')) {

    function titleToKey($text)
    {
        return strtolower(str_replace(' ', '_', $text));
    }
}

if (!function_exists('generate_invoice_no')) {

    function generate_invoice_no()
    {
        $dt = \Carbon\Carbon::now();
        $timestamp = $dt->hour . $dt->minute . $dt->second;
        $referenceNumber = randomDigits(REFERENCE_RANDOM_NUMBER_LENGTH);
        return $referenceNumber . $timestamp;
    }
}

if (!function_exists('randomDigits')) {

    function randomDigits($numDigits)
    {
        if ($numDigits <= 0) {
            return '';
        }
        return mt_rand(1, 9) . randomDigits($numDigits - 1);
    }
}
if (!function_exists('toMoney')) {

    // Naira Symbol &#8358;
    function toMoney($val, $symbol = '₦', $r = 2)
    {
        $n = $val;
        $c = is_float($n) ? 1 : number_format($n, $r);
        $d = '.';
        $t = ',';
        $sign = ($n < 0) ? '-' : '';
        $i = $n = number_format(abs($n), $r);
        $j = (($j = strlen($i)) > 3) ? $j % 3 : 0;

        return  $symbol . $sign . ($j ? substr($i, 0, $j) + $t : '') . preg_replace('/(\d{3})(?=\d)/', "$1" + $t, substr($i, $j));
    }
}

if (!function_exists('formatMoney')) {

    // Number Format used by the System by Ghaji
    function formatMoney($money)
    {

        $formatted = "₦" . number_format(sprintf('%0.2f', preg_replace("/[^0-9.]/", "", $money)), 2);
        return $money < 0 ? "({$formatted})" : "{$formatted}";
    }
}
if (!function_exists('convert_number_to_words')) {

    function convert_number_to_words($number)
    {
        $hyphen      = '-';
        $conjunction = ' and ';
        $separator   = ', ';
        $negative    = 'negative ';
        $decimal     = ' point ';
        $dictionary  = array(
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'fourty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            1000000             => 'million',
            1000000000          => 'billion',
            1000000000000       => 'trillion',
            1000000000000000    => 'quadrillion',
            1000000000000000000 => 'quintillion'
        );

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . convert_number_to_words(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . convert_number_to_words($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = convert_number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= convert_number_to_words($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = array();
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return ucwords($string);
    }
}

if (!function_exists('generateTransactionId')) {

    function generateTransactionId()
    {
        $dt = \Carbon\Carbon::now();
        // $year = $dt->year;
        $timestamp = $dt->minute . $dt->second . $dt->year;
        $transactionNumber = randomDigits(REFERENCE_RANDOM_NUMBER_LENGTH);
        return  $transactionNumber . $timestamp;
    }
}

if (!function_exists('appsettings')) {

    function appsettings()
    {
        $app = ApplicationStatu::first();

        // dd($app);
        return  $app;
    }
}

if (!function_exists('userfullname')) {

    function userfullname($id)
    {

        $user = User::find($id);
        if ($user) {
            $othername = ($user->othername) ? $user->othername : ' ';
            $fullname = $user->surname . ' ' . $user->firstname . ' ' . $othername;

            return  ucwords($fullname);
        } else {
            return 'N/A';
        }
    }
}

if (!function_exists('dateSplit')) {

    function dateSplit($date)
    {
        $getDate = explode(' ', $date);
        $mainDate = $getDate[0];
        $mainTime = $getDate[1];

        return $mainDate;
    }
}
if (!function_exists('reOrderAlertFlag')) {

    function reOrderAlertFlag($productId, $reOrderAlert)
    {
        $storeStocks = StoreStock::where('product_id', '=', $productId)->get();
        $val = "";

        foreach ($storeStocks as $storeStock) {

            $getStockCurrentQ = $storeStock->current_quantity;

            if ($getStockCurrentQ > $reOrderAlert) {
                $val = '<span class="badge badge-success"> In Stock </span>';
            } elseif ($getStockCurrentQ == $reOrderAlert) {
                $val .= '<span class="badge badge-secondary"> Low in Stock </span>';
            } elseif ($getStockCurrentQ == 0) {
                $val .= '<span class="badge badge-danger"> Out of Stock </span>';
            } elseif ($getStockCurrentQ < $reOrderAlert) {
                $val .= '<span class="badge badge-dark"> Very Low </span>';
            } else {
                $val .= '<span class="badge badge-info"> Review Product </span>';
            }
        }

        return $val;
    }
}

if (!function_exists('generateFileNo')) {

    function generateFileNo()
    {
        // $dt = \Carbon\Carbon::now();
        // // $year = $dt->year;
        // $timestamp =  $dt->year . $dt->month;
        // $fileNumber = randomDigits(REFERENCE_FILE_NUMBER_LENGTH);
        // return  $fileNumber . $timestamp;
        $p = \App\Models\patient::orderBy('file_no', 'DESC')->first()->file_no;
        return $p + 1;
    }
}


if (!function_exists('generateCashPaymentTransaction')) {

    function generateCashPaymentTransaction()
    {
        $dt = \Carbon\Carbon::now();
        // $year = $dt->year;
        $tstamp = $dt->year . $dt->minute . $dt->second;
        $transNumber = randomDigits(CASH_TRANSACTION_NUMBER_LENGTH);
        return  $transNumber . $tstamp;
    }
}


// function getLabId($id)
// {
//     $getLab = LabService::find($id);
//     $getLab_id = $getLab->lab_id;
//     return $getLab_id;
// }
if (!function_exists('showFileNumber')) {

    function showFileNumber($id)
    {
        $pfile = patient::where('user_id', '=', $id)->first();
        $getItem = $pfile->file_no;
        return $getItem;
    }
}
