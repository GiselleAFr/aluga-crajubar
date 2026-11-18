<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case LANDLORD = 'landlord';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';
}