<?php

namespace App\Enums;

enum ScopeType: string
{
    case Country = 'country';
    case Province = 'province';
    case City = 'city';
}
