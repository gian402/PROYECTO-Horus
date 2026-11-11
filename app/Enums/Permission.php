<?php

namespace App\Enums;

/**
 * Permisos del CRM (Spatie). Los nombres son la única fuente de verdad:
 * el seeder, las Policies y el menú del frontend los usan.
 */
enum Permission: string
{
    case UsersManage = 'users.manage';
    case StudentsView = 'students.view';
    case StudentsManage = 'students.manage';
    case CoursesManage = 'courses.manage';
    case TeachersManage = 'teachers.manage';
    case EditionsView = 'editions.view';
    case EditionsManage = 'editions.manage';
    case EnrollmentsManage = 'enrollments.manage';
    case GradesManage = 'grades.manage';
    case CertificatesView = 'certificates.view';
    case CertificatesIssue = 'certificates.issue';
    case ImportsRun = 'imports.run';
    case ReportsView = 'reports.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Permisos por rol. Super Admin además pasa todas las comprobaciones vía Gate::before.
     *
     * @return array<string, list<string>>
     */
    public static function byRole(): array
    {
        $all = self::values();

        return [
            'Super Admin' => $all,
            'Coordinador Académico' => array_values(array_diff($all, [self::UsersManage->value])),
            'Ponente' => [
                self::StudentsView->value,
                self::EditionsView->value,
                self::GradesManage->value,
                self::CertificatesView->value,
            ],
        ];
    }
}
