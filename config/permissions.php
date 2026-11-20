<?php
declare(strict_types=1);

/**
 * Role based navigation and action permissions.
 * "pages"  – modules the role may open (read).
 * "write"  – modules the role may create/update.
 */
function permissions_map(): array
{
    $academic = [
        'students', 'research_projects', 'proposals', 'supervisors', 'milestones',
        'documents', 'evaluations',
    ];
    $institutional = ['lecturer_research', 'research_teams', 'publications'];
    $innovation    = ['innovation_projects', 'innovation_teams', 'competitions', 'judges', 'evaluation_results'];
    $finance       = ['funding_applications', 'budgets', 'expenditure', 'financial_reports'];
    $university    = ['faculties', 'departments', 'programmes', 'academic_years', 'semesters'];
    $insight       = ['reports', 'notifications', 'profile'];

    return [
        'admin' => [
            'label' => 'System Administrator',
            'description' => 'Full access to every module, user account and system setting.',
            'home'  => 'dashboard',
            'pages' => ['*'],
            'write' => ['*'],
            'delete' => ['*'],
            'scope' => 'all',
        ],
        'research_officer' => [
            'label' => 'Research Officer',
            'description' => 'Manages university research, innovation and funding portfolios.',
            'home'  => 'dashboard',
            'pages' => array_merge(['dashboard'], $university, $academic, $institutional, $innovation, $finance, $insight),
            'write' => array_merge($university, $academic, $institutional, $innovation, $finance),
            'delete' => array_diff(array_merge($university, $academic, $institutional, $innovation, $finance), ['users']),
            'scope' => 'all',
        ],
        'dean' => [
            'label' => 'Faculty Dean',
            'description' => 'Oversees research activity and approvals across the faculty.',
            'home'  => 'dashboard',
            'pages' => array_merge(['dashboard', 'faculties', 'departments', 'programmes', 'academic_years', 'semesters'], $academic, $institutional, ['innovation_projects', 'competitions', 'judges', 'evaluation_results'], $finance, ['reports', 'notifications', 'profile']),
            'write' => array_merge(['proposals', 'evaluations', 'publications', 'lecturer_research', 'research_teams'], ['funding_applications', 'budgets']),
            'delete' => [],
            'scope' => 'all',
        ],
        'hod' => [
            'label' => 'Head of Department',
            'description' => 'Coordinates departmental research, proposals and supervisors.',
            'home'  => 'dashboard',
            'pages' => array_merge(['dashboard', 'faculties', 'departments', 'programmes', 'academic_years', 'semesters'], $academic, $institutional, ['innovation_projects', 'competitions', 'evaluation_results'], ['funding_applications', 'budgets', 'expenditure'], ['reports', 'notifications', 'profile']),
            'write' => ['proposals', 'evaluations', 'milestones', 'documents', 'publications', 'lecturer_research', 'research_teams'],
            'delete' => [],
            'scope' => 'all',
        ],
        'supervisor' => [
            'label' => 'Research Supervisor',
            'description' => 'Supervises student research, reviews proposals and signs off milestones.',
            'home'  => 'dashboard',
            'pages' => ['dashboard', 'students', 'research_projects', 'proposals', 'milestones', 'documents', 'evaluations', 'publications', 'notifications', 'reports', 'profile'],
            'write' => ['research_projects', 'proposals', 'milestones', 'documents', 'evaluations'],
            'delete' => [],
            'scope' => 'own_supervision',
        ],
        'student' => [
            'label' => 'Student Researcher',
            'description' => 'Works on your research project, proposals, milestones and documents.',
            'home'  => 'dashboard',
            'pages' => ['dashboard', 'research_projects', 'proposals', 'milestones', 'documents', 'notifications', 'profile'],
            'write' => ['proposals', 'documents'],
            'delete' => [],
            'scope' => 'own_student',
        ],
        'finance' => [
            'label' => 'Finance Officer',
            'description' => 'Manages research funding, budgets, expenditure and financial reporting.',
            'home'  => 'dashboard',
            'pages' => ['dashboard', 'funding_applications', 'budgets', 'expenditure', 'financial_reports', 'reports', 'notifications', 'profile'],
            'write' => ['funding_applications', 'budgets', 'expenditure'],
            'delete' => ['expenditure'],
            'scope' => 'all',
        ],
        'judge' => [
            'label' => 'Innovation Judge',
            'description' => 'Scores innovation competition entries and reviews results.',
            'home'  => 'dashboard',
            'pages' => ['dashboard', 'innovation_projects', 'competitions', 'evaluation_results', 'notifications', 'profile'],
            'write' => ['evaluation_results'],
            'delete' => [],
            'scope' => 'all',
        ],
    ];
}

function role_permissions(string $role): array
{
    $map = permissions_map();
    return $map[$role] ?? $map['student'];
}

function permission_list(string $role, string $type): array
{
    $perms = role_permissions($role);
    return $perms[$type] ?? [];
}

function role_can(string $role, string $page, string $type = 'pages'): bool
{
    $list = permission_list($role, $type);
    return in_array('*', $list, true) || in_array($page, $list, true);
}
