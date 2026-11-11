export type EnrollmentStatus = 'cursando' | 'aprobado' | 'desaprobado';

export type EnrollmentRole = 'participante' | 'ponente';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Forma del LengthAwarePaginator de Laravel serializado por Inertia. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

export type Filters = {
    search?: string;
    [key: string]: string | number | null | undefined;
};

export type StudentRow = {
    id: number;
    dni: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    enrollments_count: number;
};

export type StudentDetail = {
    id: number;
    dni: string;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string | null;
    phone: string | null;
};

export type StudentFormData = {
    id?: number;
    dni: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
};

export type StudentEnrollment = {
    id: number;
    registration_code: string;
    grade: string | null;
    status: EnrollmentStatus;
    role: EnrollmentRole;
    edition: {
        id: number;
        label: string;
        year: number;
        course: { id: number; code: string; name: string };
    };
    certificate: { id: number; issued_at: string | null } | null;
};

export type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    teacher: { id: number; full_name: string } | null;
    created_at: string | null;
};

export type CourseRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    editions_count: number;
};

export type CourseFormData = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    code_locked: boolean;
};

export type TeacherRow = {
    id: number;
    dni: string;
    full_name: string;
    specialty: string | null;
    editions_count: number;
    user_email: string | null;
};

export type TeacherFormData = {
    id: number;
    dni: string;
    full_name: string;
    specialty: string | null;
};

export type Option = {
    id: number;
    code?: string;
    name?: string;
    full_name?: string;
};

export type EditionOptions = {
    courses: { id: number; code: string; name: string }[];
    teachers: { id: number; full_name: string }[];
};

export type EditionRow = {
    id: number;
    label: string;
    course: { id: number; code: string; name: string };
    teacher: { id: number; full_name: string } | null;
    start_date: string | null;
    end_date: string | null;
    year: number;
    enrollments_count: number;
};

export type EditionFormData = {
    id: number;
    course_id: number;
    teacher_id: number | null;
    start_date: string | null;
    end_date: string | null;
    year: number;
    locked: boolean;
};

export type EditionDetail = Omit<EditionRow, 'enrollments_count'>;

export type EditionEnrollment = {
    id: number;
    registration_code: string;
    grade: string | null;
    status: EnrollmentStatus;
    role: EnrollmentRole;
    historical: boolean;
    student: { id: number; dni: string; full_name: string };
    certificate: { id: number; issued_at: string | null } | null;
};

export type StudentSearchResult = {
    id: number;
    dni: string;
    full_name: string;
};

export type VerificationStatus = 'valid' | 'revoked' | 'not_found';

export type VerifiedCertificate = {
    participant: string;
    course: string;
    start_date: string | null;
    end_date: string | null;
    year: number;
    role: EnrollmentRole;
    registration_code: string;
    issued_at: string;
    revoked_at: string | null;
};

export type TeacherOption = {
    id: number;
    full_name: string;
    user_id: number | null;
};
