<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Store = 'store';
    case Painter = 'painter';
}
