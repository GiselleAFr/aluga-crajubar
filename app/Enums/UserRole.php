<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case LANDLORD = 'landlord';
}
