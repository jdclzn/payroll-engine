<?php

namespace QuillBytes\PayrollEngine\Enums;

enum TaxStrategy: string
{
    case CurrentPeriodAnnualized = 'current_period_annualized';
    case ProjectedAnnualized = 'projected_annualized';
}
