<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TeachersManage->value);
    }

    public function view(User $user, Teacher $model): bool
    {
        return $user->can(Permission::TeachersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TeachersManage->value);
    }

    public function update(User $user, Teacher $model): bool
    {
        return $user->can(Permission::TeachersManage->value);
    }

    public function delete(User $user, Teacher $model): bool
    {
        return $user->can(Permission::TeachersManage->value);
    }
}
