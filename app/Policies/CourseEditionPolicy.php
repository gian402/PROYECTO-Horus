<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CourseEdition;
use App\Models\User;

/**
 * Coordinación (editions.manage) ve y gestiona todas las ediciones.
 * El Ponente (editions.view / grades.manage) solo las que dicta.
 */
class CourseEditionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::EditionsView->value, Permission::EditionsManage->value]);
    }

    public function view(User $user, CourseEdition $edition): bool
    {
        return $user->can(Permission::EditionsManage->value)
            || ($user->can(Permission::EditionsView->value) && $user->teaches($edition));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EditionsManage->value);
    }

    public function update(User $user, CourseEdition $edition): bool
    {
        return $user->can(Permission::EditionsManage->value);
    }

    public function delete(User $user, CourseEdition $edition): bool
    {
        return $user->can(Permission::EditionsManage->value);
    }

    public function enroll(User $user, CourseEdition $edition): bool
    {
        return $user->can(Permission::EnrollmentsManage->value);
    }

    public function grade(User $user, CourseEdition $edition): bool
    {
        return $user->can(Permission::GradesManage->value)
            && ($user->can(Permission::EditionsManage->value) || $user->teaches($edition));
    }
}
