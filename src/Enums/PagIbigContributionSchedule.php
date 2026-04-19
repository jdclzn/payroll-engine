<?php

namespace QuillBytes\PayrollEngine\Enums;

enum PagIbigContributionSchedule: string
{
    case Monthly = 'monthly';
    case SplitPerCutoff = 'split_per_cutoff';
}
