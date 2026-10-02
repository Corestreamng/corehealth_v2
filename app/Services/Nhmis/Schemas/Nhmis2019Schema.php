<?php

namespace App\Services\Nhmis\Schemas;

class Nhmis2019Schema
{
    public function getVersion(): string
    {
        return 'v2019';
    }

    public function getTitle(): string
    {
        return 'NATIONAL HEALTH MANAGEMENT INFORMATION SYSTEM HEALTH FACILITY MONTHLY SUMMARY FORM (VERSION 2019)';
    }

    public function getCode(): string
    {
        return 'NHMIS/HF/MSF';
    }

    public function getDescription(): string
    {
        return 'Official 2019 revision of Nigeria NHMIS Monthly Summary Form incorporating updated RI antigens, TD, ANC contacts, GBV, and NCDs.';
    }

    /**
     * Standard age-sex column definition used across Attendance, Inpatient, and Mortality
     */
    private function getStandardAgeSexColumns(): array
    {
        return [
            'm_0_28d' => ['label' => '0-28 days', 'group' => 'Male'],
            'm_29d_11m' => ['label' => '29d-11 mths', 'group' => 'Male'],
            'm_12_59m' => ['label' => '12-59 mths', 'group' => 'Male'],
            'm_5_9y' => ['label' => '5-9 yrs', 'group' => 'Male'],
            'm_10_19y' => ['label' => '10-19 yrs', 'group' => 'Male'],
            'm_ge_20y' => ['label' => '≥20 yrs', 'group' => 'Male'],
            'f_0_28d' => ['label' => '0-28 days', 'group' => 'Female'],
            'f_29d_11m' => ['label' => '29d-11 mths', 'group' => 'Female'],
            'f_12_59m' => ['label' => '12-59 mths', 'group' => 'Female'],
            'f_5_9y' => ['label' => '5-9 yrs', 'group' => 'Female'],
            'f_10_19y' => ['label' => '10-19 yrs', 'group' => 'Female'],
            'f_ge_20y' => ['label' => '≥20 yrs', 'group' => 'Female'],
            'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
        ];
    }

    private function getMaleFemaleTotalColumns(): array
    {
        return [
            'male' => ['label' => 'Male'],
            'female' => ['label' => 'Female'],
            'total' => ['label' => 'Total', 'is_total' => true],
        ];
    }

    private function getTotalOnlyColumn(): array
    {
        return [
            'total' => ['label' => 'Total', 'is_total' => true],
        ];
    }

    /**
     * Get all 5 pages and sections of the form
     */
    public function getPages(): array
    {
        return [
            $this->getPage1(),
            $this->getPage2(),
            $this->getPage3(),
            $this->getPage4(),
            $this->getPage5(),
        ];
    }

    /**
     * Page 1: Attendance, Inpatient, Mortality, Antenatal Care
     */
    private function getPage1(): array
    {
        $ageSexCols = $this->getStandardAgeSexColumns();
        $totalOnly = $this->getTotalOnlyColumn();

        return [
            'id' => 'page_1',
            'number' => 1,
            'title' => 'Attendance, Inpatient Care, Mortality & Antenatal Care',
            'sections' => [
                [
                    'id' => 'sec_attendance',
                    'title' => 'Health Facility Attendance',
                    'columns' => $ageSexCols,
                    'rows' => [
                        ['id' => 'row_1', 'number' => 1, 'label' => 'General Attendance'],
                        ['id' => 'row_2', 'number' => 2, 'label' => 'Out-patient Attendance'],
                    ],
                ],
                [
                    'id' => 'sec_inpatient',
                    'title' => 'Inpatient Care (IPC)',
                    'columns' => $ageSexCols,
                    'rows' => [
                        ['id' => 'row_3', 'number' => 3, 'label' => 'Patients admitted'],
                        ['id' => 'row_4', 'number' => 4, 'label' => 'Inpatient discharges'],
                    ],
                ],
                [
                    'id' => 'sec_mortality',
                    'title' => 'Mortality (Deaths)',
                    'columns' => $ageSexCols,
                    'rows' => [
                        ['id' => 'row_5', 'number' => 5, 'label' => 'Deaths among individuals (disaggregated by age)'],
                    ],
                ],
                [
                    'id' => 'sec_maternal_mortality',
                    'title' => 'Maternal Mortality',
                    'columns' => [
                        'age_10_19y' => ['label' => '10-19 yrs'],
                        'age_ge_20y' => ['label' => '≥20 yrs'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_6', 'number' => 6, 'label' => 'Deaths of women related to pregnancy (maternal deaths)'],
                    ],
                ],
                [
                    'id' => 'sec_causes_death_maternal',
                    'title' => 'Causes of Deaths: Confirmed Maternal Deaths',
                    'columns' => [
                        'pph' => ['label' => 'Post-partum haemorrhage'],
                        'sepsis' => ['label' => 'Sepsis'],
                        'obstructed_labour' => ['label' => 'Obstructed labour'],
                        'abortion' => ['label' => 'Abortion'],
                        'malaria' => ['label' => 'Malaria'],
                        'anaemia' => ['label' => 'Anaemia'],
                        'hiv' => ['label' => 'HIV'],
                        'other' => ['label' => 'Other'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_7', 'number' => 7, 'label' => 'Confirmed maternal deaths due to:'],
                    ],
                ],
                [
                    'id' => 'sec_causes_death_neonatal',
                    'title' => 'Causes of Deaths: Confirmed Neonatal Deaths (< 28 days)',
                    'columns' => [
                        'prematurity' => ['label' => 'Prematurity'],
                        'neonatal_tetanus' => ['label' => 'Neonatal Tetanus'],
                        'congenital_malformation' => ['label' => 'Congenital Malformation'],
                        'other' => ['label' => 'Other'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_8', 'number' => 8, 'label' => 'Confirmed neonatal deaths due to:'],
                    ],
                ],
                [
                    'id' => 'sec_causes_death_u5',
                    'title' => 'Causes of Deaths: Confirmed Under 5 Deaths',
                    'columns' => [
                        'malaria' => ['label' => 'Malaria'],
                        'pneumonia' => ['label' => 'Pneumonia'],
                        'malnutrition' => ['label' => 'Malnutrition'],
                        'other' => ['label' => 'Other'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_9', 'number' => 9, 'label' => 'Confirmed under 5 deaths due to:'],
                    ],
                ],
                [
                    'id' => 'sec_anc_attendance',
                    'title' => 'Maternal Health: Ante-Natal Care Attendance by Age',
                    'columns' => [
                        'age_10_14y' => ['label' => '10-14 yrs'],
                        'age_15_19y' => ['label' => '15-19 yrs'],
                        'age_20_35y' => ['label' => '20-35 yrs'],
                        'age_35_49y' => ['label' => '35-49 yrs'],
                        'age_ge_50y' => ['label' => '≥50 yrs'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_10', 'number' => 10, 'label' => 'Antenatal attendance by pregnant women'],
                    ],
                ],
                [
                    'id' => 'sec_anc_first_visit',
                    'title' => 'Maternal Health: Ante-Natal First Visit Gestational Age',
                    'columns' => [
                        'ga_lt_20wks' => ['label' => 'Gestational age < 20 weeks'],
                        'ga_ge_20wks' => ['label' => 'Gestational age ≥ 20 weeks'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_11', 'number' => 11, 'label' => 'Antenatal attendance first visit'],
                    ],
                ],
                [
                    'id' => 'sec_anc_services',
                    'title' => 'Maternal Health: ANC Services, Tests & Preventive Therapy',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_12', 'number' => 12, 'label' => 'Pregnant women that attended antenatal clinic for 4th visit'],
                        ['id' => 'row_13', 'number' => 13, 'label' => 'Pregnant women that attended antenatal clinic for 8th visit'],
                        ['id' => 'row_14', 'number' => 14, 'label' => 'Pregnant women counseled on Female Genital Mutilation (FGM)'],
                        ['id' => 'row_15', 'number' => 15, 'label' => 'Pregnant women counseled on Family Planning (FP)'],
                        ['id' => 'row_16', 'number' => 16, 'label' => 'Pregnant women counseled on Maternal Nutrition during ANC'],
                        ['id' => 'row_17', 'number' => 17, 'label' => 'ANC syphilis test done'],
                        ['id' => 'row_18', 'number' => 18, 'label' => 'ANC syphilis test positive'],
                        ['id' => 'row_19', 'number' => 19, 'label' => 'ANC syphilis case treated'],
                        ['id' => 'row_20', 'number' => 20, 'label' => 'ANC Hepatitis B test done'],
                        ['id' => 'row_21', 'number' => 21, 'label' => 'ANC Hepatitis B test positive'],
                        ['id' => 'row_22', 'number' => 22, 'label' => 'ANC Hepatitis B case referred for treatment'],
                        ['id' => 'row_23', 'number' => 23, 'label' => 'ANC Hepatitis C test done'],
                        ['id' => 'row_24', 'number' => 24, 'label' => 'ANC Hepatitis C test positive'],
                        ['id' => 'row_25', 'number' => 25, 'label' => 'ANC Hepatitis C case referred for treatment'],
                        ['id' => 'row_26', 'number' => 26, 'label' => 'Pregnant women who received malaria IPT1'],
                        ['id' => 'row_27', 'number' => 27, 'label' => 'Pregnant women who received malaria IPT2'],
                        ['id' => 'row_28', 'number' => 28, 'label' => 'Pregnant women who received malaria IPT3'],
                        ['id' => 'row_29', 'number' => 29, 'label' => 'Pregnant women who received malaria IPT>=4'],
                        ['id' => 'row_30', 'number' => 30, 'label' => 'Pregnant women who received LLIN'],
                        ['id' => 'row_31', 'number' => 31, 'label' => 'Pregnant women who received Haematinics (Iron and Folic Acid supplements)'],
                        ['id' => 'row_32', 'number' => 32, 'label' => 'Pregnant women with severe anaemia'],
                        ['id' => 'row_33', 'number' => 33, 'label' => 'Pregnant women with proteinuria'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Page 2: Labour & Delivery, PNC, Newborn Health, Immunization (TD & Antigens)
     */
    private function getPage2(): array
    {
        $mfTotal = $this->getMaleFemaleTotalColumns();
        $totalOnly = $this->getTotalOnlyColumn();

        return [
            'id' => 'page_2',
            'number' => 2,
            'title' => 'Labour & Delivery, Postnatal Care, Newborn Health & Immunization',
            'sections' => [
                [
                    'id' => 'sec_labour_care_seeking',
                    'title' => 'Labour & Delivery: Care-seeking & Transportation',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_34', 'number' => 34, 'label' => 'Decision in seeking care < 24 hours'],
                        ['id' => 'row_35', 'number' => 35, 'label' => 'Transportation in'],
                    ],
                ],
                [
                    'id' => 'sec_labour_deliveries',
                    'title' => 'Labour & Delivery: Delivery Methods',
                    'columns' => [
                        'svd' => ['label' => 'Spontaneous Vaginal Delivery (SVD)'],
                        'assisted' => ['label' => 'Assisted'],
                        'c_section' => ['label' => 'Caesarean Section'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_36', 'number' => 36, 'label' => 'Deliveries'],
                    ],
                ],
                [
                    'id' => 'sec_labour_events',
                    'title' => 'Labour & Delivery: Complications & Delivery Management',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_37', 'number' => 37, 'label' => 'Preterm birth (before 37 weeks GA)'],
                        ['id' => 'row_38', 'number' => 38, 'label' => 'Deliveries with complications - mother only'],
                        ['id' => 'row_39', 'number' => 39, 'label' => 'Deliveries by adolescent mother (aged 10-19 years)'],
                        ['id' => 'row_40', 'number' => 40, 'label' => 'Deliveries monitored using a partograph'],
                        ['id' => 'row_41', 'number' => 41, 'label' => 'Deliveries taken by skilled birth attendants (SBA)'],
                    ],
                ],
                [
                    'id' => 'sec_labour_uterotonics',
                    'title' => 'Labour & Delivery: Uterotonics in Third Stage',
                    'columns' => [
                        'oxytocin' => ['label' => 'Oxytocin'],
                        'misoprostol' => ['label' => 'Misoprostol'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_42', 'number' => 42, 'label' => 'Women giving birth who received Uterotonics in third stage of labour'],
                    ],
                ],
                [
                    'id' => 'sec_labour_abortions',
                    'title' => 'Labour & Delivery: Eclampsia, Abortions & Post-Abortion Care',
                    'columns' => [
                        'induced' => ['label' => 'Induced'],
                        'spontaneous' => ['label' => 'Spontaneous'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_44', 'number' => 44, 'label' => 'Abortions'],
                    ],
                ],
                [
                    'id' => 'sec_labour_pac_services',
                    'title' => 'Labour & Delivery: Post Abortion Care & Eclampsia',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_43', 'number' => 43, 'label' => 'Women admitted with Eclampsia who received MgSO4'],
                        ['id' => 'row_45', 'number' => 45, 'label' => 'Women who received Post Abortion Care (PAC)'],
                        ['id' => 'row_46', 'number' => 46, 'label' => 'Women admitted for complications of unsafe abortion'],
                    ],
                ],
                [
                    'id' => 'sec_pnc',
                    'title' => 'Maternal Health: Post-Natal Care (PNC) Visits',
                    'columns' => [
                        'mother_1d' => ['label' => '1 day', 'group' => 'Mothers'],
                        'mother_2_3d' => ['label' => '2-3 days', 'group' => 'Mothers'],
                        'mother_4_7d' => ['label' => '4-7 days', 'group' => 'Mothers'],
                        'mother_gt_7d' => ['label' => '>7 days', 'group' => 'Mothers'],
                        'baby_1d' => ['label' => '1 day', 'group' => 'Newborns'],
                        'baby_2_3d' => ['label' => '2-3 days', 'group' => 'Newborns'],
                        'baby_4_7d' => ['label' => '4-7 days', 'group' => 'Newborns'],
                        'baby_gt_7d' => ['label' => '>7 days', 'group' => 'Newborns'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_47', 'number' => 47, 'label' => 'Postnatal clinic visits'],
                    ],
                ],
                [
                    'id' => 'sec_newborn_live_births',
                    'title' => 'Newborn Health: Live Births by Weight and Sex',
                    'columns' => [
                        'm_lt_2_5kg' => ['label' => '<2.5kg', 'group' => 'Male'],
                        'm_ge_2_5kg' => ['label' => '≥2.5kg', 'group' => 'Male'],
                        'f_lt_2_5kg' => ['label' => '<2.5kg', 'group' => 'Female'],
                        'f_ge_2_5kg' => ['label' => '≥2.5kg', 'group' => 'Female'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_48', 'number' => 48, 'label' => 'Live Births'],
                    ],
                ],
                [
                    'id' => 'sec_newborn_stillbirths',
                    'title' => 'Newborn Health: Still Births & HIV Exposures',
                    'columns' => [
                        'macerated_msb' => ['label' => 'Macerated (MSB)'],
                        'fresh_fsb' => ['label' => 'Fresh Still Births (FSB)'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_50', 'number' => 50, 'label' => 'Still Births'],
                    ],
                ],
                [
                    'id' => 'sec_newborn_hiv',
                    'title' => 'Newborn Health: Live Births by HIV Positive Women',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_49', 'number' => 49, 'label' => 'Live Births by HIV positive women only'],
                    ],
                ],
                [
                    'id' => 'sec_immediate_newborn_care',
                    'title' => 'Newborn Health: Immediate Newborn Care',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_51', 'number' => 51, 'label' => 'Babies whose cords were clamped/cut after 1 minute of birth'],
                        ['id' => 'row_52', 'number' => 52, 'label' => 'Babies for whom 4% Chlorhexidine (CHX) gel is applied to cord at birth'],
                        ['id' => 'row_53', 'number' => 53, 'label' => 'Babies put to breast within 1 hour with skin-to-skin to keep warm'],
                        ['id' => 'row_54', 'number' => 54, 'label' => 'Babies whose temperature were taken at 1 hour of birth'],
                    ],
                ],
                [
                    'id' => 'sec_newborn_complications',
                    'title' => 'Newborn Health: Complications & Asphyxia',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_55', 'number' => 55, 'label' => 'Babies not breathing/ not crying at birth'],
                        ['id' => 'row_56', 'number' => 56, 'label' => 'Babies not breathing/ not crying at birth that were successfully resuscitated'],
                        ['id' => 'row_57', 'number' => 57, 'label' => 'Newborns with danger signs'],
                        ['id' => 'row_58', 'number' => 58, 'label' => 'Newborns with danger signs given first dose of antibiotics and referred'],
                        ['id' => 'row_59', 'number' => 59, 'label' => 'Neonatal tetanus'],
                        ['id' => 'row_60', 'number' => 60, 'label' => 'Neonatal jaundice'],
                    ],
                ],
                [
                    'id' => 'sec_newborn_kmc',
                    'title' => 'Newborn Health: Low Birth Weight & Kangaroo Mother Care (KMC)',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_61', 'number' => 61, 'label' => 'Newborn with low birth weight admitted on KMC'],
                        ['id' => 'row_62', 'number' => 62, 'label' => 'Newborn with low birth weight discharged after KMC'],
                    ],
                ],
                [
                    'id' => 'sec_immunization_td',
                    'title' => 'Immunization: Tetanus Diphtheria (TD) for Women',
                    'columns' => [
                        'td1' => ['label' => 'TD1'],
                        'td2' => ['label' => 'TD2'],
                        'td3' => ['label' => 'TD3'],
                        'td4' => ['label' => 'TD4'],
                        'td5' => ['label' => 'TD5'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_63', 'number' => 63, 'label' => 'TD doses given to pregnant women'],
                        ['id' => 'row_64', 'number' => 64, 'label' => 'TD doses given to non-pregnant women (15-49 years)'],
                    ],
                ],
                [
                    'id' => 'sec_immunization_antigens',
                    'title' => 'Immunization: Routine Antigens Received',
                    'columns' => [
                        'fixed_lt_1y' => ['label' => '<1 yr', 'group' => 'Fixed Session'],
                        'fixed_ge_1y' => ['label' => '≥1 yr', 'group' => 'Fixed Session'],
                        'outreach_lt_1y' => ['label' => '<1 yr', 'group' => 'Outreach Session'],
                        'outreach_ge_1y' => ['label' => '≥1 yr', 'group' => 'Outreach Session'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_65', 'number' => 65, 'label' => 'OPV 0 (given within 2 weeks of birth)'],
                        ['id' => 'row_66', 'number' => 66, 'label' => 'Hepatitis B 0 (given within 24 hours of birth)'],
                        ['id' => 'row_67', 'number' => 67, 'label' => 'BCG (Bacille Calmette-Guérin)'],
                        ['id' => 'row_68', 'number' => 68, 'label' => 'OPV 1'],
                        ['id' => 'row_69', 'number' => 69, 'label' => 'Penta 1 (DTP-HepB-Hib 1)'],
                        ['id' => 'row_70', 'number' => 70, 'label' => 'PCV 1 (Pneumococcal Conjugate Vaccine 1)'],
                        ['id' => 'row_71', 'number' => 71, 'label' => 'Rotavirus 1'],
                        ['id' => 'row_72', 'number' => 72, 'label' => 'OPV 2'],
                        ['id' => 'row_73', 'number' => 73, 'label' => 'Penta 2 (DTP-HepB-Hib 2)'],
                        ['id' => 'row_74', 'number' => 74, 'label' => 'PCV 2'],
                        ['id' => 'row_75', 'number' => 75, 'label' => 'Rotavirus 2'],
                        ['id' => 'row_76', 'number' => 76, 'label' => 'OPV 3'],
                        ['id' => 'row_77', 'number' => 77, 'label' => 'Penta 3 (DTP-HepB-Hib 3)'],
                        ['id' => 'row_78', 'number' => 78, 'label' => 'PCV 3'],
                        ['id' => 'row_79', 'number' => 79, 'label' => 'Rotavirus 3'],
                        ['id' => 'row_80', 'number' => 80, 'label' => 'IPV (Inactivated Polio Vaccine)'],
                        ['id' => 'row_81', 'number' => 81, 'label' => 'Vitamin A (given at 9 months)'],
                        ['id' => 'row_82', 'number' => 82, 'label' => 'Measles 1 (at 9 months)'],
                        ['id' => 'row_83', 'number' => 83, 'label' => 'Fully Immunized Children'],
                        ['id' => 'row_84', 'number' => 84, 'label' => 'Yellow Fever (at 9 months)'],
                        ['id' => 'row_85', 'number' => 85, 'label' => 'Measles 2 (at 15 months)'],
                        ['id' => 'row_86', 'number' => 86, 'label' => 'Men A (Meningitis A conjugate)'],
                        ['id' => 'row_87', 'number' => 87, 'label' => 'HPV (Human Papillomavirus) for girls 9-14 years'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Page 3: AEFI, RI Operations, Birth Registration, Nutrition, SAM, IMCI, Family Planning
     */
    private function getPage3(): array
    {
        $mfTotal = $this->getMaleFemaleTotalColumns();
        $totalOnly = $this->getTotalOnlyColumn();

        return [
            'id' => 'page_3',
            'number' => 3,
            'title' => 'AEFI, RI Strategy, Birth Reg, Growth Monitoring, SAM, IMCI & Family Planning',
            'sections' => [
                [
                    'id' => 'sec_aefi_reported',
                    'title' => 'Immunization: Adverse Events Following Immunization (AEFI)',
                    'columns' => [
                        'non_serious' => ['label' => 'Non-Serious'],
                        'serious' => ['label' => 'Serious'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_88', 'number' => 88, 'label' => 'AEFI cases reported'],
                    ],
                ],
                [
                    'id' => 'sec_aefi_investigated',
                    'title' => 'Immunization: Serious AEFI Investigations & Outcomes',
                    'columns' => [
                        'alive' => ['label' => 'Alive'],
                        'dead' => ['label' => 'Dead'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_90', 'number' => 90, 'label' => 'Outcome of Serious cases of AEFI investigated'],
                    ],
                ],
                [
                    'id' => 'sec_aefi_cases',
                    'title' => 'Immunization: Serious Cases of AEFI Investigated',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_89', 'number' => 89, 'label' => 'Serious cases of AEFI investigated'],
                    ],
                ],
                [
                    'id' => 'sec_ri_operations_sessions',
                    'title' => 'Immunization: Routine Strategy Sessions (Planned vs Conducted)',
                    'columns' => [
                        'planned' => ['label' => 'Planned'],
                        'conducted' => ['label' => 'Conducted'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_92', 'number' => 92, 'label' => 'RI Fixed Sessions'],
                        ['id' => 'row_93', 'number' => 93, 'label' => 'RI Outreach Sessions'],
                    ],
                ],
                [
                    'id' => 'sec_ri_operations_supervision',
                    'title' => 'Immunization: Supportive Supervision Level',
                    'columns' => [
                        'national' => ['label' => 'National'],
                        'state' => ['label' => 'State'],
                        'lga' => ['label' => 'LGA'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_95', 'number' => 95, 'label' => 'Level of Supportive supervision received [1 where applicable]'],
                    ],
                ],
                [
                    'id' => 'sec_ri_operations_indicators',
                    'title' => 'Immunization: Strategy Microplan, Funds & Committee Meetings',
                    'columns' => [
                        'val' => ['label' => 'Value / Amount'],
                    ],
                    'rows' => [
                        ['id' => 'row_91', 'number' => 91, 'label' => 'Facility has updated REW Microplan (1: Yes, 0: No)'],
                        ['id' => 'row_94', 'number' => 94, 'label' => 'Facility Staff received RI supportive supervision (1: Yes, 0: No)'],
                        ['id' => 'row_96', 'number' => 96, 'label' => 'Amount of RI funds received (in ₦)'],
                        ['id' => 'row_97', 'number' => 97, 'label' => 'Village or Ward Development Committee (WDC) meeting conducted (1: Yes, 0: No)'],
                    ],
                ],
                [
                    'id' => 'sec_birth_registration',
                    'title' => 'Birth Registration',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_98', 'number' => 98, 'label' => 'Children under 1 year registered'],
                        ['id' => 'row_99', 'number' => 99, 'label' => 'Birth certificate issued'],
                        ['id' => 'row_100', 'number' => 100, 'label' => 'Birth certificate collected'],
                    ],
                ],
                [
                    'id' => 'sec_growth_monitoring_attendance',
                    'title' => 'Nutrition: Growth Monitoring & Promotion Attendance by Age',
                    'columns' => [
                        'male_new' => ['label' => 'New', 'group' => 'Male'],
                        'male_revisit' => ['label' => 'Revisit', 'group' => 'Male'],
                        'female_new' => ['label' => 'New', 'group' => 'Female'],
                        'female_revisit' => ['label' => 'Revisit', 'group' => 'Female'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_101_0_5m', 'number' => '101a', 'label' => 'Children 0-5 months receiving GMP services'],
                        ['id' => 'row_101_6_23m', 'number' => '101b', 'label' => 'Children 6-23 months receiving GMP services'],
                        ['id' => 'row_101_24_59m', 'number' => '101c', 'label' => 'Children 24-59 months receiving GMP services'],
                    ],
                ],
                [
                    'id' => 'sec_growth_monitoring_indicators',
                    'title' => 'Nutrition: Growth, Exclusive Breastfeeding & IYCN Counselling',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_102', 'number' => 102, 'label' => 'Children 0-59 months that are growing well'],
                        ['id' => 'row_103', 'number' => 103, 'label' => 'Children 0-6 months receiving Exclusive Breast Feeding (EBF)'],
                        ['id' => 'row_104', 'number' => 104, 'label' => 'Clients counselled on Infant and Young Child Nutrition (IYCN)'],
                    ],
                ],
                [
                    'id' => 'sec_growth_vit_a',
                    'title' => 'Nutrition: Vitamin A Supplementation',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_105_6_11m', 'number' => '105a', 'label' => 'Children 6-11 months given Vitamin A'],
                        ['id' => 'row_105_12_59m', 'number' => '105b', 'label' => 'Children 12-59 months given Vitamin A'],
                    ],
                ],
                [
                    'id' => 'sec_growth_supplements',
                    'title' => 'Nutrition: Micronutrient Powder (MNP) & Deworming',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_106', 'number' => 106, 'label' => 'Children 6-23 months who received Micronutrient Powder (MNP)'],
                        ['id' => 'row_107', 'number' => 107, 'label' => 'Children 12-59 months who received deworming medication'],
                    ],
                ],
                [
                    'id' => 'sec_sam_admissions',
                    'title' => 'Nutrition: Severe Acute Malnutrition (SAM) Admissions & Outcomes',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_108', 'number' => 108, 'label' => 'Children <5 years admitted for SAM treatment'],
                        ['id' => 'row_109_new', 'number' => '109a', 'label' => 'SAM Treatment: New'],
                        ['id' => 'row_109_transferred_in', 'number' => '109b', 'label' => 'SAM Treatment: Transferred In'],
                        ['id' => 'row_109_recovered', 'number' => '109c', 'label' => 'SAM Treatment: Recovered'],
                        ['id' => 'row_109_defaulted', 'number' => '109d', 'label' => 'SAM Treatment: Defaulted'],
                        ['id' => 'row_109_dead', 'number' => '109e', 'label' => 'SAM Treatment: Dead'],
                        ['id' => 'row_109_transferred_out', 'number' => '109f', 'label' => 'SAM Treatment: Transferred Out'],
                    ],
                ],
                [
                    'id' => 'sec_imci',
                    'title' => 'Child Health and Integrated Management of Childhood Illnesses (CH & IMCI)',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_110', 'number' => 110, 'label' => 'Diarrhoea new cases < 5 years'],
                        ['id' => 'row_111', 'number' => 111, 'label' => 'Diarrhoea new cases < 5 years given ORS and zinc'],
                        ['id' => 'row_112', 'number' => 112, 'label' => 'Pneumonia new cases < 5 years'],
                        ['id' => 'row_113', 'number' => 113, 'label' => 'Pneumonia new cases < 5 years given antibiotics (amoxyl DT)'],
                        ['id' => 'row_114', 'number' => 114, 'label' => 'Measles new cases < 5 years'],
                    ],
                ],
                [
                    'id' => 'sec_family_planning_counseling',
                    'title' => 'Family Planning: Clients Counselled & New Acceptors',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_115', 'number' => 115, 'label' => 'Family Planning (FP) clients counselled'],
                        ['id' => 'row_116', 'number' => 116, 'label' => 'New family planning acceptors'],
                    ],
                ],
                [
                    'id' => 'sec_family_planning_modern_age',
                    'title' => 'Family Planning: Modern Contraceptive Users by Age Group',
                    'columns' => [
                        'age_10_14y' => ['label' => '10-14 yrs'],
                        'age_15_19y' => ['label' => '15-19 yrs'],
                        'age_20_24y' => ['label' => '20-24 yrs'],
                        'age_25_49y' => ['label' => '25-49 yrs'],
                        'age_ge_50y' => ['label' => '≥50 yrs'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_117', 'number' => 117, 'label' => 'Females using modern contraception'],
                    ],
                ],
                [
                    'id' => 'sec_family_planning_pills',
                    'title' => 'Family Planning: Oral & Emergency Contraceptive Pills',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_118', 'number' => 118, 'label' => 'Clients given oral pills'],
                        ['id' => 'row_119', 'number' => 119, 'label' => 'Oral pills cycle (sachets) dispensed'],
                        ['id' => 'row_120', 'number' => 120, 'label' => 'Emergency contraceptive pills dispensed'],
                    ],
                ],
                [
                    'id' => 'sec_family_planning_injectables',
                    'title' => 'Family Planning: Injectables Administered',
                    'columns' => [
                        'noristerat' => ['label' => 'Noristerat'],
                        'dmpa_im' => ['label' => 'DMPA-IM'],
                        'provider_dmpa_sc' => ['label' => 'Provider DMPA-SC'],
                        'self_inject_dmpa_sc' => ['label' => 'Self-Inject DMPA-SC'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_121', 'number' => 121, 'label' => 'Injectables given'],
                    ],
                ],
                [
                    'id' => 'sec_family_planning_iud',
                    'title' => 'Family Planning: Intrauterine Devices (IUD) Inserted',
                    'columns' => [
                        'cut_380a_10y' => ['label' => '10 years CuT 380A (Copper)'],
                        'lng_ius_5y' => ['label' => '5 years LNG IUS (Hormonal)'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_122', 'number' => 122, 'label' => 'IUD inserted'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Page 4: Family Planning (cont.), Referrals, NCDs, Malaria, TB, Hepatitis & GBV
     */
    private function getPage4(): array
    {
        $mfTotal = $this->getMaleFemaleTotalColumns();
        $totalOnly = $this->getTotalOnlyColumn();

        $malariaCols = [
            'lt_5y' => ['label' => '<5 years'],
            'ge_5y_excl_pw' => ['label' => '≥5yrs (excluding PW)'],
            'pregnant_women' => ['label' => 'Pregnant Women'],
            'total' => ['label' => 'Total', 'is_total' => true],
        ];

        return [
            'id' => 'page_4',
            'number' => 4,
            'title' => 'Family Planning (cont.), Referrals, NCDs, Malaria, TB, Hepatitis & GBV',
            'sections' => [
                [
                    'id' => 'sec_fp_implants',
                    'title' => 'Family Planning: Implants Inserted',
                    'columns' => [
                        'implanon_nxt' => ['label' => 'Implanon NXT'],
                        'jadelle' => ['label' => 'Jadelle'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_123', 'number' => 123, 'label' => 'Implants inserted'],
                    ],
                ],
                [
                    'id' => 'sec_fp_sterilization',
                    'title' => 'Family Planning: Voluntary Sterilization',
                    'columns' => [
                        'male' => ['label' => 'Male (Vasectomy)'],
                        'female' => ['label' => 'Female (BTL)'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_124', 'number' => 124, 'label' => 'Voluntary Sterilization'],
                    ],
                ],
                [
                    'id' => 'sec_fp_condoms_received',
                    'title' => 'Family Planning: Clients that received Condoms',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_125', 'number' => 125, 'label' => 'Clients that received Condoms'],
                    ],
                ],
                [
                    'id' => 'sec_fp_condoms_distributed',
                    'title' => 'Family Planning: Condoms Distributed by Type',
                    'columns' => [
                        'male_condom' => ['label' => 'Male condom'],
                        'female_condom' => ['label' => 'Female condom'],
                        'total' => ['label' => 'Total', 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_126', 'number' => 126, 'label' => 'Condoms distributed'],
                    ],
                ],
                [
                    'id' => 'sec_fp_postpartum_referrals',
                    'title' => 'Family Planning: Postpartum FP & Service Referrals',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_127', 'number' => 127, 'label' => 'Individual referred for FP services from other units'],
                        ['id' => 'row_128', 'number' => 128, 'label' => 'Women counselled on Postpartum Family Planning'],
                        ['id' => 'row_129', 'number' => 129, 'label' => 'Post-partum Implanon NXT inserted'],
                        ['id' => 'row_130', 'number' => 130, 'label' => 'Post-partum Jadelle inserted'],
                        ['id' => 'row_131', 'number' => 131, 'label' => 'Post-partum IUD inserted'],
                    ],
                ],
                [
                    'id' => 'sec_referrals',
                    'title' => 'Specialist Referrals Out',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_132', 'number' => 132, 'label' => 'All out-going referred cases (Referral out)'],
                        ['id' => 'row_133', 'number' => 133, 'label' => 'Malaria cases referred for further treatment'],
                        ['id' => 'row_134', 'number' => 134, 'label' => 'Malaria cases referred for adverse drug reaction'],
                        ['id' => 'row_135', 'number' => 135, 'label' => 'Women referred out for Pregnancy related complications'],
                        ['id' => 'row_136', 'number' => 136, 'label' => 'Women seen and referred for Obstetric Fistula (VVF & RVF)'],
                    ],
                ],
                [
                    'id' => 'sec_ncds',
                    'title' => 'Non-Communicable Diseases (NCDs)',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_137', 'number' => 137, 'label' => 'Diabetes Mellitus new cases (suspected)'],
                        ['id' => 'row_138', 'number' => 138, 'label' => 'Women with Gestational Diabetes (suspected)'],
                        ['id' => 'row_139', 'number' => 139, 'label' => 'Hypertension new cases (suspected)'],
                        ['id' => 'row_140', 'number' => 140, 'label' => 'Arthritis new cases (suspected)'],
                        ['id' => 'row_141', 'number' => 141, 'label' => 'Sickle Cell disease new cases (suspected)'],
                        ['id' => 'row_142', 'number' => 142, 'label' => 'Asthma new cases (suspected)'],
                        ['id' => 'row_143', 'number' => 143, 'label' => 'Depression new cases (suspected)'],
                        ['id' => 'row_144', 'number' => 144, 'label' => 'Breast Cancer new cases (suspected)'],
                        ['id' => 'row_145', 'number' => 145, 'label' => 'Cervical Cancer new cases (suspected)'],
                    ],
                ],
                [
                    'id' => 'sec_malaria_llin',
                    'title' => 'Malaria: Under 5 LLIN Distribution',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_146', 'number' => 146, 'label' => 'Children under 5 years who received LLIN'],
                    ],
                ],
                [
                    'id' => 'sec_malaria_testing',
                    'title' => 'Malaria: Testing Services (RDT & Microscopy)',
                    'columns' => $malariaCols,
                    'rows' => [
                        ['id' => 'row_147', 'number' => 147, 'label' => 'Persons presenting with fever'],
                        ['id' => 'row_148', 'number' => 148, 'label' => 'Persons presenting with fever and tested by RDT'],
                        ['id' => 'row_149', 'number' => 149, 'label' => 'Persons tested positive for malaria by RDT'],
                        ['id' => 'row_150', 'number' => 150, 'label' => 'Persons presenting with fever and tested by Microscopy'],
                        ['id' => 'row_151', 'number' => 151, 'label' => 'Persons tested positive for malaria by Microscopy'],
                    ],
                ],
                [
                    'id' => 'sec_malaria_cases',
                    'title' => 'Malaria: Clinical, Confirmed & Severe Cases',
                    'columns' => $malariaCols,
                    'rows' => [
                        ['id' => 'row_152', 'number' => 152, 'label' => 'Persons with clinically diagnosed Malaria'],
                        ['id' => 'row_153', 'number' => 153, 'label' => 'Persons with confirmed uncomplicated Malaria'],
                        ['id' => 'row_154', 'number' => 154, 'label' => 'Severe Malaria cases seen'],
                    ],
                ],
                [
                    'id' => 'sec_malaria_treatment',
                    'title' => 'Malaria: Treatment Services (ACT & Injectable Therapies)',
                    'columns' => $malariaCols,
                    'rows' => [
                        ['id' => 'row_155', 'number' => 155, 'label' => 'Confirmed Uncomplicated Malaria treated with ACT'],
                        ['id' => 'row_156', 'number' => 156, 'label' => 'Clinically diagnosed Malaria treated with ACT'],
                        ['id' => 'row_157', 'number' => 157, 'label' => 'Confirmed Uncomplicated Malaria treated with other antimalarials'],
                        ['id' => 'row_158', 'number' => 158, 'label' => 'Severe Malaria given recommended pre-referral treatment'],
                        ['id' => 'row_159', 'number' => 159, 'label' => 'Severe Malaria treated with Artesunate injection'],
                        ['id' => 'row_160', 'number' => 160, 'label' => 'Severe Malaria treated with other injectable antimalarials'],
                    ],
                ],
                [
                    'id' => 'sec_tb',
                    'title' => 'Tuberculosis: Screening & Referrals',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_161', 'number' => 161, 'label' => 'Persons Screened for TB'],
                        ['id' => 'row_162', 'number' => 162, 'label' => 'Persons with TB score'],
                        ['id' => 'row_163', 'number' => 163, 'label' => 'Persons with TB score referred to TB services'],
                    ],
                ],
                [
                    'id' => 'sec_hepatitis_b',
                    'title' => 'Hepatitis B: Screening, Treatment & Referral Services',
                    'columns' => [
                        'm_10_19y' => ['label' => '10-19 years', 'group' => 'Male'],
                        'm_ge_20y' => ['label' => '≥20 years', 'group' => 'Male'],
                        'f_10_19y' => ['label' => '10-19 years', 'group' => 'Female'],
                        'f_ge_20y' => ['label' => '≥20 years', 'group' => 'Female'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_164', 'number' => 164, 'label' => 'Persons tested for Hepatitis B'],
                        ['id' => 'row_165', 'number' => 165, 'label' => 'Persons tested Hep B positive (Hep B +ve result)'],
                        ['id' => 'row_166', 'number' => 166, 'label' => 'Persons treated for Hepatitis B'],
                        ['id' => 'row_167', 'number' => 167, 'label' => 'Persons with Hepatitis B referred for further treatment'],
                    ],
                ],
                [
                    'id' => 'sec_hepatitis_c',
                    'title' => 'Hepatitis C: Screening, Treatment & Referral Services',
                    'columns' => [
                        'm_10_19y' => ['label' => '10-19 years', 'group' => 'Male'],
                        'm_ge_20y' => ['label' => '≥20 years', 'group' => 'Male'],
                        'f_10_19y' => ['label' => '10-19 years', 'group' => 'Female'],
                        'f_ge_20y' => ['label' => '≥20 years', 'group' => 'Female'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_168', 'number' => 168, 'label' => 'Persons tested for Hepatitis C'],
                        ['id' => 'row_169', 'number' => 169, 'label' => 'Persons tested Hepatitis C positive (Hep C +ve result)'],
                        ['id' => 'row_170', 'number' => 170, 'label' => 'Persons treated for Hepatitis C'],
                        ['id' => 'row_171', 'number' => 171, 'label' => 'Persons with Hepatitis C referred for further treatment'],
                    ],
                ],
                [
                    'id' => 'sec_gbv',
                    'title' => 'Gender Based Violence (GBV) Care Services',
                    'columns' => [
                        'm_lt_20y' => ['label' => '<20 years', 'group' => 'Male'],
                        'm_ge_20y' => ['label' => '≥20 years', 'group' => 'Male'],
                        'f_lt_20y' => ['label' => '<20 years', 'group' => 'Female'],
                        'f_ge_20y' => ['label' => '≥20 years', 'group' => 'Female'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_172', 'number' => 172, 'label' => 'Gender based violence cases seen'],
                        ['id' => 'row_173', 'number' => 173, 'label' => 'Gender based violence cases who received post GBV care'],
                        ['id' => 'row_174', 'number' => 174, 'label' => 'Gender based violence cases referred for further treatment'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Page 5: Fistula, NTDs, Pharmacovigilance & Sign-off
     */
    private function getPage5(): array
    {
        $mfTotal = $this->getMaleFemaleTotalColumns();
        $totalOnly = $this->getTotalOnlyColumn();

        return [
            'id' => 'page_5',
            'number' => 5,
            'title' => 'Obstetric Fistula, NTDs, Pharmacovigilance & Sign-off',
            'sections' => [
                [
                    'id' => 'sec_fistula',
                    'title' => 'Obstetric Fistula Services',
                    'columns' => [
                        'vvf_10_19y' => ['label' => '10-19 yrs', 'group' => 'VVF Only'],
                        'vvf_ge_20y' => ['label' => '≥20 yrs', 'group' => 'VVF Only'],
                        'rvf_10_19y' => ['label' => '10-19 yrs', 'group' => 'RVF Only'],
                        'rvf_ge_20y' => ['label' => '≥20 yrs', 'group' => 'RVF Only'],
                        'vvf_rvf_10_19y' => ['label' => '10-19 yrs', 'group' => 'VVF & RVF Combined'],
                        'vvf_rvf_ge_20y' => ['label' => '≥20 yrs', 'group' => 'VVF & RVF Combined'],
                        'total' => ['label' => 'Total', 'group' => null, 'is_total' => true],
                    ],
                    'rows' => [
                        ['id' => 'row_175', 'number' => 175, 'label' => 'Obstetric Fistula cases seen'],
                        ['id' => 'row_176', 'number' => 176, 'label' => 'Obstetric Fistula cases successfully repaired'],
                        ['id' => 'row_177', 'number' => 177, 'label' => 'Obstetric Fistula cases failed repair'],
                        ['id' => 'row_178', 'number' => 178, 'label' => 'Obstetric Fistula cases counselled on FP'],
                        ['id' => 'row_179', 'number' => 179, 'label' => 'Obstetric Fistula cases who became pregnant after repair'],
                        ['id' => 'row_180', 'number' => 180, 'label' => 'Obstetric Fistula cases scheduled for elective Caesarean section'],
                        ['id' => 'row_181', 'number' => 181, 'label' => 'Obstetric Fistula cases referred'],
                    ],
                ],
                [
                    'id' => 'sec_ntds',
                    'title' => 'Neglected Tropical Diseases (NTDs)',
                    'columns' => $mfTotal,
                    'rows' => [
                        ['id' => 'row_182', 'number' => 182, 'label' => 'Trachoma cases seen'],
                        ['id' => 'row_183', 'number' => 183, 'label' => 'Snake bite cases seen'],
                        ['id' => 'row_184', 'number' => 184, 'label' => 'Snake bite cases that received anti-snake venom'],
                    ],
                ],
                [
                    'id' => 'sec_adrs',
                    'title' => 'Pharmacovigilance: Adverse Drug Reactions (ADRs)',
                    'columns' => $totalOnly,
                    'rows' => [
                        ['id' => 'row_185', 'number' => 185, 'label' => 'Adverse Drug Reaction cases reported to NAFDAC'],
                    ],
                ],
            ],
        ];
    }
}
