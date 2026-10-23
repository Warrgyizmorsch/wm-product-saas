<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Employer statutory contributions (India)
    |--------------------------------------------------------------------------
    |
    | Booked with each payroll run on top of what is deducted from employees:
    | Dr Employer Contribution to PF & ESI / Cr PF Payable, ESI Payable.
    |
    | - employer_pf_matches_employee: the employer pays the same 12% EPF/EPS as
    |   the employee's deduction (on the same capped PF wages).
    | - pf_charges_percent: EDLI 0.5% + EPF admin 0.5% of PF wages, where PF
    |   wages are derived from the employee deduction at employee_pf_percent.
    | - ESI: employer 3.25% against the employee's 0.75% of the same wages.
    |
    */
    'employer_contributions' => [
        'employer_pf_matches_employee' => (bool) env('PAYROLL_EMPLOYER_PF', true),
        'employee_pf_percent' => (float) env('PAYROLL_EMPLOYEE_PF_PERCENT', 12),
        'pf_charges_percent' => (float) env('PAYROLL_PF_CHARGES_PERCENT', 1.0),
        'employee_esi_percent' => (float) env('PAYROLL_EMPLOYEE_ESI_PERCENT', 0.75),
        'employer_esi_percent' => (float) env('PAYROLL_EMPLOYER_ESI_PERCENT', 3.25),
    ],

];
