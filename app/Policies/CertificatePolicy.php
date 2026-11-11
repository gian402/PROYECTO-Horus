<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Emitir/reemitir/anular: certificates.issue (coordinación).
 * Descargar: coordinación, o el Ponente de la edición (certificates.view).
 */
class CertificatePolicy
{
    public function issue(User $user, Enrollment $enrollment): bool
    {
        return $user->can(Permission::CertificatesIssue->value);
    }

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $user->can(Permission::CertificatesIssue->value);
    }

    public function download(User $user, Certificate $certificate): bool
    {
        if ($user->can(Permission::CertificatesIssue->value)) {
            return true;
        }

        return $user->can(Permission::CertificatesView->value)
            && $user->teaches($certificate->enrollment->courseEdition);
    }
}
