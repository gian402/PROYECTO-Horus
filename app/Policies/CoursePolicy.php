<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function view(User $user, Course $model): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function update(User $user, Course $model): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }

    public function delete(User $user, Course $model): bool
    {
        return $user->can(Permission::CoursesManage->value);
    }
}
