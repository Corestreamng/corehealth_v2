<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Links pharmacy products to the NHMIS drug groups that pharmacy-based rows count
 * (e.g. which products are "LLIN" or "IPTp SP"). Only linked products are counted.
 */
class NhmisProductMapping extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'nhmis_product_mappings';

    protected $fillable = ['indicator_code', 'product_id', 'notes'];

    /**
     * Drug groups. likes/regex/exclude are only Auto-Detect suggestions; counts use saved links.
     */
    public const INDICATORS = [
        'iptp_sp' => ['label' => 'IPTp: Sulfadoxine-Pyrimethamine (SP)', 'section' => 'Antenatal Care', 'rows' => '26-29', 'likes' => ['fansidar', 'sulfadoxine', 'pyrimethamine', 'maloxine', 'amalar', 'laridox', 'swidar'], 'regex' => '/(fansidar|sulfadoxine|pyrimethamine|maloxine|amalar|laridox|swidar)/'],
        'llin' => ['label' => 'Long-lasting insecticidal net (LLIN)', 'section' => 'Antenatal Care & Malaria', 'rows' => '30, 146', 'likes' => ['llin', 'bed net', 'mosquito net', 'insecticid'], 'regex' => '/\b(llin|bed\s*net|mosquito\s*net|insecticid\w*\s*(treated)?\s*net)\b/'],
        'ifa' => ['label' => 'Iron / folic acid (haematinics)', 'section' => 'Antenatal Care', 'rows' => '31', 'likes' => ['ferrous', 'fersolat', 'folic', 'fefol', 'ranferon', 'chemiron', 'haematinic', 'hematinic'], 'regex' => '/\b(ferrous|fersolat\w*|folic|fefol|ranferon|chemiron|ha?ematinic\w*)\b/'],
        'syphilis_treatment' => ['label' => 'Syphilis treatment (penicillin-class)', 'section' => 'Antenatal Care', 'rows' => '19', 'likes' => ['penicillin', 'benzathine', 'erythromycin', 'azithromycin', 'ceftriaxone', 'rocephin', 'ocexone'], 'regex' => '/(penicillin|benzathine|erythromycin|azithromycin|ceftriaxone|rocephin|ocexone)/', 'exclude' => '/oint/'],
        'misoprostol' => ['label' => 'Misoprostol (third stage of labour)', 'section' => 'Labour & Delivery', 'rows' => '42', 'likes' => ['misoprostol', 'cytotec'], 'regex' => '/(misoprostol|cytotec)/', 'exclude' => '/arthrotec|diclofenac/'],
        'mgso4' => ['label' => 'Magnesium sulphate (MgSO4)', 'section' => 'Labour & Delivery', 'rows' => '43', 'likes' => ['magnesium', 'mgso4'], 'regex' => '/(magnesium\s*sul|mgso4)/'],
        'act' => ['label' => 'Artemisinin-based combination therapy (ACT)', 'section' => 'Malaria', 'rows' => '155-156', 'likes' => ['artemether', 'lumefantrine', 'coartem', 'amodiaquine', 'piperaquine', 'artemisinin', 'lonart', 'alaxin', 'anate', 'camosunate', 'zomal'], 'regex' => '/(artemether|lumefantrine|coartem|amodiaquine|piperaquine|artemisinin|lonart|alaxin|\banate\b|camosunate|zomal)/', 'exclude' => '/\binj/'],
        'other_oral_antimalarial' => ['label' => 'Other oral antimalarials (non-ACT)', 'section' => 'Malaria', 'rows' => '157', 'likes' => ['quinine', 'chloroquine'], 'regex' => '/\b(quinine|chloroquine)\b/', 'exclude' => '/\binj|hydroxy/'],
        'rectal_artesunate' => ['label' => 'Rectal artesunate (pre-referral)', 'section' => 'Malaria', 'rows' => '158', 'likes' => ['artesunate'], 'regex' => '/artesunate.*(rectal|supp)|(rectal|supp).*artesunate/'],
        'artesunate_injection' => ['label' => 'Artesunate injection', 'section' => 'Malaria', 'rows' => '159', 'likes' => ['artesunate'], 'regex' => '/artesunate.*(inj|60\s*mg|120\s*mg)/', 'exclude' => '/amodiaquine|anate|rectal|supp/'],
        'other_injectable_antimalarial' => ['label' => 'Other injectable antimalarials', 'section' => 'Malaria', 'rows' => '160', 'likes' => ['artemether', 'quinine'], 'regex' => '/(artemether|quinine).*\binj|\binj.*(artemether|quinine)/'],
        'ors' => ['label' => 'Oral rehydration salts (ORS)', 'section' => 'IMCI', 'rows' => '111', 'likes' => ['oral rehydration', 'ors'], 'regex' => '/(oral\s*rehydration|\bors\b)/'],
        'zinc' => ['label' => 'Zinc (diarrhoea)', 'section' => 'IMCI', 'rows' => '111', 'likes' => ['zinc'], 'regex' => '/\bzinc\b/', 'exclude' => '/oxide|anusol/'],
        'amoxicillin' => ['label' => 'Amoxicillin (pneumonia)', 'section' => 'IMCI', 'rows' => '113', 'likes' => ['amoxi', 'amoxy'], 'regex' => '/amox[iy]/', 'exclude' => '/esofag|clarithro/'],
        'vitamin_a' => ['label' => 'Vitamin A supplement', 'section' => 'Nutrition', 'rows' => '105', 'likes' => ['vitamin a', 'retinol'], 'regex' => '/(vitamin\s*a\b|retinol)/'],
        'mnp' => ['label' => 'Micronutrient powder (MNP)', 'section' => 'Nutrition', 'rows' => '106', 'likes' => ['micronutrient powder', 'mnp'], 'regex' => '/(micronutrient\s*powder|\bmnp\b)/'],
        'deworming' => ['label' => 'Deworming (albendazole / mebendazole)', 'section' => 'Nutrition', 'rows' => '107', 'likes' => ['albendazole', 'mebendazole', 'vermox', 'zentel'], 'regex' => '/(albendazole|mebendazole|vermox|zentel)/'],
        'hbv_antiviral' => ['label' => 'Hepatitis B antivirals', 'section' => 'Hepatitis', 'rows' => '166', 'likes' => ['tenofovir', 'entecavir'], 'regex' => '/\b(tenofovir|entecavir)\b/'],
        'hcv_antiviral' => ['label' => 'Hepatitis C antivirals', 'section' => 'Hepatitis', 'rows' => '170', 'likes' => ['sofosbuvir', 'daclatasvir', 'velpatasvir', 'ledipasvir'], 'regex' => '/\b(sofosbuvir|daclatasvir|velpatasvir|ledipasvir)\b/'],
        'antivenom' => ['label' => 'Anti-snake venom', 'section' => 'NTDs', 'rows' => '184', 'likes' => ['venom', 'antivenin', 'asv'], 'regex' => '/(anti\s*-?\s*snake|venom|antivenin|\basv\b)/'],
        'fp_oral_pill' => ['label' => 'FP: Oral contraceptive pill', 'section' => 'Family Planning', 'rows' => '118-119', 'likes' => ['levofem', 'microgynon', 'combined oral', 'exluton', 'microlut', 'oral contracep'], 'regex' => '/(levofem|microgynon|combined\s*oral|exluton|microlut|oral\s*contracep)/'],
        'fp_emergency_pill' => ['label' => 'FP: Emergency contraceptive pill', 'section' => 'Family Planning', 'rows' => '120', 'likes' => ['postinor', 'emergency contracep', 'levonorgestrel 1.5'], 'regex' => '/(postinor|emergency\s*contracep|levonorgestrel\s*1\.5)/'],
        'fp_noristerat' => ['label' => 'FP: Noristerat injectable', 'section' => 'Family Planning', 'rows' => '121', 'likes' => ['noristerat'], 'regex' => '/noristerat/'],
        'fp_dmpa_im' => ['label' => 'FP: DMPA-IM (Depo-Provera)', 'section' => 'Family Planning', 'rows' => '121', 'likes' => ['depo', 'dmpa'], 'regex' => '/(depo[\s-]*provera|\bdmpa\b)/', 'exclude' => '/sayana|\bsc\b|subcut/'],
        'fp_dmpa_sc' => ['label' => 'FP: DMPA-SC (Sayana Press)', 'section' => 'Family Planning', 'rows' => '121', 'likes' => ['sayana', 'dmpa-sc', 'dmpa sc'], 'regex' => '/(sayana|dmpa[\s-]*sc)/'],
        'fp_iud' => ['label' => 'FP: Copper IUD (Cu-T 380A)', 'section' => 'Family Planning', 'rows' => '122', 'likes' => ['iucd', 'copper t', 'iud'], 'regex' => '/(iucd|copper\s*t\b|\biud\b|cu[\s-]*t\s*380)/'],
        'fp_lng_ius' => ['label' => 'FP: Hormonal IUS (LNG-IUS)', 'section' => 'Family Planning', 'rows' => '122', 'likes' => ['mirena', 'lng-ius', 'lng ius'], 'regex' => '/(mirena|lng[\s-]*ius)/'],
        'fp_implanon' => ['label' => 'FP: Implanon NXT implant', 'section' => 'Family Planning', 'rows' => '123', 'likes' => ['implanon'], 'regex' => '/implanon/'],
        'fp_jadelle' => ['label' => 'FP: Jadelle implant', 'section' => 'Family Planning', 'rows' => '123', 'likes' => ['jadelle'], 'regex' => '/jadelle/'],
        'fp_male_condom' => ['label' => 'FP: Male condom', 'section' => 'Family Planning', 'rows' => '125-126', 'likes' => ['condom'], 'regex' => '/condom/', 'exclude' => '/female|femidom/'],
        'fp_female_condom' => ['label' => 'FP: Female condom', 'section' => 'Family Planning', 'rows' => '125-126', 'likes' => ['female condom', 'femidom'], 'regex' => '/(female\s*condom|femidom)/'],
    ];

    public function product()
    {
        // Inactive products keep their dispensing history, so links must still show their names
        return $this->belongsTo(Product::class, 'product_id', 'id')->withoutGlobalScope('active');
    }

    /**
     * @return array<int, string> product id => product name, for products linked to the drug group
     */
    public static function getLinkedProducts(string $indicatorCode): array
    {
        return DB::table('nhmis_product_mappings as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->where('m.indicator_code', $indicatorCode)
            ->pluck('p.product_name', 'p.id')
            ->all();
    }

    /** Catalogue products whose names match the drug group's suggestion pattern. */
    public static function suggestProducts(string $indicatorCode): array
    {
        $meta = self::INDICATORS[$indicatorCode] ?? null;
        if (!$meta) {
            return [];
        }

        return DB::table('products')
            ->where(function ($q) use ($meta) {
                foreach ($meta['likes'] as $l) {
                    $q->orWhere('product_name', 'like', '%' . $l . '%');
                }
            })
            ->pluck('product_name', 'id')
            ->filter(function ($name) use ($meta) {
                $n = strtolower($name);

                return preg_match($meta['regex'], $n) && (empty($meta['exclude']) || !preg_match($meta['exclude'], $n));
            })
            ->all();
    }

    /** Link suggested products that are not linked yet. Returns the number of links added. */
    public static function autoPopulateDefaults(): int
    {
        $added = 0;
        foreach (array_keys(self::INDICATORS) as $code) {
            foreach (array_keys(self::suggestProducts($code)) as $productId) {
                $link = static::firstOrCreate(
                    ['indicator_code' => $code, 'product_id' => $productId],
                    ['notes' => 'Auto-detected by product name']
                );
                if ($link->wasRecentlyCreated) {
                    $added++;
                }
            }
        }

        return $added;
    }
}
