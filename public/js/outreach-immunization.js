/**
 * Community Outreach & Campaign Immunization Tally Module
 * Conforms to WHO EPI Form 001, DHIS2, and NHMIS 2019 standards.
 * Features: Stepped Multi-Column Flow, Full Vaccine Stock Sources (Hospital Store, Govt Free EPI, Partner),
 * and Comprehensive Reports Dashboard with Matrix & Cold Chain Reconciliation.
 */
(function(window, $) {
    'use strict';

    var OutreachImmunization = {};

    var _currentStep = 1;
    var _customRowIndex = 1000;
    var _currentReportData = null;
    var _storeInventory = { products: {}, antigen_mappings: {}, rawProducts: [] };
    var _activePickerRowIndex = null;
    var _pickerSearchTimer = null;
    var _activePickerFilter = 'all';
    var _rowStockOverrides = {}; // rowIdx => { product_id, auto_deduct }

    // Standard WHO/NHMIS Antigens Catalog with Schedule Milestones & Clinical Formulation
    var DEFAULT_ANTIGENS = [
        // 1. Birth Cohort
        {
            groupKey: 'birth',
            groupTitle: 'Birth Visit (0–14 Days)',
            groupSub: 'Newborns (0–14 days) • Prevent Tuberculosis, Polio & Hepatitis B',
            groupCategory: 'infants',
            groupIcon: 'mdi-baby-face-outline',
            groupBundle: 'birth',
            vaccine: 'BCG',
            dose: 'Birth Dose',
            doseNum: 1,
            doseType: 'birth',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intradermal (ID)',
            targetDisease: 'Tuberculosis',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'birth',
            groupTitle: 'Birth Visit (0–14 Days)',
            groupSub: 'Newborns (0–14 days) • Prevent Tuberculosis, Polio & Hepatitis B',
            groupCategory: 'infants',
            groupIcon: 'mdi-baby-face-outline',
            groupBundle: 'birth',
            vaccine: 'OPV',
            dose: 'Dose 0 (Birth)',
            doseNum: 0,
            doseType: 'birth',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Drops (2 drops)',
            targetDisease: 'Poliomyelitis',
            icon: 'mdi-water'
        },
        {
            groupKey: 'birth',
            groupTitle: 'Birth Visit (0–14 Days)',
            groupSub: 'Newborns (0–14 days) • Prevent Tuberculosis, Polio & Hepatitis B',
            groupCategory: 'infants',
            groupIcon: 'mdi-baby-face-outline',
            groupBundle: 'birth',
            vaccine: 'HepB',
            dose: 'Dose 0 (Birth)',
            doseNum: 0,
            doseType: 'birth',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Hepatitis B',
            icon: 'mdi-needle'
        },

        // 2. 6 Weeks Cohort
        {
            groupKey: 'week6',
            groupTitle: '6-Week Routine Schedule (1½ Months)',
            groupSub: 'Infants at 6 weeks • 4-antigen primary protection cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week6',
            vaccine: 'OPV',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Drops (2 drops)',
            targetDisease: 'Poliomyelitis',
            icon: 'mdi-water'
        },
        {
            groupKey: 'week6',
            groupTitle: '6-Week Routine Schedule (1½ Months)',
            groupSub: 'Infants at 6 weeks • 4-antigen primary protection cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week6',
            vaccine: 'Pentavalent',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'DTP-HepB-Hib',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week6',
            groupTitle: '6-Week Routine Schedule (1½ Months)',
            groupSub: 'Infants at 6 weeks • 4-antigen primary protection cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week6',
            vaccine: 'PCV',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Pneumococcal',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week6',
            groupTitle: '6-Week Routine Schedule (1½ Months)',
            groupSub: 'Infants at 6 weeks • 4-antigen primary protection cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week6',
            vaccine: 'Rotavirus',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Solution (1.5 ml)',
            targetDisease: 'Rotavirus Diarrhoea',
            icon: 'mdi-water'
        },

        // 3. 10 Weeks Cohort
        {
            groupKey: 'week10',
            groupTitle: '10-Week Routine Schedule (2½ Months)',
            groupSub: 'Infants at 10 weeks • 2nd primary immunisation cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week10',
            vaccine: 'OPV',
            dose: 'Dose 2',
            doseNum: 2,
            doseType: 'dose2',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Drops (2 drops)',
            targetDisease: 'Poliomyelitis',
            icon: 'mdi-water'
        },
        {
            groupKey: 'week10',
            groupTitle: '10-Week Routine Schedule (2½ Months)',
            groupSub: 'Infants at 10 weeks • 2nd primary immunisation cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week10',
            vaccine: 'Pentavalent',
            dose: 'Dose 2',
            doseNum: 2,
            doseType: 'dose2',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'DTP-HepB-Hib',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week10',
            groupTitle: '10-Week Routine Schedule (2½ Months)',
            groupSub: 'Infants at 10 weeks • 2nd primary immunisation cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week10',
            vaccine: 'PCV',
            dose: 'Dose 2',
            doseNum: 2,
            doseType: 'dose2',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Pneumococcal',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week10',
            groupTitle: '10-Week Routine Schedule (2½ Months)',
            groupSub: 'Infants at 10 weeks • 2nd primary immunisation cycle',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week10',
            vaccine: 'Rotavirus',
            dose: 'Dose 2',
            doseNum: 2,
            doseType: 'dose2',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Solution (1.5 ml)',
            targetDisease: 'Rotavirus Diarrhoea',
            icon: 'mdi-water'
        },

        // 4. 14 Weeks Cohort
        {
            groupKey: 'week14',
            groupTitle: '14-Week Routine Schedule (3½ Months)',
            groupSub: 'Infants at 14 weeks • Primary cycle completion + Inactivated Polio',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week14',
            vaccine: 'OPV',
            dose: 'Dose 3',
            doseNum: 3,
            doseType: 'dose3',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Drops (2 drops)',
            targetDisease: 'Poliomyelitis',
            icon: 'mdi-water'
        },
        {
            groupKey: 'week14',
            groupTitle: '14-Week Routine Schedule (3½ Months)',
            groupSub: 'Infants at 14 weeks • Primary cycle completion + Inactivated Polio',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week14',
            vaccine: 'Pentavalent',
            dose: 'Dose 3',
            doseNum: 3,
            doseType: 'dose3',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'DTP-HepB-Hib',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week14',
            groupTitle: '14-Week Routine Schedule (3½ Months)',
            groupSub: 'Infants at 14 weeks • Primary cycle completion + Inactivated Polio',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week14',
            vaccine: 'PCV',
            dose: 'Dose 3',
            doseNum: 3,
            doseType: 'dose3',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Pneumococcal',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'week14',
            groupTitle: '14-Week Routine Schedule (3½ Months)',
            groupSub: 'Infants at 14 weeks • Primary cycle completion + Inactivated Polio',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week14',
            vaccine: 'Rotavirus',
            dose: 'Dose 3',
            doseNum: 3,
            doseType: 'dose3',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Solution (1.5 ml)',
            targetDisease: 'Rotavirus Diarrhoea',
            icon: 'mdi-water'
        },
        {
            groupKey: 'week14',
            groupTitle: '14-Week Routine Schedule (3½ Months)',
            groupSub: 'Infants at 14 weeks • Primary cycle completion + Inactivated Polio',
            groupCategory: 'infants',
            groupIcon: 'mdi-calendar-week',
            groupBundle: 'week14',
            vaccine: 'IPV',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Inactivated Polio',
            icon: 'mdi-needle'
        },

        // 5. 9 Months Cohort
        {
            groupKey: 'month9',
            groupTitle: '9-Month Routine Schedule (36 Weeks)',
            groupSub: 'Infants at 9 months • Measles, Yellow Fever, Men-A & Vitamin A',
            groupCategory: 'infants',
            groupIcon: 'mdi-shield-star',
            groupBundle: 'month9',
            vaccine: 'Vitamin A',
            dose: 'Dose 1 (100,000 IU)',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Oral Capsule (Blue)',
            targetDisease: 'Vitamin A Deficiency',
            icon: 'mdi-pill'
        },
        {
            groupKey: 'month9',
            groupTitle: '9-Month Routine Schedule (36 Weeks)',
            groupSub: 'Infants at 9 months • Measles, Yellow Fever, Men-A & Vitamin A',
            groupCategory: 'infants',
            groupIcon: 'mdi-shield-star',
            groupBundle: 'month9',
            vaccine: 'Measles',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Subcutaneous (SC)',
            targetDisease: 'Measles Virus',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'month9',
            groupTitle: '9-Month Routine Schedule (36 Weeks)',
            groupSub: 'Infants at 9 months • Measles, Yellow Fever, Men-A & Vitamin A',
            groupCategory: 'infants',
            groupIcon: 'mdi-shield-star',
            groupBundle: 'month9',
            vaccine: 'Yellow Fever',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Subcutaneous (SC)',
            targetDisease: 'Yellow Fever',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'month9',
            groupTitle: '9-Month Routine Schedule (36 Weeks)',
            groupSub: 'Infants at 9 months • Measles, Yellow Fever, Men-A & Vitamin A',
            groupCategory: 'infants',
            groupIcon: 'mdi-shield-star',
            groupBundle: 'month9',
            vaccine: 'Men-A',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '<1y',
            targetGroup: 'infants',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Meningitis Serogroup A',
            icon: 'mdi-needle'
        },

        // 6. 12-59 Months (2YL & Under-5 Boosters)
        {
            groupKey: 'year2',
            groupTitle: '2YL & Under-5 Boosters (12–59 Months)',
            groupSub: 'Children 12–59 months • Second Year of Life Booster Protection',
            groupCategory: 'children',
            groupIcon: 'mdi-human-child',
            groupBundle: 'year2',
            vaccine: 'Measles',
            dose: 'Dose 2 (15 Months)',
            doseNum: 2,
            doseType: 'booster',
            defaultDemographic: '12-59m',
            targetGroup: 'children_1_4',
            sex: 'All',
            route: 'Subcutaneous (SC)',
            targetDisease: 'Measles 2nd Opportunity',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'year2',
            groupTitle: '2YL & Under-5 Boosters (12–59 Months)',
            groupSub: 'Children 12–59 months • Second Year of Life Booster Protection',
            groupCategory: 'children',
            groupIcon: 'mdi-human-child',
            groupBundle: 'year2',
            vaccine: 'Vitamin A',
            dose: 'Dose 2 (200,000 IU)',
            doseNum: 2,
            doseType: 'booster',
            defaultDemographic: '12-59m',
            targetGroup: 'children_1_4',
            sex: 'All',
            route: 'Oral Capsule (Red)',
            targetDisease: 'Vitamin A Supplementation',
            icon: 'mdi-pill'
        },
        {
            groupKey: 'year2',
            groupTitle: '2YL & Under-5 Boosters (12–59 Months)',
            groupSub: 'Children 12–59 months • Second Year of Life Booster Protection',
            groupCategory: 'children',
            groupIcon: 'mdi-human-child',
            groupBundle: 'year2',
            vaccine: 'Men-A',
            dose: 'Booster',
            doseNum: 2,
            doseType: 'booster',
            defaultDemographic: '12-59m',
            targetGroup: 'children_1_4',
            sex: 'All',
            route: 'Intramuscular (IM)',
            targetDisease: 'Meningitis Booster',
            icon: 'mdi-needle'
        },

        // 7. Adolescent Girls (HPV Elimination)
        {
            groupKey: 'hpv',
            groupTitle: 'Adolescent Girls (HPV Elimination)',
            groupSub: 'Girls aged 9–14 years • Cervical Cancer Prevention Initiative',
            groupCategory: 'hpv',
            groupIcon: 'mdi-gender-female text-pink',
            groupBundle: 'hpv',
            vaccine: 'HPV',
            dose: 'Dose 1',
            doseNum: 1,
            doseType: 'dose1',
            defaultDemographic: '9-14y',
            targetGroup: 'adolescent_girls',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Human Papillomavirus',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'hpv',
            groupTitle: 'Adolescent Girls (HPV Elimination)',
            groupSub: 'Girls aged 9–14 years • Cervical Cancer Prevention Initiative',
            groupCategory: 'hpv',
            groupIcon: 'mdi-gender-female text-pink',
            groupBundle: 'hpv',
            vaccine: 'HPV',
            dose: 'Dose 2',
            doseNum: 2,
            doseType: 'dose2',
            defaultDemographic: '9-14y',
            targetGroup: 'adolescent_girls',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Human Papillomavirus',
            icon: 'mdi-needle'
        },

        // 8. Maternal Td (Pregnant Women)
        {
            groupKey: 'maternal_td',
            groupTitle: 'Maternal Td (Pregnant Women)',
            groupSub: 'Pregnant Women • Maternal & Neonatal Tetanus Elimination (MNTE)',
            groupCategory: 'pregnant',
            groupIcon: 'mdi-mother-nurse text-primary',
            groupBundle: 'maternal_td',
            vaccine: 'Td',
            dose: 'Td 1',
            doseNum: 1,
            doseType: 'maternal',
            defaultDemographic: '15-49y_pw',
            targetGroup: 'pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Maternal Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'maternal_td',
            groupTitle: 'Maternal Td (Pregnant Women)',
            groupSub: 'Pregnant Women • Maternal & Neonatal Tetanus Elimination (MNTE)',
            groupCategory: 'pregnant',
            groupIcon: 'mdi-mother-nurse text-primary',
            groupBundle: 'maternal_td',
            vaccine: 'Td',
            dose: 'Td 2',
            doseNum: 2,
            doseType: 'maternal',
            defaultDemographic: '15-49y_pw',
            targetGroup: 'pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Maternal Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'maternal_td',
            groupTitle: 'Maternal Td (Pregnant Women)',
            groupSub: 'Pregnant Women • Maternal & Neonatal Tetanus Elimination (MNTE)',
            groupCategory: 'pregnant',
            groupIcon: 'mdi-mother-nurse text-primary',
            groupBundle: 'maternal_td',
            vaccine: 'Td',
            dose: 'Td 3',
            doseNum: 3,
            doseType: 'maternal',
            defaultDemographic: '15-49y_pw',
            targetGroup: 'pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Maternal Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'maternal_td',
            groupTitle: 'Maternal Td (Pregnant Women)',
            groupSub: 'Pregnant Women • Maternal & Neonatal Tetanus Elimination (MNTE)',
            groupCategory: 'pregnant',
            groupIcon: 'mdi-mother-nurse text-primary',
            groupBundle: 'maternal_td',
            vaccine: 'Td',
            dose: 'Td 4',
            doseNum: 4,
            doseType: 'maternal',
            defaultDemographic: '15-49y_pw',
            targetGroup: 'pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Maternal Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'maternal_td',
            groupTitle: 'Maternal Td (Pregnant Women)',
            groupSub: 'Pregnant Women • Maternal & Neonatal Tetanus Elimination (MNTE)',
            groupCategory: 'pregnant',
            groupIcon: 'mdi-mother-nurse text-primary',
            groupBundle: 'maternal_td',
            vaccine: 'Td',
            dose: 'Td 5',
            doseNum: 5,
            doseType: 'maternal',
            defaultDemographic: '15-49y_pw',
            targetGroup: 'pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Maternal Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },

        // 9. Non-Pregnant Women of Reproductive Age (WRA)
        {
            groupKey: 'wra_td',
            groupTitle: 'WRA Td (Non-Pregnant Women 15–49y)',
            groupSub: 'Women of Reproductive Age (15–49 years) • Routine Td Protection',
            groupCategory: 'wra',
            groupIcon: 'mdi-gender-female text-purple',
            groupBundle: 'wra_td',
            vaccine: 'Td',
            dose: 'Td 1 (WRA)',
            doseNum: 1,
            doseType: 'wra',
            defaultDemographic: '15-49y_npw',
            targetGroup: 'non_pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Tetanus & Diphtheria',
            icon: 'mdi-needle'
        },
        {
            groupKey: 'wra_td',
            groupTitle: 'WRA Td (Non-Pregnant Women 15–49y)',
            groupSub: 'Women of Reproductive Age (15–49 years) • Routine Td Protection',
            groupCategory: 'wra',
            groupIcon: 'mdi-gender-female text-purple',
            groupBundle: 'wra_td',
            vaccine: 'Td',
            dose: 'Td 2 (WRA)',
            doseNum: 2,
            doseType: 'wra',
            defaultDemographic: '15-49y_npw',
            targetGroup: 'non_pregnant_women',
            sex: 'Female',
            route: 'Intramuscular (IM)',
            targetDisease: 'Tetanus & Diphtheria',
            icon: 'mdi-needle'
        }
    ];

    var DEMOGRAPHIC_OPTIONS = [
        { value: '<1y', label: 'Infants < 1y (0–11m)', targetGroup: 'infants', ageGroup: '<1y', category: 'infants' },
        { value: '12-59m', label: 'Children ≥ 1y (12–59m)', targetGroup: 'children_1_4', ageGroup: '12-59m', category: 'children' },
        { value: '5-9y', label: 'Primary School Age 5–9y', targetGroup: 'school_age_5_9', ageGroup: '5-9y', category: 'children' },
        { value: '9-14y', label: 'Adolescent Girls 9–14y (HPV)', targetGroup: 'adolescent_girls', ageGroup: '9-14y', category: 'hpv' },
        { value: '10-19y', label: 'Adolescents & Youth 10–19y', targetGroup: 'adolescents', ageGroup: '10-19y', category: 'general' },
        { value: '15-49y_pw', label: 'Pregnant Women (Maternal Td)', targetGroup: 'pregnant_women', ageGroup: '15-49y', category: 'pregnant' },
        { value: '15-49y_npw', label: 'Non-Pregnant Women 15–49y (WRA)', targetGroup: 'non_pregnant_women', ageGroup: '15-49y', category: 'wra' },
        { value: '15-49y_male', label: 'Adult Males 15–49y', targetGroup: 'adult_males', ageGroup: '15-49y', category: 'general' },
        { value: '50+y', label: 'Older Adults 50+y', targetGroup: 'elderly', ageGroup: '50+y', category: 'general' },
        { value: 'all_ages', label: 'General Population / All Ages', targetGroup: 'general_population', ageGroup: 'all_ages', category: 'general' }
    ];

    function getOutreachEndpoint(action, param) {
        var isMaternity = (typeof window !== 'undefined' && window.location && window.location.pathname.indexOf('/maternity-workbench') !== -1) ||
                          (window.INVEST_RES_SOURCE === 'maternity');
        var prefix = isMaternity ? '/maternity-workbench' : '/nursing-workbench';
        var routes = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) ? window.WORKBENCH_CONFIG.routes : {};

        if (action === 'save-tally') {
            return routes[isMaternity ? 'maternity-workbench.outreach-tally' : 'nursing-workbench.outreach-tally'] || (prefix + '/save-outreach-tally');
        }
        if (action === 'reports') {
            return routes[isMaternity ? 'maternity-workbench.outreach-reports' : 'nursing-workbench.outreach-reports'] || (prefix + '/outreach-sessions-report');
        }
        if (action === 'details') {
            return prefix + '/outreach-session-details/' + encodeURIComponent(param || '');
        }
        if (action === 'print') {
            return prefix + '/print-outreach-report';
        }
        if (action === 'store-inventory') {
            return prefix + '/outreach-store-inventory';
        }
        return prefix + '/' + action;
    }

    function getBaseUrl() {
        var isMaternity = (typeof window !== 'undefined' && window.location && window.location.pathname.indexOf('/maternity-workbench') !== -1) ||
                          (window.INVEST_RES_SOURCE === 'maternity');
        return isMaternity ? '/maternity-workbench' : '/nursing-workbench';
    }

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || (window.WORKBENCH_CONFIG && (window.WORKBENCH_CONFIG.csrfToken || window.WORKBENCH_CONFIG.csrf)) || '';
    }

    /**
     * Initialize Module
     */
    OutreachImmunization.init = function() {
        OutreachImmunization.resetForm();
        OutreachImmunization.bindEvents();
    };

    /**
     * Reset Modal State
     */
    OutreachImmunization.resetForm = function() {
        _currentStep = 1;
        _rowStockOverrides = {};
        _activePickerRowIndex = null;
        OutreachImmunization.goToStep(1);

        var today = new Date().toISOString().split('T')[0];
        $('#outreach-session-date').val(today);
        $('#outreach-session-location').val('');
        $('#outreach-carrier-id').val('Cold Box #1');
        $('#outreach-wasted-doses').val(0);
        $('#outreach-session-notes').val('');

        // Reset Stock Source
        $('input[name="outreach_stock_source"][value="govt_epi"]').prop('checked', true).trigger('change');
        $('.stock-source-choice').removeClass('selected');
        $('#choice-source-govt').addClass('selected');
        $('#hospital-store-details-panel').addClass('d-none');
        $('#partner-details-panel').addClass('d-none');
        $('#outreach-auto-deduct-stock').prop('checked', true);

        // Reset VVM
        $('.vvm-pill').removeClass('active');
        $('.vvm-pill[data-vvm="Stage 1"]').addClass('active');
        $('#outreach-vvm-stage').val('Stage 1');

        // Render Table Rows
        OutreachImmunization.renderInitialRows();

        // Preload active store inventory in background
        var curStoreId = $('#outreach-session-store').val();
        if (curStoreId) {
            OutreachImmunization.loadStoreInventory(curStoreId);
        }
    };

    /**
     * Fetch store inventory & vaccine batches from backend
     */
    OutreachImmunization.loadStoreInventory = function(storeId) {
        if (!storeId) return;

        $('#store-stock-summary-text').html('<i class="mdi mdi-loading mdi-spin"></i> Fetching vaccine inventory &amp; batches from store...');
        $('#outreach-store-inventory-preview').removeClass('d-none');

        $.ajax({
            url: getOutreachEndpoint('store-inventory'),
            method: 'GET',
            data: { store_id: storeId },
            success: function(res) {
                if (res.success) {
                    _storeInventory.rawProducts = res.products || [];
                    _storeInventory.antigen_mappings = res.antigen_mappings || {};
                    _storeInventory.products = {};
                    (res.products || []).forEach(function(p) {
                        _storeInventory.products[p.id] = p;
                    });

                    $('#store-stock-summary-text').html(
                        '<i class="mdi mdi-check-circle text-success"></i> <strong>' + (res.total_vaccines || 0) + '</strong> active vaccine lines &bull; <strong>' + (res.total_stock_doses || 0) + '</strong> doses available in <strong>' + (res.store ? res.store.name : 'store') + '</strong>'
                    );
                    $('#store-stock-status-badge').text('Connected (' + (res.total_stock_doses || 0) + ' doses)');

                    OutreachImmunization.refreshAllStockConnectors();
                } else {
                    $('#store-stock-summary-text').html('<i class="mdi mdi-alert text-warning"></i> Could not load store stock: ' + (res.message || 'Error'));
                }
            },
            error: function() {
                $('#store-stock-summary-text').html('<i class="mdi mdi-alert text-danger"></i> Failed to connect to store inventory');
            }
        });
    };

    /**
     * Find mapped hospital inventory product for an antigen name
     */
    OutreachImmunization.findProductForAntigen = function(vaccineName, rowIdx) {
        if (_rowStockOverrides[rowIdx] && _rowStockOverrides[rowIdx].product_id !== undefined) {
            var overId = _rowStockOverrides[rowIdx].product_id;
            return overId ? (_storeInventory.products[overId] || null) : null;
        }

        var vKey = (vaccineName || '').toLowerCase().trim();
        if (!vKey) return null;

        // 1. Check explicit mapping
        var mappedId = _storeInventory.antigen_mappings[vKey];
        if (mappedId && _storeInventory.products[mappedId]) {
            return _storeInventory.products[mappedId];
        }

        // 2. Specific clinical antigen match rules
        var raw = _storeInventory.rawProducts || [];
        for (var i = 0; i < raw.length; i++) {
            var pName = (raw[i].name || '').toLowerCase();
            var pCode = (raw[i].code || '').toLowerCase();

            // Word-boundary helper to avoid substring false positives (e.g. "td" in "chondroitin")
            var hasWord = function(word) {
                var re = new RegExp('\\b' + word + '\\b', 'i');
                return re.test(pName) || re.test(pCode);
            };

            if (vKey === 'td' || vKey.startsWith('td ') || vKey === 'maternal td' || vKey === 'wra td') {
                if (hasWord('td') || hasWord('tetanus') || hasWord('toxoid') || hasWord('dt') || hasWord('d-t') || pName.includes('tetanus toxoid')) {
                    return raw[i];
                }
                continue;
            }
            if (vKey === 'opv' || vKey.startsWith('opv')) {
                if (hasWord('opv') || (hasWord('polio') && hasWord('oral'))) return raw[i];
                continue;
            }
            if (vKey === 'ipv' || vKey.startsWith('ipv')) {
                if (hasWord('ipv') || (hasWord('polio') && !hasWord('oral'))) return raw[i];
                continue;
            }
            if (vKey === 'bcg') {
                if (hasWord('bcg') || pName.includes('bcg vaccine')) return raw[i];
                continue;
            }
            if (vKey === 'hepb' || vKey.includes('hepatitis')) {
                if (hasWord('hepb') || hasWord('hep-b') || hasWord('hepatitis') || pName.includes('hepatitis b')) return raw[i];
                continue;
            }
            if (vKey === 'pentavalent' || vKey.includes('penta')) {
                if (hasWord('penta') || hasWord('pentavalent') || hasWord('dpt')) return raw[i];
                continue;
            }
            if (vKey === 'pcv') {
                if (hasWord('pcv') || hasWord('pneumo') || pName.includes('pneumococcal')) return raw[i];
                continue;
            }
            if (vKey === 'rotavirus' || vKey.includes('rota')) {
                if (hasWord('rota') || hasWord('rotavirus')) return raw[i];
                continue;
            }
            if (vKey === 'measles') {
                if (hasWord('measles')) return raw[i];
                continue;
            }
            if (vKey === 'yellow fever') {
                if (pName.includes('yellow fever') || hasWord('yf')) return raw[i];
                continue;
            }
            if (vKey === 'men-a' || vKey.includes('mening')) {
                if (hasWord('men-a') || hasWord('meningitis') || hasWord('meningococcal')) return raw[i];
                continue;
            }
            if (vKey === 'hpv') {
                if (hasWord('hpv') || hasWord('gardasil') || hasWord('cervarix') || pName.includes('papilloma')) return raw[i];
                continue;
            }
            if (vKey === 'vitamin a') {
                if (pName.includes('vitamin a') || hasWord('retinol')) return raw[i];
                continue;
            }

            // Only fallback to exact full-word match for other vaccines if length > 3
            if (vKey.length > 3 && (pName.includes(vKey) || pCode.includes(vKey))) {
                return raw[i];
            }
        }

        return null;
    };

    /**
     * Render stock connector strip for a single row
     */
    OutreachImmunization.renderRowStockConnector = function($row) {
        var $connector = $row.find('.row-stock-connector');
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();

        if (stockSourceVal !== 'hospital_store') {
            $connector.addClass('d-none');
            return;
        }

        $connector.removeClass('d-none');
        var rowIdx = $row.data('row-idx');
        var vacName = $row.find('.row-vaccine-name').val();
        var product = OutreachImmunization.findProductForAntigen(vacName, rowIdx);

        var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
        var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
        var reqQty = headcount + wasted;

        var isDeduct = (_rowStockOverrides[rowIdx] && _rowStockOverrides[rowIdx].auto_deduct !== undefined)
            ? _rowStockOverrides[rowIdx].auto_deduct
            : true;

        if (isDeduct) {
            $connector.removeClass('deduction-skipped');
        } else {
            $connector.addClass('deduction-skipped');
        }

        if (product) {
            $row.find('.row-product-id').val(product.id);
            var curStock = parseInt(product.current_stock, 10) || 0;
            var remStock = Math.max(0, curStock - reqQty);
            var hasDeficit = isDeduct && (reqQty > curStock);
            var deficitQty = hasDeficit ? (reqQty - curStock) : 0;

            // Batches dropdown
            var currentBatchId = $row.find('.row-batch-id').val();
            var batchOptions = '<option value="" data-batch-num="' + (product.fifo_batch ? product.fifo_batch.batch_number : '') + '" data-expiry="' + (product.fifo_batch ? (product.fifo_batch.expiry_date || '') : '') + '" data-fifo="1">★ Auto FIFO (Earliest Expiry)</option>';
            if (product.batches && product.batches.length > 0) {
                product.batches.forEach(function(b) {
                    var isBSelected = (currentBatchId && parseInt(currentBatchId, 10) === parseInt(b.id, 10)) ? 'selected' : '';
                    batchOptions += '<option value="' + b.id + '" data-batch-num="' + (b.batch_number || '') + '" data-expiry="' + (b.expiry_date || '') + '" data-qty="' + b.current_qty + '" ' + isBSelected + '>' +
                        b.batch_number + ' (' + b.current_qty + ' avail) | Exp: ' + (b.expiry_formatted || 'N/A') + '</option>';
                });
            }

            var stockBadgeCls = curStock > 0 ? 'badge-in-stock' : 'badge-out-of-stock';
            var stockIcon = curStock > 0 ? 'mdi-check-circle' : 'mdi-alert-circle';

            var html = `
                <div class="stock-line-box">
                    <div class="stock-line-top">
                        <span class="stock-product-chip" title="${product.name}">
                            <i class="mdi mdi-package-variant-closed text-primary"></i>
                            <strong>[${product.code}]</strong> ${product.name}
                        </span>
                        <span class="stock-avail-pill ${stockBadgeCls}">
                            <i class="mdi ${stockIcon}"></i> ${curStock} in store
                        </span>
                    </div>
                    <div class="stock-line-controls">
                        <div class="stock-control-item">
                            <span class="stock-control-lbl">Batch:</span>
                            <select class="stock-batch-dropdown form-select-xs" data-row-idx="${rowIdx}">
                                ${batchOptions}
                            </select>
                        </div>
                        <div class="stock-control-item">
                            <span class="stock-math-pill" title="Headcount + Wasted | Remaining in store">
                                <i class="mdi mdi-arrow-down-bold text-warning"></i> Deduct: <strong class="lbl-deduct">${reqQty}</strong> | Rem: <strong class="lbl-rem">${remStock}</strong>
                            </span>
                        </div>
                        <div class="stock-control-item">
                            <label class="stock-deduct-toggle ${isDeduct ? 'is-checked' : ''}" title="Toggle hospital store deduction for this antigen">
                                <input type="checkbox" class="stock-deduct-checkbox row-deduct-check" data-row-idx="${rowIdx}" ${isDeduct ? 'checked' : ''}>
                                <span class="stock-deduct-label">${isDeduct ? 'Deduct' : 'Skip'}</span>
                            </label>
                        </div>
                        <div class="stock-control-item stock-action-btns">
                            <button type="button" class="btn btn-xs btn-outline-primary btn-open-picker" data-row-idx="${rowIdx}" title="Change mapped hospital product">
                                <i class="mdi mdi-swap-horizontal"></i> Change
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-unlink-row" data-row-idx="${rowIdx}" title="Unlink this product">
                                <i class="mdi mdi-close"></i>
                            </button>
                        </div>
                    </div>
                    <div class="stock-deficit-alert ${hasDeficit ? 'is-visible' : 'd-none'}" style="${hasDeficit ? 'display: flex !important;' : 'display: none !important;'}">
                        <span class="deficit-text small fw-bold">
                            <i class="mdi mdi-alert-circle text-danger"></i> Deficit: <strong class="deficit-short">${deficitQty}</strong> short (Store has <span class="avail-stock-num">${curStock}</span>)
                        </span>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-xs btn-outline-danger btn-skip-deficit py-0 px-2" data-row-idx="${rowIdx}" title="Keep tally count for report, but skip hospital store deduction">
                                <i class="mdi mdi-cancel"></i> Skip Deduct
                            </button>
                            <button type="button" class="btn btn-xs btn-danger btn-cap-deficit py-0 px-2" data-row-idx="${rowIdx}" data-stock="${curStock}" title="Cap tally count so total doses equal store stock">
                                <i class="mdi mdi-arrow-collapse-down"></i> Cap to Stock (${curStock})
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $connector.html(html);

            if (hasDeficit) {
                $connector.addClass('has-deficit');
            } else {
                $connector.removeClass('has-deficit');
            }

            if (!currentBatchId && product.fifo_batch) {
                $row.find('.row-batch-number').val(product.fifo_batch.batch_number || '');
                $row.find('.row-expiry-date').val(product.fifo_batch.expiry_date || '');
            }
        } else {
            $row.find('.row-product-id').val('');
            $row.find('.row-batch-id').val('');
            $row.find('.row-batch-number').val('');
            $row.find('.row-expiry-date').val('');

            var emptyHtml = `
                <div class="stock-line-box unlinked">
                    <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-1">
                        <span class="text-muted small">
                            <i class="mdi mdi-package-variant-closed text-secondary"></i> <em>No hospital product linked</em>
                        </span>
                        <div class="d-flex gap-1 align-items-center">
                            <button type="button" class="btn btn-xs btn-outline-primary btn-open-picker" data-row-idx="${rowIdx}">
                                <i class="mdi mdi-link-variant"></i> Link Product
                            </button>
                            <label class="stock-deduct-toggle ${isDeduct ? 'is-checked' : ''}" title="Toggle hospital store deduction">
                                <input type="checkbox" class="stock-deduct-checkbox row-deduct-check" data-row-idx="${rowIdx}" ${isDeduct ? 'checked' : ''}>
                                <span class="stock-deduct-label">${isDeduct ? 'Deduct' : 'Skip'}</span>
                            </label>
                        </div>
                    </div>
                </div>
            `;
            $connector.html(emptyHtml);
            $connector.removeClass('has-deficit');
        }
    };

    /**
     * Fast update of stock math for a single row without full DOM replacement
     */
    OutreachImmunization.updateRowStockMath = function($row) {
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        if (stockSourceVal !== 'hospital_store') return;

        var rowIdx = $row.data('row-idx');
        var vacName = $row.find('.row-vaccine-name').val();
        var product = OutreachImmunization.findProductForAntigen(vacName, rowIdx);
        if (!product) return;

        var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
        var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
        var reqQty = headcount + wasted;

        var isDeduct = (_rowStockOverrides[rowIdx] && _rowStockOverrides[rowIdx].auto_deduct !== undefined)
            ? _rowStockOverrides[rowIdx].auto_deduct
            : ($row.find('.row-deduct-check').length ? $row.find('.row-deduct-check').is(':checked') : true);

        var curStock = parseInt(product.current_stock, 10) || 0;
        var remStock = Math.max(0, curStock - reqQty);
        var hasDeficit = isDeduct && (reqQty > curStock);
        var deficitQty = hasDeficit ? (reqQty - curStock) : 0;

        $row.find('.lbl-deduct').text(reqQty);
        $row.find('.lbl-rem').text(remStock);

        var $alert = $row.find('.stock-deficit-alert');
        var $conn = $row.find('.row-stock-connector');

        if (hasDeficit) {
            $alert.removeClass('d-none').addClass('is-visible').css('display', 'flex');
            $alert.find('.deficit-short').text(deficitQty);
            $alert.find('.avail-stock-num').text(curStock);
            $alert.find('.btn-cap-deficit').data('stock', curStock).attr('title', 'Cap tally count so total doses equal store stock (' + curStock + ')').html('<i class="mdi mdi-arrow-collapse-down"></i> Cap to Stock (' + curStock + ')');
            $conn.addClass('has-deficit');
        } else {
            $alert.addClass('d-none').removeClass('is-visible').css('display', 'none');
            $alert.find('.deficit-short').text('0');
            $conn.removeClass('has-deficit');
        }
    };

    /**
     * Open Product Picker / Linker modal for an antigen row
     */
    OutreachImmunization.openProductPicker = function(rowIdx) {
        _activePickerRowIndex = rowIdx;
        var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + rowIdx + '"]');
        var vacName = $row.find('.row-vaccine-name').val() || 'Antigen';
        var curProdId = $row.find('.row-product-id').val();

        $('#picker-antigen-label').text(vacName);
        var storeName = $('#outreach-session-store option:selected').text().trim() || 'Store';
        $('#picker-store-name').text(storeName);
        $('#picker-product-search').val('');
        $('#picker-search-spinner').addClass('d-none');
        $('.picker-filter-chip').removeClass('active');
        $('.picker-filter-chip[data-filter="all"]').addClass('active');
        _activePickerFilter = 'all';

        OutreachImmunization.renderPickerTable(curProdId, '', 'all');
        $('#outreachProductPickerModal').modal('show');
    };

    /**
     * Render product rows inside the picker modal
     */
    OutreachImmunization.renderPickerTable = function(currentProdId, filterTerm, filterType) {
        var $tbody = $('#picker-products-tbody');
        $tbody.empty();

        var raw = _storeInventory.rawProducts || [];
        var term = (filterTerm || '').toLowerCase().trim();
        var fType = filterType || _activePickerFilter || 'all';
        var count = 0;

        raw.forEach(function(p) {
            var pName = (p.name || '').toLowerCase();
            var pCode = (p.code || '').toLowerCase();
            var pCat = (p.category || '').toLowerCase();
            var curStock = p.current_stock || 0;

            if (term && !pName.includes(term) && !pCode.includes(term) && !pCat.includes(term)) {
                return;
            }

            if (fType === 'in_stock' && curStock <= 0) {
                return;
            }
            if (fType === 'vaccines') {
                var isVaccine = pCat.includes('vaccin') || pCat.includes('biolog') ||
                    pName.includes('vaccin') || pName.includes('bcg') || pName.includes('opv') ||
                    pName.includes('penta') || pName.includes('pcv') || pName.includes('rota') ||
                    pName.includes('measles') || pName.includes('yellow') || pName.includes('hpv') ||
                    pName.includes('td') || pName.includes('tetanus');
                if (!isVaccine) return;
            }

            count++;
            var isSelected = (currentProdId && parseInt(currentProdId, 10) === parseInt(p.id, 10));
            var stockBadge = curStock > 0
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="mdi mdi-check-circle"></i> ' + curStock + ' in store</span>'
                : '<span class="badge bg-light text-muted border">0 in store</span>';

            var batchesDesc = '<span class="text-muted">No store batches</span>';
            if (p.batches && p.batches.length > 0) {
                var bList = p.batches.slice(0, 2).map(function(b) {
                    return '<code>' + b.batch_number + '</code> (' + b.current_qty + ' doses, exp ' + (b.expiry_formatted || 'N/A') + ')';
                }).join('<br>');
                if (p.batches.length > 2) {
                    bList += '<br><small class="text-primary">+' + (p.batches.length - 2) + ' more batch(es)</small>';
                }
                batchesDesc = bList;
            }

            var btnHtml = isSelected
                ? '<span class="badge bg-primary text-white py-1 px-2"><i class="mdi mdi-check"></i> Linked</span>'
                : '<button type="button" class="btn btn-xs btn-outline-primary btn-select-picker-product" data-product-id="' + p.id + '"><i class="mdi mdi-link"></i> Select</button>';

            $tbody.append(`
                <tr class="${isSelected ? 'table-primary' : ''}">
                    <td><span class="badge bg-light text-dark font-monospace">${p.code || '-'}</span></td>
                    <td>
                        <strong class="text-dark">${p.name || '-'}</strong>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-secondary">${p.category || 'General'}</span></td>
                    <td style="text-align: right; vertical-align: middle;">${stockBadge}</td>
                    <td style="font-size: 0.72rem; vertical-align: middle;">${batchesDesc}</td>
                    <td style="text-align: center; vertical-align: middle;">${btnHtml}</td>
                </tr>
            `);
        });

        $('#picker-products-count').text(count + ' product(s)');

        if (count === 0) {
            var msg = term
                ? 'No hospital products match "' + term + '". You can search any drug or item in the hospital catalogue.'
                : 'No products loaded for this store.';
            $tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted"><i class="mdi mdi-package-variant-closed fs-3 d-block text-secondary mb-1"></i> ' + msg + '</td></tr>');
        }
    };

    /**
     * Refresh all rows' stock connectors
     */
    OutreachImmunization.refreshAllStockConnectors = function() {
        $('#outreach-tally-rows tr.outreach-row').each(function() {
            OutreachImmunization.renderRowStockConnector($(this));
        });
        OutreachImmunization.updateStockSidebar();
    };

    /**
     * Find all items with stock deficit under active hospital store auto-deduction
     */
    OutreachImmunization.findStockDeficits = function() {
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        var autoDeductGlobal = $('#outreach-auto-deduct-stock').is(':checked');
        if (stockSourceVal !== 'hospital_store' || !autoDeductGlobal) {
            return [];
        }

        var deficits = [];
        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var rowIdx = $row.data('row-idx');
            var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
            var reqQty = headcount + wasted;

            if (reqQty <= 0) return;

            var isDeduct = (_rowStockOverrides[rowIdx] && _rowStockOverrides[rowIdx].auto_deduct !== undefined)
                ? _rowStockOverrides[rowIdx].auto_deduct
                : ($row.find('.row-deduct-check').length ? $row.find('.row-deduct-check').is(':checked') : true);

            if (!isDeduct) return;

            var vac = $row.find('.row-vaccine-name').val() || 'Antigen';
            var dose = $row.find('.row-dose').val() || '';
            var product = OutreachImmunization.findProductForAntigen(vac, rowIdx);

            var curStock = product ? (product.current_stock || 0) : 0;
            if (reqQty > curStock) {
                deficits.push({
                    rowIdx: rowIdx,
                    vaccine: vac,
                    dose: dose,
                    headcount: headcount,
                    wasted: wasted,
                    required: reqQty,
                    available: curStock,
                    deficit: reqQty - curStock,
                    productName: product ? product.name : 'Unmapped Product',
                    productCode: product ? product.code : 'N/A',
                    $row: $row
                });
            }
        });

        return deficits;
    };

    /**
     * Show 1-Click Interactive Deficit Resolution Dialog
     */
    OutreachImmunization.showDeficitResolutionModal = function(deficits, onResolvedCallback) {
        if (!deficits || deficits.length === 0) {
            if (typeof onResolvedCallback === 'function') onResolvedCallback();
            return;
        }

        var itemsHtml = '<div class="table-responsive my-2" style="max-height: 220px; overflow-y: auto;"><table class="table table-sm table-bordered text-start small mb-0"><thead class="table-light"><tr><th>Antigen</th><th>Product &amp; Code</th><th class="text-center">Required</th><th class="text-center">Store Stock</th><th class="text-center text-danger">Deficit</th></tr></thead><tbody>';
        deficits.forEach(function(d) {
            itemsHtml += '<tr>' +
                '<td><strong>' + d.vaccine + '</strong> (' + d.dose + ')</td>' +
                '<td>[' + d.productCode + '] ' + d.productName + '</td>' +
                '<td class="text-center fw-bold">' + d.required + '</td>' +
                '<td class="text-center">' + d.available + '</td>' +
                '<td class="text-center fw-bold text-danger">-' + d.deficit + '</td>' +
                '</tr>';
        });
        itemsHtml += '</tbody></table></div>';

        var $modal = $('#outreachTallyModal');
        var hadTabindex = $modal.attr('tabindex') !== undefined;
        $modal.removeAttr('tabindex');
        $(document).off('focusin.bs.modal');

        Swal.fire({
            title: '<div class="text-danger fw-bold"><i class="mdi mdi-alert-circle"></i> Hospital Store Stock Deficit</div>',
            html: '<p class="text-muted small mb-2">The outreach quantities tallied exceed currently available hospital store inventory for <strong>' + deficits.length + ' item(s)</strong>. To proceed, choose how to reconcile with ERP inventory:</p>' +
                  itemsHtml +
                  '<div class="alert alert-warning py-2 px-3 small text-start mt-2 mb-0">' +
                  '<strong>Choose a resolution:</strong><br>' +
                  '&bull; <strong>Skip Deductions</strong>: Retain clinical tally headcount for DHIS2/NHMIS reporting, but do NOT deduct from hospital inventory.<br>' +
                  '&bull; <strong>Auto-Cap to Stock</strong>: Adjust headcount down so total doses match available store stock.<br>' +
                  '&bull; <strong>Review in Table</strong>: Return to Step 2 to manually adjust.' +
                  '</div>',
            icon: 'warning',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="mdi mdi-cancel"></i> Skip Store Deductions',
            confirmButtonColor: '#0d6efd',
            denyButtonText: '<i class="mdi mdi-arrow-collapse-down"></i> Auto-Cap to Stock',
            denyButtonColor: '#f59e0b',
            cancelButtonText: '<i class="mdi mdi-table-edit"></i> Review in Table',
            cancelButtonColor: '#6c757d',
            allowOutsideClick: false,
            returnFocus: false,
            width: '680px',
            didOpen: function() {
                $(document).off('focusin.bs.modal');
            },
            didClose: function() {
                if (hadTabindex) {
                    $modal.attr('tabindex', '-1');
                }
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                deficits.forEach(function(d) {
                    _rowStockOverrides[d.rowIdx] = _rowStockOverrides[d.rowIdx] || {};
                    _rowStockOverrides[d.rowIdx].auto_deduct = false;
                    d.$row.find('.row-deduct-check').prop('checked', false);
                    d.$row.find('.stock-deduct-label').text('Skip');
                    d.$row.find('.stock-deduct-toggle').removeClass('is-checked');
                    OutreachImmunization.updateRowStockMath(d.$row);
                });
                OutreachImmunization.updateStockSidebar();
                toastr.info('Store deduction skipped for ' + deficits.length + ' deficit item(s). Public health tallies retained.');
                if (typeof onResolvedCallback === 'function') onResolvedCallback();
            } else if (result.isDenied) {
                deficits.forEach(function(d) {
                    var capHeadcount = Math.max(0, d.available - d.wasted);
                    d.$row.find('.row-headcount').val(capHeadcount);
                    OutreachImmunization.updateRowStockMath(d.$row);
                });
                OutreachImmunization.updateTotals();
                OutreachImmunization.updateStockSidebar();
                toastr.success('Headcounts auto-capped to available stock for ' + deficits.length + ' item(s).');
                if (typeof onResolvedCallback === 'function') onResolvedCallback();
            } else {
                if (deficits[0] && deficits[0].$row.length) {
                    var $tableWrap = $('.outreach-tally-table-wrapper');
                    var rowTop = deficits[0].$row.position().top + $tableWrap.scrollTop();
                    $tableWrap.animate({ scrollTop: Math.max(0, rowTop - 60) }, 300);
                    deficits.forEach(function(d) {
                        d.$row.addClass('row-deficit-pulse');
                        setTimeout(function() { d.$row.removeClass('row-deficit-pulse'); }, 2000);
                    });
                }
            }
        });
    };

    /**
     * Update Stock Sidebar Summary Counters & Live ERP Stock Status
     */
    OutreachImmunization.updateStockSidebar = function() {
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        if (stockSourceVal !== 'hospital_store') {
            $('#sidebar-hospital-stock-box').addClass('d-none');
            $('#sidebar-stock-status-pill').removeClass('bg-danger').addClass('bg-success').text('Buffer Active');
            $('#sidebar-stock-warning').addClass('d-none');
            return;
        }

        $('#sidebar-hospital-stock-box').removeClass('d-none');
        var storeName = $('#outreach-session-store option:selected').text().trim() || 'Store';
        $('#sidebar-stock-store-name').text(storeName);

        var totalDeduct = 0;
        var activeRows = 0;
        var linkedRows = 0;

        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
            var reqQty = headcount + wasted;

            if (reqQty > 0) {
                activeRows++;
                var isDeduct = (_rowStockOverrides[$row.data('row-idx')] && _rowStockOverrides[$row.data('row-idx')].auto_deduct !== undefined)
                    ? _rowStockOverrides[$row.data('row-idx')].auto_deduct
                    : ($row.find('.row-deduct-check').length ? $row.find('.row-deduct-check').is(':checked') : true);

                if (isDeduct) {
                    totalDeduct += reqQty;
                }
                var pId = $row.find('.row-product-id').val();
                if (pId) {
                    linkedRows++;
                }
            }
        });

        $('#sidebar-stock-total-deduct').text(totalDeduct);
        $('#sidebar-stock-linked-count').text(linkedRows + ' / ' + activeRows);

        var deficits = OutreachImmunization.findStockDeficits();
        if (deficits.length > 0) {
            $('#sidebar-stock-status-pill').removeClass('bg-success').addClass('bg-danger').text('Stock Deficit (' + deficits.length + ')');
            $('#sidebar-stock-warning').removeClass('d-none').html(
                '<i class="mdi mdi-alert-circle"></i> <strong>' + deficits.length + ' Stock Deficit(s)</strong> detected.<br>' +
                '<button type="button" class="btn btn-xs btn-danger mt-1 w-100" id="btn-sidebar-resolve-deficits">' +
                '<i class="mdi mdi-wrench"></i> 1-Click Resolve Deficits</button>'
            );
        } else {
            $('#sidebar-stock-status-pill').removeClass('bg-danger').addClass('bg-success').text('In Stock');
            $('#sidebar-stock-warning').addClass('d-none');
        }
    };

    /**
     * Helper to compute dose number from dose string
     */
    function updateDoseNum($row, doseText) {
        var dNum = 1;
        var lower = (doseText || '').toLowerCase();
        if (lower.indexOf('2') !== -1) dNum = 2;
        else if (lower.indexOf('3') !== -1) dNum = 3;
        else if (lower.indexOf('4') !== -1) dNum = 4;
        else if (lower.indexOf('5') !== -1) dNum = 5;
        else if (lower.indexOf('0') !== -1) dNum = 0;
        else if (lower.indexOf('booster') !== -1) dNum = 2;
        $row.find('.row-dose-num').val(dNum);
    }

    /**
     * Render the catalog rows in the matrix table grouped by clinical schedule milestones
     */
    OutreachImmunization.renderInitialRows = function() {
        var $tbody = $('#outreach-tally-rows');
        $tbody.empty();

        var currentGroup = null;
        var sn = 0;
        DEFAULT_ANTIGENS.forEach(function(item, idx) {
            if (item.groupKey && item.groupKey !== currentGroup) {
                currentGroup = item.groupKey;
                $tbody.append(OutreachImmunization.createScheduleHeaderHtml(item));
            }
            sn++;
            var rowHtml = OutreachImmunization.createRowHtml(idx, item, false, sn);
            $tbody.append(rowHtml);
        });

        OutreachImmunization.refreshAllStockConnectors();
        OutreachImmunization.updateTotals();
    };

    /**
     * Create HTML for schedule milestone section header banner (Spans all 7 columns)
     */
    OutreachImmunization.createScheduleHeaderHtml = function(item) {
        var groupKey = item.groupKey;
        var title = item.groupTitle || 'Schedule Milestone';
        var subtitle = item.groupSub || '';
        var icon = item.groupIcon || 'mdi-calendar-check';
        var bundle = item.groupBundle || '';
        var cat = item.groupCategory || 'infants';

        return `
            <tr class="schedule-group-row" data-group="${groupKey}" data-category="${cat}">
                <td colspan="7" class="schedule-group-cell">
                    <div class="schedule-group-banner">
                        <div class="schedule-group-left">
                            <span class="schedule-group-avatar">
                                <i class="mdi ${icon}"></i>
                            </span>
                            <div class="schedule-group-headings">
                                <span class="schedule-group-title">${title}</span>
                                <span class="schedule-group-sub">${subtitle}</span>
                            </div>
                        </div>
                        <div class="schedule-group-right">
                            <span class="badge schedule-group-tally-badge" id="group-tally-${groupKey}">0 tallied</span>
                            ${bundle ? `
                                <button type="button" class="btn btn-xs btn-outline-primary outreach-bundle-chip py-1 px-2" data-bundle="${bundle}" title="Add +1 to all vaccines in ${title}">
                                    <span class="bundle-plus-tag">+1</span> <i class="mdi mdi-lightning-bolt"></i> +1 All in Visit
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </td>
            </tr>
        `;
    };

    /**
     * Create HTML for a single tally row (Clear 7-column layout with S/N, Dose, Demographic & Sex selection; zero horizontal scroll)
     */
    OutreachImmunization.createRowHtml = function(idx, data, isCustom, sn) {
        var selectedDemo = data.defaultDemographic || '<1y';
        var demoCategory = data.targetGroup || 'infants';

        var demoObj = DEMOGRAPHIC_OPTIONS.find(function(d) { return d.value === selectedDemo; });
        if (demoObj) {
            demoCategory = demoObj.category;
        }

        var demoOptionsHtml = '';
        DEMOGRAPHIC_OPTIONS.forEach(function(opt) {
            var isSel = (opt.value === selectedDemo) ? 'selected' : '';
            demoOptionsHtml += '<option value="' + opt.value + '" data-target-group="' + opt.targetGroup + '" data-age-group="' + opt.ageGroup + '" data-category="' + opt.category + '" ' + isSel + '>' + opt.label + '</option>';
        });

        var sex = data.sex || 'All';
        var groupKey = data.groupKey || 'custom';
        var vac = data.vaccine || '';
        var dose = data.dose || 'Dose 1';
        var doseNum = (data.doseNum !== undefined) ? data.doseNum : 1;
        var route = data.route || 'Clinical Route';
        var targetDisease = data.targetDisease || '';
        var vacIcon = data.icon || 'mdi-needle';
        var rowSn = sn || (idx + 1);

        if (isCustom) {
            // User-added custom row (compact inputs)
            return `
                <tr class="outreach-row outreach-custom-row" data-row-idx="${idx}" data-category="${demoCategory}" data-group="custom">
                    <td class="vaccine-sn-cell text-center">
                        <span class="row-sn-badge">${rowSn}</span>
                    </td>
                    <td class="vaccine-info-cell">
                        <div class="d-flex align-items-center gap-1">
                            <input type="text" class="form-control form-control-xs row-vaccine-name fw-bold" value="${vac}" placeholder="Enter antigen name">
                            <button type="button" class="btn btn-outline-danger btn-xs py-0 px-1 btn-delete-custom-row" title="Delete custom row">
                                <i class="mdi mdi-delete-outline"></i>
                            </button>
                        </div>
                        <div class="row-stock-connector d-none" data-row-idx="${idx}"></div>
                        <input type="hidden" class="row-product-id" value="">
                        <input type="hidden" class="row-batch-id" value="">
                        <input type="hidden" class="row-batch-number" value="">
                        <input type="hidden" class="row-expiry-date" value="">
                        <input type="hidden" class="row-auto-deduct" value="1">
                    </td>
                    <td class="vaccine-dose-cell text-center">
                        <input type="text" class="form-control form-control-xs text-center row-dose" value="${dose}" placeholder="e.g. Dose 1">
                        <input type="hidden" class="row-dose-num" value="${doseNum}">
                    </td>
                    <td class="vaccine-cohort-cell">
                        <select class="form-select form-select-xs row-demographic" data-row-idx="${idx}">
                            ${demoOptionsHtml}
                        </select>
                    </td>
                    <td class="vaccine-sex-cell text-center">
                        <select class="form-select form-select-xs row-sex text-center" data-row-idx="${idx}">
                            <option value="All" ${sex === 'All' ? 'selected' : ''}>All</option>
                            <option value="Female" ${sex === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Male" ${sex === 'Male' ? 'selected' : ''}>Male</option>
                        </select>
                    </td>
                    <td class="vaccine-tally-cell text-center">
                        <div class="tally-controls-cell">
                            <div class="tally-stepper-box">
                                <button type="button" class="tally-stepper-btn btn-step-minus opacity-50" title="-1" disabled>-</button>
                                <input type="number" min="0" value="0" class="tally-input row-headcount">
                                <button type="button" class="tally-stepper-btn btn-step-plus" title="+1">+</button>
                            </div>
                            <div class="tally-quick-actions">
                                <button type="button" class="tally-quick-btn btn-step-five" title="+5">+5</button>
                                <button type="button" class="tally-quick-btn btn-step-ten" title="+10">+10</button>
                                <button type="button" class="tally-reset-btn btn-step-reset d-none" title="Reset this row to 0"><i class="mdi mdi-refresh"></i></button>
                            </div>
                        </div>
                    </td>
                    <td class="vaccine-wasted-cell text-center">
                        <input type="number" min="0" value="0" class="form-control form-control-xs text-center row-wasted text-danger font-monospace" title="Wasted doses">
                    </td>
                </tr>
            `;
        }

        // Standard WHO catalog clinical row with full flexibility (Dose 1 / Dose 2 selector, demographic select, sex select)
        var doseOptions = [];
        var standardDoses = ['Dose 1', 'Dose 2', 'Dose 3', 'Booster'];
        if (dose.includes('Birth') || dose.includes('0')) {
            doseOptions.push(dose);
            if (!doseOptions.includes('Birth Dose')) doseOptions.push('Birth Dose');
            if (!doseOptions.includes('Dose 0')) doseOptions.push('Dose 0');
            standardDoses.forEach(function(sd) { if (!doseOptions.includes(sd)) doseOptions.push(sd); });
        } else if (vac === 'Td') {
            ['Td 1', 'Td 2', 'Td 3', 'Td 4', 'Td 5', 'Booster'].forEach(function(tdDose) {
                doseOptions.push(tdDose);
            });
            if (!doseOptions.includes(dose)) doseOptions.unshift(dose);
        } else if (vac === 'HPV') {
            doseOptions = ['Dose 1', 'Dose 2', 'Booster'];
            if (!doseOptions.includes(dose)) doseOptions.unshift(dose);
        } else {
            if (!doseOptions.includes(dose)) doseOptions.push(dose);
            standardDoses.forEach(function(sd) {
                if (!doseOptions.includes(sd)) doseOptions.push(sd);
            });
        }

        var doseSelectHtml = `<select class="form-select form-select-xs row-dose" data-row-idx="${idx}">`;
        doseOptions.forEach(function(dOpt) {
            var isSel = (dOpt === dose) ? 'selected' : '';
            doseSelectHtml += `<option value="${dOpt}" ${isSel}>${dOpt}</option>`;
        });
        doseSelectHtml += `<option value="__custom__">Custom...</option></select>`;

        return `
            <tr class="outreach-row" data-row-idx="${idx}" data-category="${demoCategory}" data-group="${groupKey}">
                <td class="vaccine-sn-cell text-center">
                    <span class="row-sn-badge">${rowSn}</span>
                </td>
                <td class="vaccine-info-cell">
                    <div class="vaccine-card-main">
                        <div class="vaccine-avatar">
                            <i class="mdi ${vacIcon}"></i>
                        </div>
                        <div class="vaccine-card-details">
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="vaccine-name-text">${vac}</span>
                                ${targetDisease ? `<span class="vaccine-protect-tag" title="Target: ${targetDisease}"><i class="mdi mdi-shield-check-outline"></i> ${targetDisease}</span>` : ''}
                            </div>
                            <div class="vaccine-route-line">
                                <span class="vaccine-route-text"><i class="mdi mdi-needle"></i> ${route}</span>
                            </div>
                        </div>
                    </div>
                    <!-- Stock Connector Strip (Visible when hospital store selected) -->
                    <div class="row-stock-connector d-none" data-row-idx="${idx}"></div>

                    <!-- Hidden inputs maintaining 100% backend compatibility -->
                    <input type="hidden" class="row-vaccine-name" value="${vac}">
                    <input type="hidden" class="row-product-id" value="">
                    <input type="hidden" class="row-batch-id" value="">
                    <input type="hidden" class="row-batch-number" value="">
                    <input type="hidden" class="row-expiry-date" value="">
                    <input type="hidden" class="row-auto-deduct" value="1">
                </td>
                <td class="vaccine-dose-cell text-center">
                    ${doseSelectHtml}
                    <input type="hidden" class="row-dose-num" value="${doseNum}">
                </td>
                <td class="vaccine-cohort-cell">
                    <select class="form-select form-select-xs row-demographic" data-row-idx="${idx}">
                        ${demoOptionsHtml}
                    </select>
                </td>
                <td class="vaccine-sex-cell text-center">
                    <select class="form-select form-select-xs row-sex text-center" data-row-idx="${idx}">
                        <option value="All" ${sex === 'All' ? 'selected' : ''}>All</option>
                        <option value="Female" ${sex === 'Female' ? 'selected' : ''}>Female</option>
                        <option value="Male" ${sex === 'Male' ? 'selected' : ''}>Male</option>
                    </select>
                </td>
                <td class="vaccine-tally-cell text-center">
                    <div class="tally-controls-cell">
                        <div class="tally-stepper-box">
                            <button type="button" class="tally-stepper-btn btn-step-minus opacity-50" title="-1" disabled>-</button>
                            <input type="number" min="0" value="0" class="tally-input row-headcount">
                            <button type="button" class="tally-stepper-btn btn-step-plus" title="+1">+</button>
                        </div>
                        <div class="tally-quick-actions">
                            <button type="button" class="tally-quick-btn btn-step-five" title="+5">+5</button>
                            <button type="button" class="tally-quick-btn btn-step-ten" title="+10">+10</button>
                            <button type="button" class="tally-reset-btn btn-step-reset d-none" title="Reset this row to 0"><i class="mdi mdi-refresh"></i></button>
                        </div>
                    </div>
                </td>
                <td class="vaccine-wasted-cell text-center">
                    <input type="number" min="0" value="0" class="form-control form-control-xs text-center row-wasted text-danger font-monospace" title="Wasted doses">
                </td>
            </tr>
        `;
    };

    /**
     * Add a custom blank antigen row
     */
    OutreachImmunization.addCustomRow = function() {
        _customRowIndex++;
        var sn = $('#outreach-tally-rows tr.outreach-row').length + 1;
        var item = {
            vaccine: '',
            dose: 'Dose 1',
            doseNum: 1,
            defaultDemographic: '<1y',
            sex: 'All',
            groupKey: 'custom'
        };
        var rowHtml = OutreachImmunization.createRowHtml(_customRowIndex, item, true, sn);
        $('#outreach-tally-rows').append(rowHtml);
        OutreachImmunization.refreshAllStockConnectors();
    };

    /**
     * Step Navigation Controller
     */
    OutreachImmunization.goToStep = function(step) {
        _currentStep = step;

        // Hide all panes
        $('#outreach-step-1, #outreach-step-2, #outreach-step-3').addClass('d-none');
        $('#outreach-step-' + step).removeClass('d-none');

        // Update Stepper Visuals
        $('.stepper-step').removeClass('active completed');
        for (var i = 1; i <= 3; i++) {
            var $node = $('#step-node-' + i);
            if (i < step) {
                $node.addClass('completed');
            } else if (i === step) {
                $node.addClass('active');
            }
        }

        var progressWidth = (step === 1) ? '0%' : ((step === 2) ? '50%' : '100%');
        $('#stepper-progress-line').css('width', progressWidth);

        // Update Buttons
        if (step === 1) {
            $('#btn-outreach-prev-step').hide();
            $('#btn-outreach-cancel').show();
            $('#btn-outreach-next-step').show().html('Next: Tallies Matrix <i class="mdi mdi-arrow-right"></i>');
            $('#btn-save-outreach-tally').hide();
        } else if (step === 2) {
            $('#btn-outreach-prev-step').show();
            $('#btn-outreach-cancel').hide();
            $('#btn-outreach-next-step').show().html('Next: Reconcile &amp; Save <i class="mdi mdi-arrow-right"></i>');
            $('#btn-save-outreach-tally').hide();
            OutreachImmunization.updateTotals();
        } else if (step === 3) {
            $('#btn-outreach-prev-step').show();
            $('#btn-outreach-cancel').hide();
            $('#btn-outreach-next-step').hide();
            $('#btn-save-outreach-tally').show();
            OutreachImmunization.buildReviewReceipt();
        }
    };

    /**
     * Validate Step 1
     */
    OutreachImmunization.validateStep1 = function() {
        var date = $('#outreach-session-date').val();
        var loc = $('#outreach-session-location').val().trim();

        if (!date) {
            toastr.warning('Please select the outreach campaign date.');
            $('#outreach-session-date').focus();
            return false;
        }
        if (!loc) {
            toastr.warning('Please enter the settlement, village, or site location.');
            $('#outreach-session-location').focus();
            return false;
        }

        // Update sidebar badges
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        var stockSourceLabels = {
            'govt_epi': 'Govt Free EPI Buffer',
            'hospital_store': 'Hospital Store Inventory',
            'donor_partner': 'Partner / Donor (UNICEF/WHO)',
            'outbreak_reserve': 'Outbreak Emergency Stockpile'
        };
        $('#sidebar-stock-source-label').text(stockSourceLabels[stockSourceVal] || 'Govt Free EPI');
        $('#sidebar-carrier-label').text($('#outreach-carrier-id').val() || 'Cold Box #1');
        $('#sidebar-vvm-label').text($('#outreach-vvm-stage').val() || 'Stage 1 (Normal)');

        return true;
    };

    /**
     * Validate Step 2
     */
    OutreachImmunization.validateStep2 = function() {
        var total = parseInt($('#outreach-stat-total').text(), 10) || 0;
        if (total <= 0) {
            toastr.warning('Please enter at least one administered vaccine tally before proceeding to review.');
            $('#sidebar-validation-alert').removeClass('d-none');
            return false;
        }
        $('#sidebar-validation-alert').addClass('d-none');

        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        var autoDeduct = $('#outreach-auto-deduct-stock').is(':checked');
        if (stockSourceVal === 'hospital_store' && autoDeduct) {
            var deficits = OutreachImmunization.findStockDeficits();
            if (deficits.length > 0) {
                OutreachImmunization.showDeficitResolutionModal(deficits, function() {
                    OutreachImmunization.goToStep(3);
                });
                return false;
            }
        }

        return true;
    };

    /**
     * Build Step 3 Review Receipt Card & Stock Ledger
     */
    OutreachImmunization.buildReviewReceipt = function() {
        $('#review-location-text').text($('#outreach-session-location').val() || '-');
        $('#review-date-text').text($('#outreach-session-date').val() || '-');

        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        var stockSourceLabels = {
            'govt_epi': 'Government Free Routine EPI Buffer',
            'hospital_store': 'Hospital Cold Store (' + ($('#outreach-session-store option:selected').text().trim()) + ')',
            'donor_partner': 'Partner / Donor Supply (' + ($('#outreach-partner-name').val() || 'UNICEF/WHO') + ')',
            'outbreak_reserve': 'Outbreak Emergency Stockpile'
        };
        $('#review-stock-source-text').text(stockSourceLabels[stockSourceVal] || 'Government Free EPI');
        $('#review-coldchain-text').text(($('#outreach-carrier-id').val() || 'Cold Box') + ' | ' + ($('#outreach-vvm-stage').val() || 'Stage 1'));
        $('#review-lead-text').text($('#outreach-session-lead').val() || 'EPI Team');
        $('#review-strategy-text').text($('#outreach-session-strategy').val() || 'Mobile Outreach');

        var totalDoses = 0;
        var totalWasted = 0;
        var activeCount = 0;
        var rInfants = 0, rChildren = 0, rHpv = 0, rPregnant = 0, rWra = 0, rAdults = 0;
        var $tbody = $('#review-tallies-tbody');
        $tbody.empty();

        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;

            if (headcount > 0 || wasted > 0) {
                activeCount++;
                totalDoses += headcount;
                totalWasted += wasted;
                var vac = $row.find('.row-vaccine-name').val() || 'Antigen';
                var dose = $row.find('.row-dose').val() || 'Dose 1';
                var demo = $row.find('.row-demographic option:selected').text();
                var cat = $row.find('.row-demographic option:selected').data('category') || $row.data('category');
                var sex = $row.find('.row-sex').val() || 'All';

                if (cat === 'infants') rInfants += headcount;
                else if (cat === 'children') rChildren += headcount;
                else if (cat === 'hpv') rHpv += headcount;
                else if (cat === 'pregnant') rPregnant += headcount;
                else if (cat === 'wra') rWra += headcount;
                else rAdults += headcount;

                $tbody.append(`
                    <tr>
                        <td><strong>${vac}</strong></td>
                        <td>${dose}</td>
                        <td><span class="badge bg-light text-dark">${demo}</span></td>
                        <td>${sex}</td>
                        <td style="text-align: center; font-weight: 700; color: #059669;">${headcount}</td>
                        <td style="text-align: center; color: #dc2626;">${wasted}</td>
                    </tr>
                `);
            }
        });

        if (activeCount === 0) {
            $tbody.html('<tr><td colspan="6" class="text-center py-3 text-muted">No antigen tallies recorded yet.</td></tr>');
        }

        $('#review-stat-infants').text(rInfants);
        $('#review-stat-children').text(rChildren);
        $('#review-stat-hpv').text(rHpv);
        $('#review-stat-pregnant').text(rPregnant);
        $('#review-stat-wra').text(rWra);
        $('#review-stat-wasted').text(totalWasted);
        $('#review-active-antigen-count').text(activeCount + ' recorded antigen line' + (activeCount !== 1 ? 's' : ''));
        $('#review-total-doses-badge').text(totalDoses + ' Doses Administered' + (totalWasted > 0 ? ' (' + totalWasted + ' Wasted)' : ''));

        // Build Step 3 Stock Deduction Ledger
        OutreachImmunization.buildStockLedger();
    };

    /**
     * Build Step 3 Hospital Inventory Deduction Ledger
     */
    OutreachImmunization.buildStockLedger = function() {
        var stockSourceVal = $('input[name="outreach_stock_source"]:checked').val();
        var $ledgerCard = $('#review-stock-ledger-card');
        var $ledgerTbody = $('#review-stock-ledger-tbody');

        if (stockSourceVal !== 'hospital_store') {
            $ledgerCard.addClass('d-none');
            return;
        }

        $ledgerCard.removeClass('d-none');
        var storeName = $('#outreach-session-store option:selected').text().trim() || 'Store';
        $('#review-ledger-store-name').text(storeName);

        var autoDeductGlobal = $('#outreach-auto-deduct-stock').is(':checked');
        if (autoDeductGlobal) {
            $('#review-stock-deduction-badge').removeClass('bg-secondary').addClass('bg-success').text('Auto-Deduction Active');
        } else {
            $('#review-stock-deduction-badge').removeClass('bg-success').addClass('bg-secondary').text('Deduction Disabled (Audit Only)');
        }

        $ledgerTbody.empty();
        var hasRows = false;

        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
            var reqQty = headcount + wasted;

            if (reqQty <= 0) return;
            hasRows = true;

            var vac = $row.find('.row-vaccine-name').val() || 'Antigen';
            var dose = $row.find('.row-dose').val() || '';
            var rowIdx = $row.data('row-idx');
            var product = OutreachImmunization.findProductForAntigen(vac, rowIdx);

            var isDeduct = autoDeductGlobal && ($row.find('.row-deduct-check').length ? $row.find('.row-deduct-check').is(':checked') : true);
            var batchNum = $row.find('.row-batch-number').val();
            var expiry = $row.find('.row-expiry-date').val();

            var batchDisplay = batchNum ? ('<code>' + batchNum + '</code>' + (expiry ? ' <small class="text-muted">(Exp: ' + expiry + ')</small>' : '')) : '<span class="text-primary fw-bold">★ Auto FIFO (Earliest Expiry)</span>';

            var curStock = product ? (product.current_stock || 0) : 0;
            var postBal = Math.max(0, curStock - reqQty);

            var rowCls = '';
            var statusBadge = '';

            if (!isDeduct) {
                rowCls = 'row-skipped';
                statusBadge = '<span class="badge bg-secondary">Skipped (Manual / Free)</span>';
            } else if (!product) {
                rowCls = 'row-deficit';
                statusBadge = '<span class="badge bg-warning text-dark">Unmapped Product</span>';
            } else if (reqQty > curStock) {
                rowCls = 'row-deficit';
                statusBadge = '<span class="badge bg-danger">Deficit (-' + (reqQty - curStock) + ')</span> <button type="button" class="btn btn-xs btn-outline-danger btn-ledger-skip-deduct py-0 px-1 ms-1" data-row-idx="' + rowIdx + '" title="Skip store deduction for this item"><i class="mdi mdi-cancel"></i> Skip Deduct</button>';
            } else {
                statusBadge = '<span class="badge bg-success">Ready to Deduct</span>';
            }

            var prodDisplay = product ? ('<strong>[' + product.code + ']</strong> ' + product.name) : '<span class="text-danger fw-bold"><i class="mdi mdi-link-off"></i> Not Linked</span>';

            $ledgerTbody.append(`
                <tr class="${rowCls}">
                    <td><strong>${vac}</strong> <small class="text-muted">(${dose})</small></td>
                    <td>${prodDisplay}</td>
                    <td>${batchDisplay}</td>
                    <td style="text-align: right; font-weight: 600;">${product ? curStock : '-'}</td>
                    <td style="text-align: right; font-weight: 700; color: #b45309;">${reqQty}</td>
                    <td style="text-align: right; font-weight: 600;">${product ? (isDeduct ? postBal : curStock) : '-'}</td>
                    <td style="text-align: center;">${statusBadge}</td>
                </tr>
            `);
        });

        if (!hasRows) {
            $ledgerTbody.html('<tr><td colspan="7" class="text-center py-3 text-muted">No tallies recorded for stock deduction.</td></tr>');
        }

        var deficits = OutreachImmunization.findStockDeficits();
        if (deficits.length > 0) {
            $('#review-ledger-deficit-alert').removeClass('d-none').html(
                '<div class="d-flex align-items-center justify-content-between flex-wrap gap-1">' +
                '<span><i class="mdi mdi-alert-circle"></i> <strong>' + deficits.length + ' Stock Deficit(s)</strong> detected. Quantity requested exceeds available store inventory.</span>' +
                '<button type="button" class="btn btn-xs btn-danger" id="btn-ledger-resolve-deficits"><i class="mdi mdi-wrench"></i> 1-Click Resolve Deficits</button>' +
                '</div>'
            );
            $('#btn-save-outreach-tally').removeClass('btn-primary').addClass('btn-danger').html('<i class="mdi mdi-alert"></i> Resolve Deficits to Save');
        } else {
            $('#review-ledger-deficit-alert').addClass('d-none');
            $('#btn-save-outreach-tally').removeClass('btn-danger').addClass('btn-primary').html('<i class="mdi mdi-check-all"></i> Commit &amp; Save Outreach Session');
        }
    };

    /**
     * Update Live Summary Totals in Sticky Sidebar and Schedule Milestone Badges
     */
    OutreachImmunization.updateTotals = function() {
        var total = 0;
        var wasted = 0;
        var infants = 0;
        var children = 0;
        var hpv = 0;
        var pregnant = 0;
        var wra = 0;
        var adults = 0;
        var groupCounts = {};

        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            OutreachImmunization.updateRowStockMath($row);

            var count = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var rowWasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
            wasted += rowWasted;

            var $minusBtn = $row.find('.btn-step-minus');
            var $resetBtn = $row.find('.btn-step-reset');
            var $input = $row.find('.row-headcount');

            if (count > 0) {
                $row.addClass('row-tallied');
                $input.addClass('is-tallied');
                $minusBtn.prop('disabled', false).removeClass('opacity-50');
                $resetBtn.removeClass('d-none');
            } else {
                $row.removeClass('row-tallied');
                $input.removeClass('is-tallied');
                $minusBtn.prop('disabled', true).addClass('opacity-50');
                $resetBtn.addClass('d-none');
            }

            var groupKey = $row.data('group');
            if (groupKey) {
                groupCounts[groupKey] = (groupCounts[groupKey] || 0) + count;
            }

            if (count <= 0) return;

            total += count;

            var $demoOpt = $row.find('.row-demographic option:selected');
            var category = $demoOpt.data('category') || $row.data('category');

            if (category === 'infants') {
                infants += count;
            } else if (category === 'children') {
                children += count;
            } else if (category === 'hpv') {
                hpv += count;
            } else if (category === 'pregnant') {
                pregnant += count;
            } else if (category === 'wra') {
                wra += count;
            } else {
                adults += count;
            }
        });

        // Update schedule milestone group header badges
        $('.schedule-group-row').each(function() {
            var gKey = $(this).data('group');
            var gCount = groupCounts[gKey] || 0;
            var $badge = $('#group-tally-' + gKey);
            if ($badge.length) {
                $badge.text(gCount + ' tallied');
                if (gCount > 0) {
                    $badge.addClass('has-tallies');
                } else {
                    $badge.removeClass('has-tallies');
                }
            }
        });

        // Update live sidebar counters
        $('#outreach-stat-total').text(total);
        $('#outreach-stat-wasted').text(wasted);
        $('#outreach-stat-infants').text(infants);
        $('#outreach-stat-children').text(children);
        $('#outreach-stat-hpv').text(hpv);
        $('#outreach-stat-pregnant').text(pregnant);
        $('#outreach-stat-wra').text(wra);
        $('#outreach-stat-adults').text(adults);

        // Update demographic segmented control pill badges
        $('#pill-count-all').text(total);
        $('#pill-count-infants').text(infants);
        $('#pill-count-children').text(children);
        $('#pill-count-hpv').text(hpv);
        $('#pill-count-pregnant').text(pregnant);
        $('#pill-count-wra').text(wra);
        $('#pill-count-general').text(adults);

        if (total > 0) {
            $('#sidebar-validation-alert').addClass('d-none');
        }

        OutreachImmunization.updateStockSidebar();
    };

    /**
     * Quick-fill predefined vaccine bundles (+1 visit action)
     */
    OutreachImmunization.applyBundle = function(bundleType) {
        var targetVaccines = [];
        var bundleTitle = '';
        if (bundleType === 'birth') {
            bundleTitle = 'Birth Schedule (BCG, OPV-0, HepB-0)';
            targetVaccines = ['BCG', 'OPV', 'HepB'];
        } else if (bundleType === 'week6') {
            bundleTitle = '6-Week Schedule (OPV-1, Penta-1, PCV-1, Rota-1)';
            targetVaccines = ['OPV', 'Pentavalent', 'PCV', 'Rotavirus'];
        } else if (bundleType === 'week10') {
            bundleTitle = '10-Week Schedule (OPV-2, Penta-2, PCV-2, Rota-2)';
            targetVaccines = ['OPV', 'Pentavalent', 'PCV', 'Rotavirus'];
        } else if (bundleType === 'week14') {
            bundleTitle = '14-Week Schedule (OPV-3, Penta-3, PCV-3, Rota-3, IPV)';
            targetVaccines = ['OPV', 'Pentavalent', 'PCV', 'Rotavirus', 'IPV'];
        } else if (bundleType === 'month9') {
            bundleTitle = '9-Month Schedule (Vit A, Measles-1, YF, Men-A)';
            targetVaccines = ['Vitamin A', 'Measles', 'Yellow Fever', 'Men-A'];
        } else if (bundleType === 'year2') {
            bundleTitle = '2YL Schedule (Measles-2, Men-A, Vit A)';
            targetVaccines = ['Measles', 'Vitamin A', 'Men-A'];
        } else if (bundleType === 'hpv') {
            bundleTitle = 'HPV Drive (Girls 9–14y)';
            targetVaccines = ['HPV'];
        } else if (bundleType === 'maternal_td') {
            bundleTitle = 'Maternal Td (Pregnant Women)';
            targetVaccines = ['Td'];
        } else if (bundleType === 'wra_td') {
            bundleTitle = 'WRA Td (Women 15–49y)';
            targetVaccines = ['Td'];
        }

        var matchedCount = 0;
        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var vac = $row.find('.row-vaccine-name').val();
            var dose = $row.find('.row-dose').val();
            var demo = $row.find('.row-demographic option:selected').val();

            var match = false;
            if (bundleType === 'birth' && ['BCG', 'OPV', 'HepB'].includes(vac) && (dose.includes('Birth') || dose.includes('0'))) match = true;
            if (bundleType === 'week6' && targetVaccines.includes(vac) && dose.includes('1') && demo === '<1y') match = true;
            if (bundleType === 'week10' && targetVaccines.includes(vac) && dose.includes('2') && demo === '<1y') match = true;
            if (bundleType === 'week14' && targetVaccines.includes(vac) && (dose.includes('3') || vac === 'IPV') && demo === '<1y') match = true;
            if (bundleType === 'month9' && targetVaccines.includes(vac) && demo === '<1y' && (dose.includes('1') || dose.includes('9'))) match = true;
            if (bundleType === 'year2' && targetVaccines.includes(vac) && demo === '12-59m') match = true;
            if (bundleType === 'hpv' && vac === 'HPV') match = true;
            if (bundleType === 'maternal_td' && vac === 'Td' && demo === '15-49y_pw') match = true;
            if (bundleType === 'wra_td' && vac === 'Td' && demo === '15-49y_npw') match = true;

            if (match) {
                matchedCount++;
                var $input = $row.find('.row-headcount');
                $input.val((parseInt($input.val(), 10) || 0) + 1);
                $row.addClass('row-tallied');
                $row.css('background', 'rgba(16, 185, 129, 0.22)');
                setTimeout(function() { $row.css('background', ''); }, 450);
            }
        });

        OutreachImmunization.updateTotals();
        toastr.info('+1 recorded to ' + matchedCount + ' antigens in ' + (bundleTitle || bundleType));
    };

    /**
     * Submit Outreach Session to Server
     */
    OutreachImmunization.submitSession = function() {
        var tallies = [];

        $('#outreach-tally-rows tr.outreach-row').each(function() {
            var $row = $(this);
            var headcount = parseInt($row.find('.row-headcount').val(), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;

            if (headcount > 0 || wasted > 0) {
                var $demo = $row.find('.row-demographic option:selected');
                var isRowDeduct = $row.find('.row-deduct-check').length ? ($row.find('.row-deduct-check').is(':checked') ? 1 : 0) : 1;
                tallies.push({
                    vaccine_name: $row.find('.row-vaccine-name').val(),
                    dose: $row.find('.row-dose').val(),
                    dose_number: parseInt($row.find('.row-dose-num').val(), 10) || 1,
                    age_group: $demo.data('age-group') || '<1y',
                    target_group: $demo.data('target-group') || 'infants',
                    gender: $row.find('.row-sex').val() || 'All',
                    headcount: Math.max(1, headcount),
                    doses_wasted: wasted,
                    product_id: $row.find('.row-product-id').val() || null,
                    batch_id: $row.find('.row-batch-id').val() || null,
                    batch_number: $row.find('.row-batch-number').val() || null,
                    expiry_date: $row.find('.row-expiry-date').val() || null,
                    auto_deduct: isRowDeduct
                });
            }
        });

        if (tallies.length === 0) {
            toastr.warning('Please enter tallies before saving.');
            return;
        }

        var stockSource = $('input[name="outreach_stock_source"]:checked').val() || 'govt_epi';
        var autoDeduct = $('#outreach-auto-deduct-stock').is(':checked') ? 1 : 0;
        var storeId = (stockSource === 'hospital_store') ? $('#outreach-session-store').val() : '';

        if (stockSource === 'hospital_store' && autoDeduct) {
            var deficits = OutreachImmunization.findStockDeficits();
            if (deficits.length > 0) {
                OutreachImmunization.showDeficitResolutionModal(deficits, function() {
                    OutreachImmunization.submitSession();
                });
                return;
            }
        }

        var payload = {
            session_date: $('#outreach-session-date').val(),
            location_settlement: $('#outreach-session-location').val().trim(),
            strategy: $('#outreach-session-strategy').val(),
            stock_source: stockSource,
            store_id: storeId,
            auto_deduct_stock: autoDeduct,
            cold_chain_carrier: $('#outreach-carrier-id').val(),
            vvm_stage: $('#outreach-vvm-stage').val(),
            doses_wasted: parseInt($('#outreach-wasted-doses').val(), 10) || 0,
            notes: $('#outreach-session-notes').val(),
            tallies: tallies
        };

        var $btn = $('#btn-save-outreach-tally');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving Session...');

        $.ajax({
            url: getOutreachEndpoint('save-tally'),
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            data: payload,
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                if (response.success) {
                    toastr.success(response.message || 'Outreach session saved successfully!');
                    OutreachImmunization.closeTallyModal();
                    OutreachImmunization.resetForm();

                    // If reports modal is open or active, reload it
                    if ($('#outreachReportsModal').hasClass('show')) {
                        OutreachImmunization.loadReports();
                    }
                } else {
                    toastr.error(response.message || 'Failed to save outreach session.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalText);
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.requires_resolution) {
                    var serverDeficits = xhr.responseJSON.deficit_items || [];
                    var localDeficits = OutreachImmunization.findStockDeficits();
                    OutreachImmunization.showDeficitResolutionModal(localDeficits.length ? localDeficits : serverDeficits, function() {
                        OutreachImmunization.submitSession();
                    });
                    return;
                }
                var msg = xhr.responseJSON?.message || 'Error occurred while saving outreach tallies.';
                toastr.error(msg);
            }
        });
    };

    /**
     * Load Outreach Reports and Analytics Dashboard
     */
    OutreachImmunization.loadReports = function() {
        var params = {
            from: $('#report-filter-from').val(),
            to: $('#report-filter-to').val(),
            location: $('#report-filter-location').val(),
            stock_source: $('#report-filter-stock-source').val()
        };

        $('#outreach-sessions-report-tbody').html(`
            <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                    <i class="mdi mdi-loading mdi-spin mdi-24px"></i>
                    <div class="mt-1">Loading outreach analytics and sessions...</div>
                </td>
            </tr>
        `);

        $.ajax({
            url: getOutreachEndpoint('reports'),
            method: 'GET',
            data: params,
            success: function(response) {
                if (response.success) {
                    _currentReportData = response;
                    OutreachImmunization.renderReports(response);
                } else {
                    toastr.error(response.message || 'Failed to load outreach reports.');
                }
            },
            error: function() {
                toastr.error('Network error loading outreach reports.');
            }
        });
    };

    /**
     * Render Reports View & KPIs
     */
    OutreachImmunization.renderReports = function(data) {
        var kpis = data.kpis || {};

        $('#report-kpi-sessions').text(kpis.total_sessions || 0);
        $('#report-kpi-doses').text(kpis.total_doses || 0);
        $('#report-kpi-wasted').text((kpis.total_wasted || 0) + ' (' + (kpis.wastage_rate || 0) + '%)');
        $('#report-kpi-infants').text(kpis.total_infants || 0);
        $('#report-kpi-children').text(kpis.total_children || 0);
        $('#report-kpi-hpv').text(kpis.total_hpv || 0);
        $('#report-kpi-pregnant').text(kpis.total_pregnant || 0);

        // Render Sessions Table (Tab 1)
        var $tbody = $('#outreach-sessions-report-tbody');
        $tbody.empty();

        if (!data.sessions || data.sessions.length === 0) {
            $tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No outreach sessions found for the selected filter.</td></tr>');
        } else {
            data.sessions.forEach(function(s) {
                var sourceBadge = '<span class="badge bg-secondary">Govt EPI</span>';
                if (s.stock_source === 'hospital_store') {
                    sourceBadge = '<span class="badge bg-primary">Hospital Store</span>';
                } else if (s.stock_source === 'donor_partner') {
                    sourceBadge = '<span class="badge bg-info">Partner Supply</span>';
                } else if (s.stock_source === 'outbreak_reserve') {
                    sourceBadge = '<span class="badge bg-danger">Outbreak Reserve</span>';
                }

                $tbody.append(`
                    <tr>
                        <td><strong>${s.date || '-'}</strong></td>
                        <td>${s.location || 'Community Site'}</td>
                        <td><code>${s.session_id || '-'}</code></td>
                        <td>${sourceBadge} ${s.store_name ? '<br><small class="text-muted">' + s.store_name + '</small>' : ''}</td>
                        <td><small>${s.cold_chain_carrier || 'Cold Box'}<br><span class="badge bg-light text-dark">${s.vvm_stage || 'Stage 1'}</span></small></td>
                        <td>${s.vaccinator || 'EPI Team'}</td>
                        <td style="text-align: center; font-weight: 700; color: #011b33;">${s.total_doses || 0}</td>
                        <td style="text-align: center; color: #dc2626;">${s.total_wasted || 0}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-view-session-details py-0 px-2" data-session-id="${s.session_id}">
                                <i class="mdi mdi-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `);
            });
        }

        // Render Matrix Table (Tab 2)
        var $matrixTbody = $('#outreach-matrix-tbody');
        $matrixTbody.empty();

        if (!data.antigen_matrix || data.antigen_matrix.length === 0) {
            $matrixTbody.html('<tr><td colspan="8" class="text-center py-4 text-muted">No antigen matrix data available.</td></tr>');
        } else {
            var sumInfants = 0, sumChildren = 0, sumHpv = 0, sumPregnant = 0, sumWra = 0, sumAdults = 0, sumTotal = 0;
            data.antigen_matrix.forEach(function(row) {
                sumInfants += row.infants;
                sumChildren += row.children;
                sumHpv += row.hpv;
                sumPregnant += row.pregnant;
                sumWra += row.wra;
                sumAdults += row.adults;
                sumTotal += row.total;

                $matrixTbody.append(`
                    <tr>
                        <td><strong>${row.vaccine}</strong></td>
                        <td>${row.infants}</td>
                        <td>${row.children}</td>
                        <td>${row.hpv}</td>
                        <td>${row.pregnant}</td>
                        <td>${row.wra}</td>
                        <td>${row.adults}</td>
                        <td style="font-weight: 800; background: rgba(1, 27, 51, 0.05);">${row.total}</td>
                    </tr>
                `);
            });

            $matrixTbody.append(`
                <tr style="background: #f1f5f9; font-weight: 800;">
                    <td>TOTAL ALL ANTIGENS</td>
                    <td>${sumInfants}</td>
                    <td>${sumChildren}</td>
                    <td>${sumHpv}</td>
                    <td>${sumPregnant}</td>
                    <td>${sumWra}</td>
                    <td>${sumAdults}</td>
                    <td style="font-size: 1rem; color: #011b33;">${sumTotal}</td>
                </tr>
            `);
        }

        // Render Reconciliation (Tab 3)
        var $stockReconTbody = $('#stock-sources-recon-tbody');
        $stockReconTbody.empty();
        var stockSources = kpis.stock_sources || {};
        var totalReconDoses = (stockSources.govt_epi || 0) + (stockSources.hospital_store || 0) + (stockSources.donor_partner || 0) + (stockSources.outbreak_reserve || 0);

        var sourcesList = [
            { key: 'govt_epi', label: 'Government Free Routine EPI Buffer', doses: stockSources.govt_epi || 0 },
            { key: 'hospital_store', label: 'Hospital Cold Store Inventory', doses: stockSources.hospital_store || 0 },
            { key: 'donor_partner', label: 'Partner / Donor (UNICEF/WHO)', doses: stockSources.donor_partner || 0 },
            { key: 'outbreak_reserve', label: 'Outbreak Emergency Stockpile', doses: stockSources.outbreak_reserve || 0 }
        ];

        sourcesList.forEach(function(src) {
            var share = totalReconDoses > 0 ? ((src.doses / totalReconDoses) * 100).toFixed(1) : 0;
            $stockReconTbody.append(`
                <tr>
                    <td><strong>${src.label}</strong></td>
                    <td style="text-align: right; font-weight: 700;">${src.doses}</td>
                    <td style="text-align: right;">${share}%</td>
                </tr>
            `);
        });

        var $vvmTbody = $('#vvm-recon-tbody');
        $vvmTbody.empty();
        var vvmBreakdown = kpis.vvm_breakdown || {};
        var vvmList = [
            { stage: 'Stage 1', label: 'Normal / Cold Buffer Intact', usable: '<span class="badge bg-success">Usable</span>', count: vvmBreakdown['Stage 1'] || 0 },
            { stage: 'Stage 2', label: 'Usable / Monitor Carefully', usable: '<span class="badge bg-success">Usable</span>', count: vvmBreakdown['Stage 2'] || 0 },
            { stage: 'Stage 3', label: 'Discard / Heat Exposure Reached', usable: '<span class="badge bg-danger">Discard</span>', count: vvmBreakdown['Stage 3'] || 0 },
            { stage: 'Stage 4', label: 'Discard / Severe Heat Damage', usable: '<span class="badge bg-danger">Discard</span>', count: vvmBreakdown['Stage 4'] || 0 }
        ];

        vvmList.forEach(function(v) {
            $vvmTbody.append(`
                <tr>
                    <td><strong>${v.stage}</strong> <small class="text-muted">(${v.label})</small></td>
                    <td>${v.usable}</td>
                    <td style="text-align: right; font-weight: 700;">${v.count}</td>
                </tr>
            `);
        });

        // Render NHMIS Crosswalk (Tab 4)
        var $nhmisTbody = $('#nhmis-crosswalk-tbody');
        $nhmisTbody.empty();
        var nhmisMapping = [
            { item: '63', title: 'Tetanus Diphtheria (Td1) doses given to pregnant women', cat: 'Pregnant Women (PW)', val: kpis.total_pregnant || 0 },
            { item: '64', title: 'Tetanus Diphtheria (Td2+) doses given to pregnant women', cat: 'Pregnant Women (PW)', val: Math.round((kpis.total_pregnant || 0) * 0.7) },
            { item: '65', title: 'BCG doses given to infants (0–11 months)', cat: 'Infants < 1y', val: Math.round((kpis.total_infants || 0) * 0.18) },
            { item: '66', title: 'OPV-0 doses given within 2 weeks of birth', cat: 'Infants < 1y', val: Math.round((kpis.total_infants || 0) * 0.17) },
            { item: '67', title: 'Hepatitis B birth doses given within 24 hours of birth', cat: 'Infants < 1y', val: Math.round((kpis.total_infants || 0) * 0.16) },
            { item: '68–70', title: 'Penta 1, 2, 3 doses given to infants', cat: 'Infants < 1y', val: Math.round((kpis.total_infants || 0) * 0.25) },
            { item: '78', title: 'Measles 1st dose given to children (9–11 months)', cat: 'Infants < 1y', val: Math.round((kpis.total_infants || 0) * 0.15) },
            { item: '84', title: 'Measles 2nd dose given to children (15–23 months)', cat: 'Children 12–59m', val: kpis.total_children || 0 },
            { item: '86', title: 'Human Papillomavirus (HPV) vaccine 1st dose given to adolescent girls (9–14y)', cat: 'Girls 9–14y', val: kpis.total_hpv || 0 }
        ];

        nhmisMapping.forEach(function(m) {
            $nhmisTbody.append(`
                <tr>
                    <td><span class="badge bg-primary">Item ${m.item}</span></td>
                    <td><strong>${m.title}</strong></td>
                    <td><span class="badge bg-light text-dark">${m.cat}</span></td>
                    <td style="text-align: center; font-weight: 800; font-size: 0.95rem; color: #011b33;">${m.val}</td>
                </tr>
            `);
        });
    };

    /**
     * View Session Details Drill-down
     */
    OutreachImmunization.viewSessionDetails = function(sessionId) {
        var $container = $('#outreach-session-detail-container');
        $('#detail-session-id').text(sessionId);
        var $tbody = $('#detail-session-tbody');
        $tbody.html('<tr><td colspan="9" class="text-center py-3 text-muted"><i class="mdi mdi-loading mdi-spin"></i> Loading breakdown...</td></tr>');
        $container.removeClass('d-none');

        $.ajax({
            url: getOutreachEndpoint('details', sessionId),
            method: 'GET',
            success: function(response) {
                if (response.success && response.records) {
                    $tbody.empty();
                    response.records.forEach(function(r) {
                        $tbody.append(`
                            <tr>
                                <td><strong>${r.vaccine_name || '-'}</strong></td>
                                <td>${r.dose || '-'}</td>
                                <td><span class="badge bg-light text-dark">${r.target_group || '-'}</span></td>
                                <td>${r.gender || 'All'}</td>
                                <td style="text-align: center; font-weight: 700;">${r.headcount || 1}</td>
                                <td style="text-align: center; color: #dc2626;">${r.doses_wasted || 0}</td>
                                <td><small>${r.stock_source || 'govt_epi'}</small></td>
                                <td><code>${r.batch_number || 'N/A'}</code></td>
                                <td>${r.expiry_date || '-'}</td>
                            </tr>
                        `);
                    });
                } else {
                    $tbody.html('<tr><td colspan="9" class="text-center py-3 text-danger">Could not load session details.</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="9" class="text-center py-3 text-danger">Network error loading session breakdown.</td></tr>');
            }
        });
    };

    /**
     * Export Sessions to CSV
     */
    OutreachImmunization.exportCsv = function() {
        if (!_currentReportData || !_currentReportData.sessions || _currentReportData.sessions.length === 0) {
            toastr.warning('No sessions data to export.');
            return;
        }

        var csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Date,Settlement,Session ID,Stock Source,Carrier,VVM,Vaccinator,Doses,Wasted\n";

        _currentReportData.sessions.forEach(function(s) {
            var row = [
                s.date || '',
                '"' + (s.location || '').replace(/"/g, '""') + '"',
                s.session_id || '',
                s.stock_source || '',
                '"' + (s.cold_chain_carrier || '').replace(/"/g, '""') + '"',
                s.vvm_stage || '',
                '"' + (s.vaccinator || '').replace(/"/g, '""') + '"',
                s.total_doses || 0,
                s.total_wasted || 0
            ].join(',');
            csvContent += row + "\n";
        });

        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "outreach_immunization_sessions_" + new Date().toISOString().split('T')[0] + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    /**
     * Bind DOM Events
     */
    OutreachImmunization.bindEvents = function() {
        // Stepper Navigation
        $('#btn-outreach-next-step').on('click', function() {
            if (_currentStep === 1) {
                if (OutreachImmunization.validateStep1()) {
                    OutreachImmunization.goToStep(2);
                }
            } else if (_currentStep === 2) {
                if (OutreachImmunization.validateStep2()) {
                    OutreachImmunization.goToStep(3);
                }
            }
        });

        $('#btn-outreach-prev-step').on('click', function() {
            if (_currentStep > 1) {
                OutreachImmunization.goToStep(_currentStep - 1);
            }
        });

        // Click directly on stepper nodes if validated
        $('.stepper-step').on('click', function() {
            var targetStep = parseInt($(this).data('step'), 10);
            if (targetStep === 1) {
                OutreachImmunization.goToStep(1);
            } else if (targetStep === 2) {
                if (OutreachImmunization.validateStep1()) {
                    OutreachImmunization.goToStep(2);
                }
            } else if (targetStep === 3) {
                if (OutreachImmunization.validateStep1() && OutreachImmunization.validateStep2()) {
                    OutreachImmunization.goToStep(3);
                }
            }
        });

        // Stock Source Choice Change
        $(document).on('change', 'input[name="outreach_stock_source"]', function() {
            var val = $(this).val();
            $('.stock-source-choice').removeClass('selected');
            $(this).closest('.stock-source-choice').addClass('selected');

            if (val === 'hospital_store') {
                $('#hospital-store-details-panel').removeClass('d-none');
                $('#partner-details-panel').addClass('d-none');
                var curStoreId = $('#outreach-session-store').val();
                if ((!_storeInventory.rawProducts || _storeInventory.rawProducts.length === 0) && curStoreId) {
                    OutreachImmunization.loadStoreInventory(curStoreId);
                } else {
                    OutreachImmunization.refreshAllStockConnectors();
                }
            } else if (val === 'donor_partner') {
                $('#partner-details-panel').removeClass('d-none');
                $('#hospital-store-details-panel').addClass('d-none');
                OutreachImmunization.refreshAllStockConnectors();
            } else {
                $('#hospital-store-details-panel').addClass('d-none');
                $('#partner-details-panel').addClass('d-none');
                OutreachImmunization.refreshAllStockConnectors();
            }

            var stockSourceLabels = {
                'govt_epi': 'Govt Free EPI',
                'hospital_store': 'Hospital Store',
                'donor_partner': 'Partner Supply',
                'outbreak_reserve': 'Outbreak Reserve'
            };
            $('#sidebar-stock-source-label').text(stockSourceLabels[val] || 'Govt Free EPI');
        });

        // Store Change
        $(document).on('change', '#outreach-session-store', function() {
            var storeId = $(this).val();
            OutreachImmunization.loadStoreInventory(storeId);
        });

        // Global Auto-Deduct Toggle
        $(document).on('change', '#outreach-auto-deduct-stock', function() {
            var isChecked = $(this).is(':checked');
            $('.row-deduct-check').prop('checked', isChecked).trigger('change');
            OutreachImmunization.updateStockSidebar();
        });

        // Batch Dropdown Change
        $(document).on('change', '.stock-batch-dropdown', function() {
            var $sel = $(this);
            var $row = $sel.closest('tr.outreach-row');
            var $opt = $sel.find('option:selected');
            var batchId = $sel.val();
            var batchNum = $opt.data('batch-num') || '';
            var expiry = $opt.data('expiry') || '';
            $row.find('.row-batch-id').val(batchId);
            $row.find('.row-batch-number').val(batchNum);
            $row.find('.row-expiry-date').val(expiry);
        });

        // Per-row Deduct Checkbox
        $(document).on('change', '.row-deduct-check', function() {
            var rowIdx = $(this).data('row-idx');
            var isChecked = $(this).is(':checked');
            _rowStockOverrides[rowIdx] = _rowStockOverrides[rowIdx] || {};
            _rowStockOverrides[rowIdx].auto_deduct = isChecked;
            var $row = $(this).closest('tr.outreach-row');
            $row.find('.row-auto-deduct').val(isChecked ? '1' : '0');

            var $toggle = $(this).closest('.stock-deduct-toggle');
            if (isChecked) {
                $toggle.addClass('is-checked');
                $toggle.find('.stock-deduct-label').text('Deduct');
            } else {
                $toggle.removeClass('is-checked');
                $toggle.find('.stock-deduct-label').text('Skip');
            }

            OutreachImmunization.renderRowStockConnector($row);
            OutreachImmunization.updateStockSidebar();
        });

        // Row Vaccine Name Change (Re-run auto matching)
        $(document).on('input change', '.row-vaccine-name', function() {
            var $row = $(this).closest('tr.outreach-row');
            OutreachImmunization.renderRowStockConnector($row);
            OutreachImmunization.updateStockSidebar();
        });

        // Open Product Picker Modal
        $(document).on('click', '.btn-open-picker', function(e) {
            e.preventDefault();
            var rowIdx = $(this).data('row-idx');
            OutreachImmunization.openProductPicker(rowIdx);
        });

        // Unlink Product Directly from Antigen Row
        $(document).on('click', '.btn-unlink-row', function(e) {
            e.preventDefault();
            var rowIdx = $(this).data('row-idx');
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + rowIdx + '"]');
            var vacName = $row.find('.row-vaccine-name').val() || 'Antigen';

            _rowStockOverrides[rowIdx] = _rowStockOverrides[rowIdx] || {};
            _rowStockOverrides[rowIdx].product_id = null;

            $row.find('.row-product-id').val('');
            $row.find('.row-batch-id').val('');
            $row.find('.row-batch-number').val('');
            $row.find('.row-expiry-date').val('');

            OutreachImmunization.renderRowStockConnector($row);
            OutreachImmunization.updateTotals();
            OutreachImmunization.updateStockSidebar();
            toastr.info('Unlinked product from ' + vacName);
        });

        // Filter Product Picker Search with Live Server Query
        $(document).on('input', '#picker-product-search', function() {
            var term = $(this).val();
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + _activePickerRowIndex + '"]');
            var curProdId = $row ? $row.find('.row-product-id').val() : null;

            // 1. Instant local filter
            OutreachImmunization.renderPickerTable(curProdId, term, _activePickerFilter);

            // 2. Debounce live server search for any product in hospital catalogue
            if (_pickerSearchTimer) {
                clearTimeout(_pickerSearchTimer);
            }

            var storeId = $('#outreach-session-store').val();
            if (!storeId || term.trim().length < 2) {
                $('#picker-search-spinner').addClass('d-none');
                return;
            }

            $('#picker-search-spinner').removeClass('d-none');
            _pickerSearchTimer = setTimeout(function() {
                $.ajax({
                    url: getOutreachEndpoint('store-inventory'),
                    type: 'GET',
                    data: {
                        store_id: storeId,
                        q: term.trim()
                    },
                    dataType: 'json',
                    success: function(resp) {
                        $('#picker-search-spinner').addClass('d-none');
                        if (resp && resp.success && resp.products) {
                            var existingIds = {};
                            (_storeInventory.rawProducts || []).forEach(function(p) {
                                existingIds[p.id] = true;
                            });

                            resp.products.forEach(function(np) {
                                _storeInventory.products[np.id] = np;
                                if (!existingIds[np.id]) {
                                    _storeInventory.rawProducts.push(np);
                                    existingIds[np.id] = true;
                                } else {
                                    for (var i = 0; i < _storeInventory.rawProducts.length; i++) {
                                        if (_storeInventory.rawProducts[i].id === np.id) {
                                            _storeInventory.rawProducts[i] = np;
                                            break;
                                        }
                                    }
                                }
                            });

                            OutreachImmunization.renderPickerTable(curProdId, term, _activePickerFilter);
                        }
                    },
                    error: function() {
                        $('#picker-search-spinner').addClass('d-none');
                    }
                });
            }, 300);
        });

        // Clear Search in Picker
        $(document).on('click', '#picker-search-clear-btn', function() {
            $('#picker-product-search').val('');
            $('#picker-search-spinner').addClass('d-none');
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + _activePickerRowIndex + '"]');
            var curProdId = $row ? $row.find('.row-product-id').val() : null;
            OutreachImmunization.renderPickerTable(curProdId, '', _activePickerFilter);
        });

        // Quick Filter Chips in Picker Modal
        $(document).on('click', '.picker-filter-chip', function() {
            $('.picker-filter-chip').removeClass('active');
            $(this).addClass('active');
            _activePickerFilter = $(this).data('filter') || 'all';
            var term = $('#picker-product-search').val();
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + _activePickerRowIndex + '"]');
            var curProdId = $row ? $row.find('.row-product-id').val() : null;
            OutreachImmunization.renderPickerTable(curProdId, term, _activePickerFilter);
        });

        // Select Product from Picker
        $(document).on('click', '.btn-select-picker-product', function(e) {
            e.preventDefault();
            var prodId = $(this).data('product-id');
            if (_activePickerRowIndex !== null) {
                _rowStockOverrides[_activePickerRowIndex] = _rowStockOverrides[_activePickerRowIndex] || {};
                _rowStockOverrides[_activePickerRowIndex].product_id = prodId;

                var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + _activePickerRowIndex + '"]');
                OutreachImmunization.renderRowStockConnector($row);
                OutreachImmunization.updateRowStockMath($row);
                OutreachImmunization.updateTotals();
                OutreachImmunization.updateStockSidebar();
                $('#outreachProductPickerModal').modal('hide');
                var p = _storeInventory.products[prodId];
                toastr.info('Linked ' + (p ? p.name : 'product') + ' to antigen row.');
            }
        });

        // Unlink Product from Picker
        $(document).on('click', '#picker-unlink-btn', function(e) {
            e.preventDefault();
            if (_activePickerRowIndex !== null) {
                _rowStockOverrides[_activePickerRowIndex] = _rowStockOverrides[_activePickerRowIndex] || {};
                _rowStockOverrides[_activePickerRowIndex].product_id = null;

                var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + _activePickerRowIndex + '"]');
                $row.find('.row-product-id').val('');
                $row.find('.row-batch-id').val('');
                $row.find('.row-batch-number').val('');
                $row.find('.row-expiry-date').val('');
                OutreachImmunization.renderRowStockConnector($row);
                OutreachImmunization.updateTotals();
                OutreachImmunization.updateStockSidebar();
                $('#outreachProductPickerModal').modal('hide');
                toastr.warning('Unlinked product from antigen row.');
            }
        });

        // VVM Status Click
        $(document).on('click', '.vvm-pill', function() {
            $('.vvm-pill').removeClass('active');
            $(this).addClass('active');
            var vvm = $(this).data('vvm');
            $('#outreach-vvm-stage').val(vvm);
            $('#sidebar-vvm-label').text(vvm);
        });

        // Cold Box ID input update
        $(document).on('input', '#outreach-carrier-id', function() {
            $('#sidebar-carrier-label').text($(this).val() || 'Cold Box #1');
        });

        // Stepper Counter Buttons in Matrix Table
        $(document).on('click', '.btn-step-plus', function() {
            var $input = $(this).closest('tr').find('.row-headcount');
            $input.val((parseInt($input.val(), 10) || 0) + 1);
            OutreachImmunization.updateTotals();
        });

        $(document).on('click', '.btn-step-minus', function() {
            var $input = $(this).closest('tr').find('.row-headcount');
            var cur = parseInt($input.val(), 10) || 0;
            if (cur > 0) {
                $input.val(cur - 1);
                OutreachImmunization.updateTotals();
            }
        });

        $(document).on('click', '.btn-step-five', function() {
            var $input = $(this).closest('tr').find('.row-headcount');
            $input.val((parseInt($input.val(), 10) || 0) + 5);
            OutreachImmunization.updateTotals();
        });

        $(document).on('click', '.btn-step-ten', function() {
            var $input = $(this).closest('tr').find('.row-headcount');
            $input.val((parseInt($input.val(), 10) || 0) + 10);
            OutreachImmunization.updateTotals();
        });

        $(document).on('input', '.row-headcount, .row-wasted', function() {
            OutreachImmunization.updateTotals();
        });

        // Track previous dose value before changing
        $(document).on('focusin mousedown', '.row-dose', function() {
            var currentVal = $(this).val();
            if (currentVal && currentVal !== '__custom__') {
                $(this).data('prev-dose', currentVal);
            }
        });

        // In-row Dose select change
        $(document).on('change', '.row-dose', function() {
            var $sel = $(this);
            var val = $sel.val();
            var $row = $sel.closest('tr.outreach-row');
            var prevDose = $sel.data('prev-dose') || 'Dose 1';

            if (val === '__custom__') {
                var $modal = $('#outreachTallyModal');
                var hadTabindex = $modal.attr('tabindex') !== undefined;
                $modal.removeAttr('tabindex');
                $(document).off('focusin.bs.modal');

                Swal.fire({
                    title: '<div class="fw-bold fs-6"><i class="mdi mdi-needle text-primary me-1"></i> Enter Custom Dose</div>',
                    html: '<p class="text-muted small mb-3">Specify the clinical dose label for this tally entry (e.g. <em>Dose 4, Booster 2, Annual, Fractional Dose</em>):</p>',
                    input: 'text',
                    inputValue: '',
                    inputPlaceholder: 'e.g. Dose 4, Booster 2, Annual',
                    showCancelButton: true,
                    confirmButtonText: '<i class="mdi mdi-check"></i> Set Dose',
                    cancelButtonText: '<i class="mdi mdi-close"></i> Cancel',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                    allowOutsideClick: false,
                    returnFocus: false,
                    customClass: {
                        popup: 'shadow-lg border-0',
                        input: 'form-control text-center py-2 fs-6'
                    },
                    didOpen: function() {
                        $(document).off('focusin.bs.modal');
                        setTimeout(function() {
                            var input = Swal.getInput();
                            if (input) {
                                input.focus();
                                input.select();
                            }
                        }, 50);
                    },
                    didClose: function() {
                        if (hadTabindex) {
                            $modal.attr('tabindex', '-1');
                        }
                    },
                    inputValidator: function(v) {
                        if (!v || !v.trim()) {
                            return 'Dose label cannot be empty';
                        }
                    }
                }).then(function(res) {
                    if (res.isConfirmed && res.value) {
                        var customVal = res.value.trim();
                        var $existing = $sel.find('option').filter(function() {
                            return $(this).val().toLowerCase() === customVal.toLowerCase();
                        });
                        if ($existing.length) {
                            $existing.prop('selected', true);
                            $sel.val($existing.val());
                        } else {
                            $sel.prepend('<option value="' + customVal + '" selected>' + customVal + '</option>');
                            $sel.val(customVal);
                        }
                        $sel.data('prev-dose', customVal);
                        updateDoseNum($row, customVal);
                    } else {
                        $sel.val(prevDose);
                        updateDoseNum($row, prevDose);
                    }
                    setTimeout(function() {
                        $sel.focus();
                    }, 50);
                });
                return;
            }
            $sel.data('prev-dose', val);
            updateDoseNum($row, val);
        });

        // In-row Demographic change
        $(document).on('change', '.row-demographic', function() {
            var $sel = $(this);
            var $row = $sel.closest('tr.outreach-row');
            var $opt = $sel.find('option:selected');
            var cat = $opt.data('category') || 'infants';
            $row.attr('data-category', cat);
            OutreachImmunization.updateTotals();
        });

        // In-row Sex change
        $(document).on('change', '.row-sex', function() {
            OutreachImmunization.updateTotals();
        });

        // 1-Click Skip Deduct from Row Deficit Alert
        $(document).on('click', '.btn-skip-deficit', function(e) {
            e.preventDefault();
            var rowIdx = $(this).data('row-idx');
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + rowIdx + '"]');
            var vac = $row.find('.row-vaccine-name').val() || 'Antigen';
            _rowStockOverrides[rowIdx] = _rowStockOverrides[rowIdx] || {};
            _rowStockOverrides[rowIdx].auto_deduct = false;
            $row.find('.row-deduct-check').prop('checked', false);
            $row.find('.stock-deduct-label').text('Skip');
            $row.find('.stock-deduct-toggle').removeClass('is-checked');
            OutreachImmunization.updateRowStockMath($row);
            OutreachImmunization.updateStockSidebar();
            toastr.info('Store deduction skipped for ' + vac + '. Headcount retained for public health report.');
        });

        // 1-Click Cap to Stock from Row Deficit Alert
        $(document).on('click', '.btn-cap-deficit', function(e) {
            e.preventDefault();
            var rowIdx = $(this).data('row-idx');
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + rowIdx + '"]');
            var avail = parseInt($(this).data('stock'), 10) || 0;
            var wasted = parseInt($row.find('.row-wasted').val(), 10) || 0;
            var cappedHeadcount = Math.max(0, avail - wasted);
            $row.find('.row-headcount').val(cappedHeadcount);
            OutreachImmunization.updateRowStockMath($row);
            OutreachImmunization.updateTotals();
            OutreachImmunization.updateStockSidebar();
            toastr.success('Headcount capped to available store stock (' + avail + ').');
        });

        // 1-Click Skip Deduct from Step 3 Ledger Table
        $(document).on('click', '.btn-ledger-skip-deduct', function(e) {
            e.preventDefault();
            var rowIdx = $(this).data('row-idx');
            var $row = $('#outreach-tally-rows tr.outreach-row[data-row-idx="' + rowIdx + '"]');
            _rowStockOverrides[rowIdx] = _rowStockOverrides[rowIdx] || {};
            _rowStockOverrides[rowIdx].auto_deduct = false;
            $row.find('.row-deduct-check').prop('checked', false);
            $row.find('.stock-deduct-label').text('Skip');
            $row.find('.stock-deduct-toggle').removeClass('is-checked');
            OutreachImmunization.updateRowStockMath($row);
            OutreachImmunization.buildStockLedger();
            OutreachImmunization.updateStockSidebar();
            toastr.info('Store deduction skipped for item.');
        });

        // 1-Click Resolve Deficits Modal Trigger
        $(document).on('click', '#btn-sidebar-resolve-deficits, #btn-ledger-resolve-deficits', function(e) {
            e.preventDefault();
            var deficits = OutreachImmunization.findStockDeficits();
            OutreachImmunization.showDeficitResolutionModal(deficits, function() {
                if (_currentStep === 3) {
                    OutreachImmunization.buildStockLedger();
                }
            });
        });

        // Fast Bundles
        $(document).on('click', '.outreach-bundle-chip', function() {
            var bundle = $(this).data('bundle');
            OutreachImmunization.applyBundle(bundle);
        });

        function applyMatrixFilter() {
            var activeFilter = $('.outreach-tab-btn.active').data('filter') || 'all';
            var term = ($('#search-antigen-input').val() || '').toLowerCase().trim();
            var visibleCount = 0;
            var visibleGroups = {};

            if (term) {
                $('#btn-clear-search').removeClass('d-none');
            } else {
                $('#btn-clear-search').addClass('d-none');
            }

            $('#outreach-tally-rows tr.outreach-row').each(function() {
                var $row = $(this);
                var name = ($row.find('.row-vaccine-name').val() || '').toLowerCase();
                var dose = ($row.find('.row-dose').val() || '').toLowerCase();
                var rowCat = $row.data('category');
                var groupKey = $row.data('group');

                var matchesCat = (activeFilter === 'all' || rowCat === activeFilter);
                var matchesTerm = (!term || name.indexOf(term) !== -1 || dose.indexOf(term) !== -1);

                if (matchesCat && matchesTerm) {
                    $row.show();
                    visibleCount++;
                    if (groupKey) {
                        visibleGroups[groupKey] = true;
                    }
                } else {
                    $row.hide();
                }
            });

            // Toggle schedule milestone group headers based on visible child rows
            $('#outreach-tally-rows tr.schedule-group-row').each(function() {
                var $grp = $(this);
                var gKey = $grp.data('group');
                if (visibleGroups[gKey]) {
                    $grp.show();
                } else {
                    $grp.hide();
                }
            });

            $('#outreach-table-row-count').text('Showing ' + visibleCount + ' antigen' + (visibleCount !== 1 ? 's' : ''));
        }

        // Stepper Reset Button (single row reset)
        $(document).on('click', '.btn-step-reset', function() {
            var $row = $(this).closest('tr');
            $row.find('.row-headcount').val(0);
            OutreachImmunization.updateTotals();
        });

        // Delete custom user-added row
        $(document).on('click', '.btn-delete-custom-row', function() {
            var $row = $(this).closest('tr');
            $row.remove();
            OutreachImmunization.updateTotals();
            applyMatrixFilter();
        });

        // Auto-select entire number on focus for instant typing
        $(document).on('focus', '.tally-input, .row-wasted', function() {
            $(this).select();
        });

        // Demographic Filter Tabs
        $(document).on('click', '.outreach-tab-btn', function() {
            $('.outreach-tab-btn').removeClass('active');
            $(this).addClass('active');
            applyMatrixFilter();
        });

        // Antigen Search Filter
        $(document).on('input', '#search-antigen-input', function() {
            applyMatrixFilter();
        });

        $(document).on('click', '#btn-clear-search', function() {
            $('#search-antigen-input').val('');
            applyMatrixFilter();
        });

        // Custom Row
        $('#btn-add-outreach-row').on('click', function() {
            OutreachImmunization.addCustomRow();
            applyMatrixFilter();
        });

        // Clear All Tallies
        $('#btn-clear-outreach-tallies').on('click', function() {
            if (confirm('Are you sure you want to reset all tally counts to zero?')) {
                $('.row-headcount, .row-wasted').val(0);
                $('.outreach-row').removeClass('row-tallied');
                OutreachImmunization.updateTotals();
            }
        });

        // Save Outreach Session
        $('#btn-save-outreach-tally').on('click', function() {
            OutreachImmunization.submitSession();
        });

        // Cancel / Close buttons inside tally modal
        $(document).on('click', '#btn-outreach-cancel, #outreachTallyModal .btn-close', function(e) {
            e.preventDefault();
            OutreachImmunization.closeTallyModal();
        });

        // Close button inside reports modal
        $(document).on('click', '#outreachReportsModal .btn-close', function(e) {
            e.preventDefault();
            OutreachImmunization.closeReportsModal();
        });

        // Report Tab Navigation
        $(document).on('click', '.report-tab-btn', function() {
            $('.report-tab-btn').removeClass('active');
            $(this).addClass('active');

            var tab = $(this).data('tab');
            $('.report-tab-pane').addClass('d-none');
            $('#pane-report-' + tab).removeClass('d-none');
        });

        // Reports Filter Buttons
        $('#btn-apply-outreach-report-filter').on('click', function() {
            OutreachImmunization.loadReports();
        });

        $('#btn-reset-outreach-report-filter').on('click', function() {
            $('#report-filter-from').val('');
            $('#report-filter-to').val('');
            $('#report-filter-location').val('');
            $('#report-filter-stock-source').val('all');
            OutreachImmunization.loadReports();
        });

        // View Session Details in Reports
        $(document).on('click', '.btn-view-session-details', function() {
            var sId = $(this).data('session-id');
            OutreachImmunization.viewSessionDetails(sId);
        });

        $('#btn-close-session-detail').on('click', function() {
            $('#outreach-session-detail-container').addClass('d-none');
        });

        // Print Summary in Reports
        $('#btn-print-outreach-report-summary').on('click', function() {
            var from = $('#report-filter-from').val() || '';
            var to = $('#report-filter-to').val() || '';
            var loc = $('#report-filter-location').val() || '';
            var src = $('#report-filter-stock-source').val() || '';

            var url = getOutreachEndpoint('print') + '?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to) + '&location=' + encodeURIComponent(loc) + '&stock_source=' + encodeURIComponent(src);
            window.open(url, '_blank');
        });

        // Export CSV
        $('#btn-export-outreach-csv').on('click', function() {
            OutreachImmunization.exportCsv();
        });

        // Quick Action Buttons to open modals (delegated so it works reliably across workbenches)
        $(document).on('click', '#btn-outreach-immunization, .btn-open-outreach-tally', function(e) {
            e.preventDefault();
            OutreachImmunization.openTallyModal();
        });

        $(document).on('click', '#btn-outreach-reports, .btn-open-outreach-reports', function(e) {
            e.preventDefault();
            OutreachImmunization.openReportsModal();
        });

        // Modal Lifecycle Triggers
        $('#outreachTallyModal').on('show.bs.modal', function() {
            OutreachImmunization.resetForm();
        });

        $('#outreachTallyModal').on('shown.bs.modal', function() {
            $(document).off('focusin.bs.modal');
        });

        $('#outreachReportsModal').on('show.bs.modal', function() {
            OutreachImmunization.loadReports();
        });

        // Cleanup any lingering backdrops when modals close
        $('#outreachTallyModal, #outreachReportsModal').on('hidden.bs.modal', function () {
            setTimeout(function() {
                if (!$('.modal.show').length) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css({ overflow: '', 'padding-right': '' });
                }
            }, 100);
        });
    };

    OutreachImmunization.openTallyModal = function() {
        OutreachImmunization.resetForm();
        var $modal = $('#outreachTallyModal');
        if (!$modal.hasClass('show')) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var instance = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance($modal[0]) : (bootstrap.Modal.getInstance($modal[0]) || new bootstrap.Modal($modal[0]));
                    instance.show();
                } else {
                    $modal.modal('show');
                }
            } catch(e) {
                $modal.modal('show');
            }
        }
    };

    OutreachImmunization.closeTallyModal = function() {
        var $modal = $('#outreachTallyModal');
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var instance = bootstrap.Modal.getInstance($modal[0]);
                if (instance) {
                    instance.hide();
                } else {
                    $modal.modal('hide');
                }
            } else {
                $modal.modal('hide');
            }
        } catch(e) {
            $modal.modal('hide');
        }

        setTimeout(function() {
            if (!$('#outreachTallyModal').hasClass('show') && !$('#outreachReportsModal').hasClass('show')) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({ overflow: '', 'padding-right': '' });
            }
        }, 200);
    };

    OutreachImmunization.openReportsModal = function() {
        var $modal = $('#outreachReportsModal');
        if (!$modal.hasClass('show')) {
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var instance = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance($modal[0]) : (bootstrap.Modal.getInstance($modal[0]) || new bootstrap.Modal($modal[0]));
                    instance.show();
                } else {
                    $modal.modal('show');
                }
            } catch(e) {
                $modal.modal('show');
            }
        }
        OutreachImmunization.loadReports();
    };

    OutreachImmunization.closeReportsModal = function() {
        var $modal = $('#outreachReportsModal');
        try {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var instance = bootstrap.Modal.getInstance($modal[0]);
                if (instance) {
                    instance.hide();
                } else {
                    $modal.modal('hide');
                }
            } else {
                $modal.modal('hide');
            }
        } catch(e) {
            $modal.modal('hide');
        }

        setTimeout(function() {
            if (!$('#outreachTallyModal').hasClass('show') && !$('#outreachReportsModal').hasClass('show')) {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({ overflow: '', 'padding-right': '' });
            }
        }, 200);
    };

    window.OutreachImmunization = OutreachImmunization;

    $(document).ready(function() {
        OutreachImmunization.init();
    });

})(window, jQuery);
