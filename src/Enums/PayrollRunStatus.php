<?php

namespace QuillBytes\PayrollEngine\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Approved = 'approved';
    case Processed = 'processed';
    case Released = 'released';
}
