<?php

namespace ItmoSoft\VendorPayouts\Enums;

enum EarningStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
