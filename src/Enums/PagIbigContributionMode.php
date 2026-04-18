<?php

namespace Jdclzn\PayrollEngine\Enums;

enum PagIbigContributionMode: string
{
    case StandardMandatory = 'standard_mandatory';
    case SplitPerCutoff = 'split_per_cutoff';
    case UpgradedVoluntary = 'upgraded_voluntary';
    case LoanAmortizationSeparated = 'loan_amortization_separated';
}
