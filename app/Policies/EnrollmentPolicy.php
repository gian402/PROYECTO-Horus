<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::EnrollmentsManage->value);
    }
}
