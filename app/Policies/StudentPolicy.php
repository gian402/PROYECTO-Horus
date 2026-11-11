<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::StudentsView->value, Permission::StudentsManage->value]);
    }

    public function view(User $user, Student $student): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StudentsManage->value);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can(Permission::StudentsManage->value);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can(Permission::StudentsManage->value);
    }
}
