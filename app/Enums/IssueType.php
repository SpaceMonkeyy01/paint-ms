<?php

namespace App\Enums;

enum IssueType: string
{
    case Bom = 'bom';           // within BOM allowance
    case Variance = 'variance'; // over BOM, reason required
    case Rework = 'rework';     // repaint
    case Reissue = 'reissue';   // re-issue after a failed/short issue
}
