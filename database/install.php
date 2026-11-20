<?php
declare(strict_types=1);

/**
 * URIMS installer – run from the terminal:
 *   php database/install.php
 * Recreates the database schema and loads demonstration data.
 */

$urimsArgs  = $_SERVER['argv'] ?? [];
$isCli      = PHP_SAPI === 'cli';
$urimsFlags = [];
foreach ($urimsArgs as $urimsArg) {
    if (is_string($urimsArg) && str_starts_with($urimsArg, '--')) {
        $urimsFlags[] = substr($urimsArg, 2);
    }
}

$fresh     = in_array('fresh', $urimsFlags, true) || (!$isCli && ($_GET['fresh'] ?? '') === '1');
$noSeed    = in_array('no-seed', $urimsFlags, true) || (!$isCli && ($_GET['seed'] ?? '') === '0');
$checkOnly = in_array('check', $urimsFlags, true);

if (!$isCli && ($_GET['confirm'] ?? '') !== 'install') {
    http_response_code(403);
    exit('Installer is protected. Run "php database/install.php" from the terminal, or append ?confirm=install.');
}

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once __DIR__ . '/bootstrap.php';

$config = db_config();

function out(string $line, string $type = 'info'): void
{
    $prefix = ['info' => '  ', 'ok' => '  ✓ ', 'warn' => '  ! '][$type] ?? '  ';
    if (PHP_SAPI === 'cli') {
        echo $prefix . $line . PHP_EOL;
        return;
    }
    echo htmlspecialchars($prefix . $line, ENT_QUOTES, 'UTF-8') . "\n";
}

if (!$isCli) {
    echo '<pre style="font:14px/1.6 ui-monospace,Consolas,monospace;background:#0f172a;color:#d1fae5;padding:24px;border-radius:12px">';
}

try {
    $pdo = db();
    out('Connected to MySQL at ' . $config['host'] . ':' . $config['port'], 'ok');
    out('Database "' . $config['name'] . '" selected (credentials: ' . ($config['source'] ?? 'unknown') . ')', 'ok');

    if ($checkOnly) {
        $missing = urims_missing_tables($pdo);
        out(
            $missing === []
                ? 'Connection OK. All ' . count(urims_required_tables()) . ' required tables are present.'
                : 'Connection OK, but ' . count($missing) . ' table(s) are missing: ' . implode(', ', $missing),
            $missing === [] ? 'ok' : 'warn'
        );
        if (!$isCli) {
            echo '</pre>';
        }
        exit($missing === [] ? 0 : 1);
    }

    $result = urims_run_schema($pdo, $fresh);
    out(
        'Schema applied: ' . $result['executed'] . ' statement(s) executed, ' . $result['skipped']
        . ' skipped (' . ($result['fresh'] ? 'fresh rebuild' : 'existing tables preserved') . ')',
        'ok'
    );

    $missing = urims_missing_tables($pdo);
    if ($missing !== []) {
        throw new RuntimeException('Schema incomplete after install; still missing: ' . implode(', ', $missing));
    }
} catch (Throwable $e) {
    out('Database error: ' . $e->getMessage(), 'warn');
    if (!$isCli) {
        echo '</pre>';
    }
    exit(1);
}

$seedDemoData = !$noSeed && urims_seed_is_needed($pdo);
if (!$seedDemoData) {
    out('Existing data detected; demo records were not re-seeded', 'ok');
}

/* ---------------------------------------------------------------
 | Seeding helpers
 --------------------------------------------------------------- */
mt_srand(20261120);

function ins(PDO $pdo, string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
        . implode(',', array_fill(0, count($cols), '?')) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

function ago(int $days, string $clock = '09:15:00'): string
{
    return date('Y-m-d H:i:s', strtotime(($days >= 0 ? '+' : '') . $days . ' days ' . $clock));
}

function pick(array $items): mixed
{
    return $items[array_rand($items)];
}

function money_rand(float $min, float $max): float
{
    return round(mt_rand((int) $min, (int) $max) / 1000) * 1000;
}

function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/* ---------------------------------------------------------------
 | Faculties, departments, programmes, calendar
 --------------------------------------------------------------- */
if ($seedDemoData) {
$faculties = [
    ['FST', 'Faculty of Science & Technology', 'Dr. Neema Kessy', 'fst@kiut.ac.tz', '+255 22 555 0101', 'Computing, engineering, mathematics and applied sciences research.'],
    ['FBM', 'Faculty of Business & Management', 'Dr. Joseph Mbwana', 'fbm@kiut.ac.tz', '+255 22 555 0102', 'Business, finance, entrepreneurship and management research.'],
    ['FED', 'Faculty of Education', 'Dr. Grace Ndunguru', 'fed@kiut.ac.tz', '+255 22 555 0103', 'Teacher education, curriculum development and pedagogy research.'],
    ['FHS', 'Faculty of Health Sciences', 'Dr. Fatuma Ally', 'fhs@kiut.ac.tz', '+255 22 555 0104', 'Public health, nursing and clinical research programmes.'],
    ['FLW', 'Faculty of Law', 'Dr. Baraka Mushi', 'flw@kiut.ac.tz', '+255 22 555 0105', 'Human rights, commercial law and legal policy research.'],
];
$facultyIds = [];
foreach ($faculties as $i => $f) {
    $facultyIds[$i + 1] = ins($pdo, 'faculties', [
        'code' => $f[0], 'name' => $f[1], 'dean' => $f[2], 'email' => $f[3], 'phone' => $f[4],
        'description' => $f[5], 'status' => 'active', 'created_at' => ago(-400),
    ]);
}

$departments = [
    [1, 'CS', 'Department of Computer Science', 'Dr. Salma Juma'],
    [1, 'IT', 'Department of Information Technology', 'Dr. Peter Msami'],
    [1, 'MTH', 'Department of Mathematics & Statistics', 'Mr. Hamisi Ally'],
    [2, 'ACC', 'Department of Accounting & Finance', 'Dr. Rehema Mkwawa'],
    [2, 'MGT', 'Department of Business Management', 'Dr. Joseph Mbwana'],
    [3, 'EDF', 'Department of Educational Foundations', 'Dr. Grace Ndunguru'],
    [3, 'CUR', 'Department of Curriculum & Instruction', 'Dr. Zawadi Mushi'],
    [4, 'PH', 'Department of Public Health', 'Dr. Daniel Chuwa'],
    [4, 'NUR', 'Department of Nursing & Midwifery', 'Dr. Fatuma Ally'],
    [5, 'LAW', 'Department of Law & Human Rights', 'Dr. Baraka Mushi'],
];
$deptIds = [];
foreach ($departments as $i => $d) {
    $deptIds[$i + 1] = ins($pdo, 'departments', [
        'faculty_id' => $fund = $facultyIds[$d[0]],
        'code' => $d[1], 'name' => $d[2], 'hod' => $d[3],
        'email' => strtolower($d[1]) . '@kiut.ac.tz',
        'phone' => '+255 22 555 ' . str_pad((string) (120 + $i), 4, '0', STR_PAD_LEFT),
        'description' => $d[2] . ' at KIUT, coordinating teaching and research in its discipline.',
        'status' => 'active', 'created_at' => ago(-380),
    ]);
}

$programmes = [
    [1, 'BSC-CS', 'BSc in Computer Science', 'Undergraduate', 3],
    [2, 'BSC-IT', 'BSc in Information Technology', 'Undergraduate', 3],
    [3, 'BSC-DS', 'BSc in Data Science', 'Undergraduate', 3],
    [4, 'BAF', 'Bachelor of Accounting & Finance', 'Undergraduate', 3],
    [5, 'BBA', 'Bachelor of Business Administration', 'Undergraduate', 3],
    [6, 'BED', 'Bachelor of Education', 'Undergraduate', 3],
    [7, 'BED-SC', 'Bachelor of Education (Science)', 'Undergraduate', 3],
    [8, 'BPH', 'Bachelor of Public Health', 'Undergraduate', 4],
    [9, 'BNS', 'Bachelor of Nursing', 'Undergraduate', 4],
    [10, 'LLB', 'Bachelor of Laws', 'Undergraduate', 4],
    [1, 'MSC-IS', 'MSc in Information Systems', 'Postgraduate', 2],
    [5, 'MBA', 'Master of Business Administration', 'Postgraduate', 2],
];
$programmeIds = [];
foreach ($programmes as $i => $p) {
    $programmeIds[$i + 1] = ins($pdo, 'programmes', [
        'department_id' => $deptIds[$p[0]], 'code' => $p[1], 'name' => $p[2],
        'level' => $p[3], 'duration_years' => $p[4],
        'description' => $p[2] . ' offered at KIUT with a strong research component.',
        'status' => 'active', 'created_at' => ago(-370),
    ]);
}

$yearIds = [];
foreach ([['2023/2024', -760, -395, 0], ['2024/2025', -395, -30, 0], ['2025/2026', -30, 335, 1]] as $i => $y) {
    $yearIds[$i + 1] = ins($pdo, 'academic_years', [
        'name' => $y[0], 'start_date' => date('Y-m-d', strtotime(($y[1] >= 0 ? '+' : '') . $y[1] . ' days')),
        'end_date' => date('Y-m-d', strtotime(($y[2] >= 0 ? '+' : '') . $y[2] . ' days')),
        'is_current' => $y[3], 'status' => 'active', 'created_at' => ago(-800),
    ]);
}
$semesterRows = [
    [1, 'Semester I', -760, -580, 0], [1, 'Semester II', -579, -395, 0],
    [2, 'Semester I', -395, -215, 0], [2, 'Semester II', -214, -30, 0],
    [3, 'Semester I', -30, 150, 1], [3, 'Semester II', 151, 335, 0],
];
foreach ($semesterRows as $s) {
    ins($pdo, 'semesters', [
        'academic_year_id' => $yearIds[$s[0]], 'name' => $s[1],
        'start_date' => date('Y-m-d', strtotime(($s[2] >= 0 ? '+' : '') . $s[2] . ' days')),
        'end_date' => date('Y-m-d', strtotime(($s[3] >= 0 ? '+' : '') . $s[3] . ' days')),
        'is_current' => $s[4], 'status' => 'active', 'created_at' => ago(-800),
    ]);
}
out('Faculties, departments, programmes and academic calendar seeded', 'ok');

/* ---------------------------------------------------------------
 | Users
 --------------------------------------------------------------- */
$userRows = [
    ['Dr. Amina Mwakyusa', 'amina', 'admin@kiut.ac.tz', 'Admin@123', 'admin', 1, 1, '+255 754 100 001'],
    ['Dr. Emmanuel Kileo', 'emmanuel', 'research@kiut.ac.tz', 'Research@123', 'research_officer', 1, 1, '+255 754 100 002'],
    ['Dr. Neema Kessy', 'neema', 'dean@kiut.ac.tz', 'Dean@123', 'dean', 1, 1, '+255 754 100 003'],
    ['Dr. Joseph Mbwana', 'joseph', 'joseph.mbwana@kiut.ac.tz', 'Dean@123', 'dean', 2, 5, '+255 754 100 004'],
    ['Dr. Grace Ndunguru', 'grace', 'hod@kiut.ac.tz', 'Hod@123', 'hod', 3, 6, '+255 754 100 005'],
    ['Dr. Baraka Mushi', 'baraka', 'baraka.mushi@kiut.ac.tz', 'Hod@123', 'hod', 5, 10, '+255 754 100 006'],
    ['Dr. Salma Juma', 'salma', 'salma.juma@kiut.ac.tz', 'Super@123', 'supervisor', 1, 1, '+255 754 100 007'],
    ['Dr. Peter Msami', 'peter', 'peter.msami@kiut.ac.tz', 'Super@123', 'supervisor', 1, 2, '+255 754 100 008'],
    ['Dr. Rehema Mkwawa', 'rehema', 'rehema.mkwawa@kiut.ac.tz', 'Super@123', 'supervisor', 2, 4, '+255 754 100 009'],
    ['Dr. Daniel Chuwa', 'daniel', 'daniel.chuwa@kiut.ac.tz', 'Super@123', 'supervisor', 4, 8, '+255 754 100 010'],
    ['Mr. Hamisi Ally', 'hamisi', 'hamisi.ally@kiut.ac.tz', 'Super@123', 'supervisor', 1, 3, '+255 754 100 011'],
    ['Dr. Zawadi Mushi', 'zawadi', 'zawadi@kiut.ac.tz', 'Super@123', 'supervisor', 3, 7, '+255 754 100 012'],
    ['Aisha Mwinyi', 'aisha', 'student@kiut.ac.tz', 'Student@123', 'student', 1, 1, '+255 754 100 013'],
    ['Brian Kimaro', 'brian', 'brian.kimaro@kiut.ac.tz', 'Student@123', 'student', 1, 2, '+255 754 100 014'],
    ['Cynthia Mushi', 'cynthia', 'cynthia.mushi@kiut.ac.tz', 'Student@123', 'student', 2, 5, '+255 754 100 015'],
    ['Daudi Nyoni', 'daudi', 'daudi.nyoni@kiut.ac.tz', 'Student@123', 'student', 3, 6, '+255 754 100 016'],
    ['Esther Mkwawa', 'esther', 'esther.mkwawa@kiut.ac.tz', 'Student@123', 'student', 4, 8, '+255 754 100 017'],
    ['Faraja Msuya', 'faraja', 'faraja.msuya@kiut.ac.tz', 'Student@123', 'student', 1, 1, '+255 754 100 018'],
    ['Grace Mwansasu', 'grace2', 'grace.mwansasu@kiut.ac.tz', 'Student@123', 'student', 1, 2, '+255 754 100 019'],
    ['Dr. Halima Said', 'halima', 'finance@kiut.ac.tz', 'Finance@123', 'finance', 2, 4, '+255 754 100 020'],
    ['Dr. Ibrahim Mtenga', 'ibrahim', 'judge@kiut.ac.tz', 'Judge@123', 'judge', 1, 1, '+255 754 100 021'],
    ['Dr. Janet Mwakyusa', 'janet', 'janet.mwakyusa@kiut.ac.tz', 'Judge@123', 'judge', 1, 2, '+255 754 100 022'],
];
$userIds = [];
foreach ($userRows as $i => $u) {
    $userIds[$i + 1] = ins($pdo, 'users', [
        'name' => $u[0], 'username' => $u[1], 'email' => $u[2],
        'password_hash' => hash_password($u[3]), 'role' => $u[4],
        'faculty_id' => $facultyIds[$u[5]], 'department_id' => $deptIds[$u[6]],
        'phone' => $u[7], 'status' => 'active',
        'last_login_at' => ago(-mt_rand(0, 6), '14:20:00'),
        'created_at' => ago(-mt_rand(200, 400)),
    ]);
}
$supervisorIds = array_values(array_intersect_key($userIds, array_flip([7, 8, 9, 10, 11, 12])));
out('22 user accounts created', 'ok');

/* ---------------------------------------------------------------
 | Students registry
 --------------------------------------------------------------- */
$studentNames = [
    'Aisha Mwinyi', 'Brian Kimaro', 'Cynthia Mushi', 'Daudi Nyoni', 'Esther Mkwawa',
    'Faraja Msuya', 'Grace Mwansasu', 'Hassan Mbarouk', 'Irene Mwakalinga', 'Juma Rashid',
    'Kelvin Mhando', 'Lulu Salum', 'Meshack Mwita', 'Neema Mushi', 'Oscar Mwenda',
];
$studentIds = [];
foreach ($studentNames as $i => $name) {
    $parts = explode(' ', $name);
    $deptKey = $i < 7 ? $deptIds[[1, 2, 5, 6, 8, 1, 2][$i]] : $deptIds[mt_rand(1, 10)];
    $facultyKey = (int) array_search($deptKey, $deptIds, true);
    $facultyKey = (int) ((int) array_search($deptKey, $deptIds, true));
    // resolve faculty from department row
    $facultyId = (int) $pdo->query('SELECT faculty_id FROM departments WHERE id = ' . (int) $deptKey)->fetchColumn();
    $programmeKey = $programmeIds[mt_rand(1, 10)];
    $studentIds[$i + 1] = ins($pdo, 'students', [
        'user_id' => $i < 7 ? $userIds[13 + $i] : null,
        'reg_no' => 'KIUT/2022/' . str_pad((string) (101 + $i), 4, '0', STR_PAD_LEFT),
        'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
        'email' => strtolower(str_replace(' ', '.', $name)) . '@kiut.ac.tz',
        'phone' => '+255 76' . mt_rand(1000000, 9999999),
        'programme_id' => $programmeKey, 'department_id' => $deptKey,
        'faculty_id' => $facultyId, 'level' => $i === 9 ? 'Postgraduate' : 'Undergraduate',
        'enrollment_year' => 2022 + ($i % 3), 'status' => 'active',
        'created_at' => ago(-mt_rand(200, 700)),
    ]);
}
out('15 student records created', 'ok');

/* ---------------------------------------------------------------
 | Research projects, proposals, milestones, documents
 --------------------------------------------------------------- */
$projectTitles = [
    'Machine learning model for early detection of crop disease in Tanzania',
    'A mobile money framework for rural financial inclusion',
    'Digital records management adoption in district hospitals',
    'Cloud-based examination scheduling for higher learning institutions',
    'Assessing e-learning readiness among secondary school teachers',
    'Predicting student performance using academic analytics',
    'Solid waste management practices in Dar es Salaam markets',
    'Cyber security awareness in Tanzanian SMEs',
    'Blockchain for academic certificate verification',
    'Influence of microfinance on women-led enterprises',
    'Curriculum alignment with 21st century skills in teacher colleges',
    'Prevalence of hypertension among university staff',
    'Telemedicine acceptance in rural health facilities',
    'Financial literacy and household saving behaviour',
    'Natural language processing for Swahili sentiment analysis',
    'Water quality assessment of community boreholes',
    'Human rights awareness among informal sector workers',
    'Supply chain digitisation in the agricultural sector',
    'Remote patient monitoring using low-cost IoT devices',
    'Gender representation in secondary school leadership',
    'Mobile application for medication adherence among TB patients',
    'Effect of tax incentives on SME growth',
    'Digital marketing adoption by tourism operators',
    'Occupational health hazards in construction sites',
    'Smart irrigation scheduling using soil sensors',
    'Student mental health support services assessment',
    'Electronic procurement compliance in public institutions',
    'Land tenure disputes and access to justice',
    'Biogas adoption among peri-urban households',
    'Learning analytics dashboard for lecturers',
    'Clinical waste handling in private clinics',
    'Mobile-based agricultural extension services',
    'Impact of cooperative societies on member incomes',
    'Machine translation for Swahili-English legal texts',
];
$statuses = array_merge(
    array_fill(0, 2, 'draft'), array_fill(0, 3, 'proposed'), array_fill(0, 4, 'approved'),
    array_fill(0, 9, 'active'), array_fill(0, 7, 'completed'), array_fill(0, 2, 'rejected'),
    array_fill(0, 1, 'suspended')
);
shuffle($statuses);

$projectIds = [];
$projectMeta = [];
foreach ($projectTitles as $i => $title) {
    $studentId = $studentIds[($i % 15) + 1];
    $student = $pdo->query('SELECT department_id, faculty_id FROM students WHERE id = ' . $studentId)->fetch(PDO::FETCH_ASSOC);
    $supervisorId = $supervisorIds[$i % count($supervisorIds)];
    $status = $statuses[$i] ?? 'active';
    $startOffset = -mt_rand(30, 420);
    $duration = mt_rand(150, 420);
    $endOffset = $startOffset + $duration;
    if ($status === 'active' && $i % 9 === 0) {
        $endOffset = -mt_rand(4, 40); // a handful of overdue active projects
    }
    $progress = match ($status) {
        'completed' => 100,
        'active' => mt_rand(35, 90),
        'approved' => mt_rand(10, 30),
        'proposed' => 5,
        'rejected' => 0,
        'suspended' => mt_rand(20, 60),
        default => 0,
    };
    $projectIds[$i + 1] = ins($pdo, 'research_projects', [
        'code' => 'URIMS/RS/' . date('Y') . '/' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
        'title' => $title,
        'abstract' => 'This study investigates ' . lcfirst($title) . '. The research applies a mixed-methods design, combining structured surveys with key informant interviews, and is supervised within the ' . ($student['department_id'] ? 'department' : 'faculty') . ' research programme at KIUT.',
        'faculty_id' => $student['faculty_id'], 'department_id' => $student['department_id'],
        'student_id' => $studentId, 'supervisor_id' => $supervisorId,
        'academic_year_id' => $yearIds[3],
        'status' => $status, 'progress' => $progress,
        'start_date' => date('Y-m-d', strtotime($startOffset . ' days')),
        'end_date' => date('Y-m-d', strtotime($endOffset . ' days')),
        'budget' => money_rand(1_500_000, 18_000_000),
        'created_at' => date('Y-m-d H:i:s', strtotime($startOffset . ' days 10:00')),
    ]);
    $projectMeta[] = ['id' => $projectIds[$i + 1], 'student' => $studentId, 'supervisor' => $supervisorId, 'dept' => $student['department_id'], 'status' => $status];
}
out('34 research projects created', 'ok');

$proposalStatuses = ['draft', 'submitted', 'under_review', 'approved', 'rejected'];
foreach ($projectMeta as $i => $p) {
    if ($i >= 26) {
        continue;
    }
    $status = $p['status'] === 'proposed' ? 'submitted'
        : ($p['status'] === 'approved' ? 'approved'
        : ($p['status'] === 'rejected' ? 'rejected'
        : ($p['status'] === 'active' || $p['status'] === 'completed' ? 'approved' : pick(['draft', 'submitted', 'under_review']))));
    $submittedOffset = -mt_rand(20, 300);
    ins($pdo, 'proposals', [
        'project_id' => $p['id'],
        'title' => $projectTitles[$i],
        'abstract' => 'Proposal submitted for review covering background, objectives, methodology, expected outputs and a detailed work plan.',
        'submitted_by' => $p['student'], 'supervisor_id' => $p['supervisor'],
        'department_id' => $p['dept'],
        'budget' => money_rand(1_000_000, 15_000_000),
        'status' => $status,
        'submitted_at' => $status === 'draft' ? null : date('Y-m-d H:i:s', strtotime($submittedOffset . ' days 11:30')),
        'decision_at' => in_array($status, ['approved', 'rejected'], true) ? date('Y-m-d H:i:s', strtotime(($submittedOffset + mt_rand(3, 20)) . ' days 15:00')) : null,
        'comments' => in_array($status, ['approved', 'rejected', 'under_review'], true)
            ? pick(['Methodology is sound. Proceed with data collection.', 'Clarify sampling strategy before approval.', 'Budget justification requires revision.', 'Approved with minor editorial corrections.'])
            : null,
        'created_at' => date('Y-m-d H:i:s', strtotime($submittedOffset . ' days 09:00')),
    ]);
}
out('26 proposals created', 'ok');

$milestoneTemplates = [
    ['Proposal defence', 40], ['Literature review chapter', 90],
    ['Data collection and fieldwork', 160], ['Final report submission', 240],
];
foreach (array_slice($projectMeta, 0, 16) as $p) {
    foreach ($milestoneTemplates as $mIndex => $m) {
        $dueOffset = -mt_rand(5, 200) + ($mIndex * 20);
        $done = $p['status'] === 'completed' || ($mIndex < 2 && $p['status'] === 'active');
        ins($pdo, 'milestones', [
            'project_id' => $p['id'], 'title' => $m[0],
            'description' => $m[0] . ' milestone for the project, reviewed and signed off by the assigned supervisor.',
            'due_date' => date('Y-m-d', strtotime($dueOffset . ' days')),
            'completed_date' => $done ? date('Y-m-d', strtotime(($dueOffset - mt_rand(0, 5)) . ' days')) : null,
            'status' => $done ? 'completed' : (strtotime($dueOffset . ' days') < time() ? 'overdue' : 'pending'),
            'created_at' => ago(-mt_rand(100, 300)),
        ]);
    }
}
out('64 milestones created', 'ok');

$documentTypes = ['Proposal', 'Progress Report', 'Ethics Clearance', 'Data Collection Tool', 'Final Thesis', 'Supervisor Feedback'];
foreach (array_slice($projectMeta, 0, 12) as $p) {
    for ($k = 0; $k < 2; $k++) {
        ins($pdo, 'documents', [
            'project_id' => $p['id'],
            'title' => $documentTypes[($p['id'] + $k) % count($documentTypes)] . ' – project ' . $p['id'],
            'document_type' => $documentTypes[($p['id'] + $k) % count($documentTypes)],
            'file_path' => null,
            'file_size' => mt_rand(120, 4800) * 1024,
            'uploaded_by' => $k === 0 ? $p['student'] : $p['supervisor'],
            'version' => $k === 0 ? '1.0' : '1.1',
            'status' => 'active', 'created_at' => ago(-mt_rand(5, 160)),
        ]);
    }
}
out('24 project documents recorded', 'ok');

/* ---------------------------------------------------------------
 | Evaluations
 --------------------------------------------------------------- */
$recommendations = ['approved', 'minor_revision', 'major_revision', 'approved'];
foreach (array_slice($projectMeta, 0, 18) as $p) {
    $a = mt_rand(18, 28);
    $b = mt_rand(18, 30);
    $c = mt_rand(15, 25);
    ins($pdo, 'evaluations', [
        'project_id' => $p['id'], 'evaluator_id' => $p['supervisor'],
        'criteria_score' => $a, 'methodology_score' => $b, 'presentation_score' => $c,
        'total_score' => $a + $b + $c,
        'recommendation' => pick($recommendations),
        'comments' => 'Assessment covers clarity of objectives, methodological rigour and quality of presentation. ' . pick([
            'Student demonstrates strong grasp of the research problem.',
            'Improve referencing and consistency of the literature review.',
            'Fieldwork plan is realistic and ethically sound.',
            'Recommend a follow-up defence before final submission.',
        ]),
        'evaluated_at' => ago(-mt_rand(1, 120)),
        'created_at' => ago(-mt_rand(1, 120)),
    ]);
}
out('18 project evaluations created', 'ok');

/* ---------------------------------------------------------------
 | Institutional research
 --------------------------------------------------------------- */
$lecturerTitles = [
    'Adaptive learning pathways in Tanzanian universities',
    'Renewable energy adoption in off-grid schools',
    'Data governance in public health information systems',
    'Agri-finance innovation and smallholder productivity',
    'Inclusive education policy implementation review',
    'Antimicrobial stewardship in referral hospitals',
    'Digital transformation of cooperative societies',
    'Legal aid and access to justice for women',
    'Swahili corpus development for NLP research',
    'Urban mobility and air quality assessment',
    'Teacher professional development and learner outcomes',
    'Supply chain resilience in East African ports',
];
$lecturerResearchIds = [];
foreach ($lecturerTitles as $i => $title) {
    $lecturer = $supervisorIds[$i % count($supervisorIds)];
    $dept = (int) $pdo->query('SELECT department_id FROM users WHERE id = ' . $lecturer)->fetchColumn();
    $lecturerResearchIds[$i + 1] = ins($pdo, 'lecturer_research', [
        'lecturer_id' => $lecturer, 'title' => $title,
        'field' => pick(['Computing', 'Public Health', 'Education', 'Business', 'Law', 'Engineering']),
        'department_id' => $dept,
        'start_date' => date('Y-m-d', strtotime(-mt_rand(60, 500) . ' days')),
        'end_date' => date('Y-m-d', strtotime(mt_rand(-90, 300) . ' days')),
        'budget' => money_rand(3_000_000, 45_000_000),
        'status' => pick(['ongoing', 'ongoing', 'ongoing', 'completed', 'approved']),
        'description' => 'Institutional research project led by academic staff, contributing to KIUT research output and community impact.',
        'created_at' => ago(-mt_rand(60, 500)),
    ]);
}

foreach ([
    ['Digital Health Research Group', 10, 'Telemedicine and health informatics', 7],
    ['Applied Data Science Lab', 7, 'Machine learning and analytics', 9],
    ['Sustainable Agriculture Cluster', 11, 'Agri-tech and food systems', 6],
    ['Education Innovation Unit', 12, 'Pedagogy and learning technology', 8],
    ['Legal Aid Research Network', 6, 'Access to justice and policy', 5],
    ['Public Health Surveillance Team', 10, 'Epidemiology and community health', 11],
] as $t) {
    ins($pdo, 'research_teams', [
        'name' => $t[0], 'leader_id' => $userIds[$t[1]],
        'department_id' => $deptIds[mt_rand(1, 10)],
        'focus_area' => $t[2], 'members_count' => $t[3],
        'status' => 'active', 'created_at' => ago(-mt_rand(90, 500)),
    ]);
}

$journals = ['Tanzania Journal of Science', 'KIUT Journal of Research & Innovation', 'East African Health Review', 'African Journal of Information Systems', 'Journal of Education & Practice'];
for ($i = 0; $i < 20; $i++) {
    $project = $projectMeta[array_rand($projectMeta)];
    ins($pdo, 'publications', [
        'project_id' => mt_rand(0, 1) ? $project['id'] : null,
        'department_id' => $project['dept'],
        'author_id' => $project['supervisor'],
        'title' => $projectTitles[array_rand($projectTitles)],
        'authors' => 'Kileo, E.; Juma, S.; ' . pick(['Mwakyusa, A.', 'Msami, P.', 'Chuwa, D.', 'Mushi, Z.']),
        'journal' => pick($journals),
        'publication_type' => pick(['Journal Article', 'Journal Article', 'Conference Paper', 'Book Chapter']),
        'publication_year' => mt_rand(2022, 2026),
        'doi' => '10.5555/kiut.' . mt_rand(10000, 99999),
        'indexation' => pick(['Scopus', 'Google Scholar', 'AJOL', 'Web of Science']),
        'status' => pick(['published', 'published', 'published', 'under_review']),
        'created_at' => ago(-mt_rand(10, 700)),
    ]);
}
out('Institutional research, teams and publications seeded', 'ok');

/* ---------------------------------------------------------------
 | Innovation
 --------------------------------------------------------------- */
$innovationTitles = [
    'SmartUshauri: AI advisory chatbot for smallholder farmers',
    'CampusPay: unified student wallet and fees platform',
    'MediTrack: cold-chain vaccine monitoring kit',
    'EcoBriquette: agricultural waste fuel enterprise',
    'KilimoSense: soil moisture sensor network',
    'LegalNaMsaada: mobile legal aid directory',
    'TakaTrack: smart waste collection routing',
    'AfyaLink: community health referral app',
    'SomaBora: offline learning content kit',
    'MajiSafe: household water quality test strips',
];
$stages = ['idea', 'prototype', 'pilot', 'scaling', 'launched'];
$innovationIds = [];
foreach ($innovationTitles as $i => $title) {
    $innovationIds[$i + 1] = ins($pdo, 'innovation_projects', [
        'title' => $title,
        'description' => 'Student-led innovation addressing a community challenge, developed through the KIUT innovation incubation programme.',
        'leader_id' => $userIds[13 + ($i % 7)],
        'department_id' => $deptIds[($i % 10) + 1],
        'stage' => $stages[$i % count($stages)],
        'status' => pick(['active', 'active', 'active', 'completed', 'draft']),
        'created_at' => ago(-mt_rand(20, 300)),
    ]);
}
foreach (range(1, 8) as $i) {
    ins($pdo, 'innovation_teams', [
        'innovation_project_id' => $innovationIds[$i],
        'name' => 'Team ' . pick(['Kijani', 'Ubunifu', 'Tumaini', 'Neema', 'Zamani', 'Sote']) . ' ' . $i,
        'leader_id' => $userIds[13 + ($i % 7)],
        'members_count' => mt_rand(3, 8),
        'role_focus' => pick(['Product development', 'Business development', 'Field testing', 'Community engagement']),
        'status' => 'active', 'created_at' => ago(-mt_rand(10, 200)),
    ]);
}

$competitionIds = [];
foreach ([
    ['KIUT Innovation Week 2025', 'Solutions for community health', 'KIUT Main Campus, Dar es Salaam', -180, -174, 'completed'],
    ['National Research & Innovation Symposium 2026', 'Research translation into enterprise', 'KIUT Convention Centre', -12, -10, 'completed'],
    ['KIUT Startup Challenge 2026', 'Student ventures for sustainable cities', 'KIUT Innovation Hub', 35, 37, 'open'],
    ['East Africa Innovation Expo 2026', 'Cross-border collaboration', 'Nelson Mandela Stadium', 120, 123, 'open'],
] as $i => $c) {
    $competitionIds[$i + 1] = ins($pdo, 'competitions', [
        'name' => $c[0], 'theme' => $c[1], 'venue' => $c[2],
        'start_date' => date('Y-m-d', strtotime(($c[3] >= 0 ? '+' : '') . $c[3] . ' days')),
        'end_date' => date('Y-m-d', strtotime(($c[4] >= 0 ? '+' : '') . $c[4] . ' days')),
        'status' => $c[5],
        'description' => 'Competition brings together student innovators, judges and industry partners to evaluate early-stage ventures.',
        'created_at' => ago(-mt_rand(60, 300)),
    ]);
}
$judgeIds = [];
foreach (range(1, 7) as $i) {
    $judgeUser = $i <= 2 ? $userIds[20 + $i] : $supervisorIds[($i - 3) % count($supervisorIds)];
    $judgeIds[$i] = ins($pdo, 'judges', [
        'user_id' => $judgeUser, 'competition_id' => $competitionIds[($i % 4) + 1],
        'expertise' => pick(['Product innovation', 'Business modelling', 'Public health', 'Data science', 'Intellectual property']),
        'organization' => pick(['KIUT Innovation Hub', 'Tanzania Commission for Science and Technology', 'Buni Hub', 'Industry partner']),
        'status' => 'active', 'created_at' => ago(-mt_rand(30, 200)),
    ]);
}
for ($i = 0; $i < 14; $i++) {
    $a = mt_rand(20, 30);
    $b = mt_rand(18, 30);
    $c = mt_rand(20, 30);
    ins($pdo, 'evaluation_results', [
        'competition_id' => $competitionIds[($i % 2) + 1],
        'innovation_project_id' => $innovationIds[($i % 10) + 1],
        'judge_id' => $judgeIds[($i % 7) + 1],
        'innovation_score' => $a, 'feasibility_score' => $b, 'impact_score' => $c,
        'total_score' => $a + $b + $c,
        'rank_position' => ($i % 5) + 1,
        'comments' => pick(['Strong prototype with clear market need.', 'Promising team, refine unit economics.', 'High community impact potential.', 'Needs stronger intellectual property strategy.']),
        'status' => pick(['submitted', 'submitted', 'verified']),
        'awarded_at' => $i < 5 ? ago(-mt_rand(5, 60)) : null,
        'created_at' => ago(-mt_rand(2, 60)),
    ]);
}
out('Innovation projects, teams, competitions and results seeded', 'ok');

/* ---------------------------------------------------------------
 | Finance
 --------------------------------------------------------------- */
$sources = ['KIUT Research Fund', 'Commission for Science & Technology', 'Industry Partner Grant', 'Development Partner Fund', 'Internal Faculty Allocation'];
foreach (array_slice($projectMeta, 0, 18) as $i => $p) {
    $amount = money_rand(2_000_000, 40_000_000);
    $status = pick(['pending', 'pending', 'approved', 'approved', 'rejected']);
    ins($pdo, 'funding_applications', [
        'reference' => 'FUND/' . date('Y') . '/' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
        'project_id' => $p['id'], 'applicant_id' => $p['supervisor'],
        'amount_requested' => $amount, 'funding_source' => pick($sources),
        'purpose' => 'Fieldwork, data collection, equipment and dissemination for the approved research project.',
        'status' => $status,
        'submitted_at' => ago(-mt_rand(5, 200)),
        'decision_at' => $status === 'pending' ? null : ago(-mt_rand(1, 100)),
        'remarks' => $status === 'rejected' ? 'Insufficient budget justification.' : ($status === 'approved' ? 'Approved subject to quarterly reporting.' : null),
        'created_at' => ago(-mt_rand(5, 200)),
    ]);
}

$budgetCategories = ['Equipment & consumables', 'Fieldwork & data collection', 'Publication fees', 'Travel & conferences', 'Software & licences', 'Personnel support'];
$budgetIds = [];
foreach (array_slice($projectMeta, 0, 22) as $i => $p) {
    foreach ((array) array_slice($budgetCategories, $i % 3, 2) as $cat) {
        $budgetIds[] = ins($pdo, 'budgets', [
            'project_id' => $p['id'], 'category' => $cat,
            'allocated_amount' => money_rand(800_000, 12_000_000),
            'fiscal_year' => pick(['2024/2025', '2025/2026']),
            'status' => pick(['approved', 'approved', 'approved', 'pending']),
            'notes' => 'Allocation approved by the research finance committee.',
            'created_at' => ago(-mt_rand(10, 260)),
        ]);
    }
}
foreach ($budgetIds as $i => $budgetId) {
    $budgetAmount = (float) $pdo->query('SELECT allocated_amount FROM budgets WHERE id = ' . $budgetId)->fetchColumn();
    $rows = mt_rand(1, 3);
    for ($k = 0; $k < $rows; $k++) {
        ins($pdo, 'expenditure', [
            'budget_id' => $budgetId,
            'description' => pick(['Purchase of field survey tablets', 'Transport for data collection', 'Printing and stationery', 'Laboratory consumables', 'Publication processing fee', 'Workshop refreshments']),
            'amount' => round($budgetAmount * (mt_rand(5, 25) / 100), -3),
            'spent_on' => date('Y-m-d', strtotime(-mt_rand(5, 200) . ' days')),
            'recorded_by' => $userIds[20],
            'status' => pick(['approved', 'approved', 'approved', 'pending']),
            'created_at' => ago(-mt_rand(5, 200)),
        ]);
    }
}
out('Funding applications, budgets and expenditure seeded', 'ok');

/* ---------------------------------------------------------------
 | Notifications, audit logs, settings
 --------------------------------------------------------------- */
$notificationTemplates = [
    ['Proposal awaiting your review', 'A research proposal has been submitted and requires your decision.', 'warning', 'modules/proposals.php'],
    ['Milestone deadline approaching', 'A project milestone is due within the next seven days.', 'info', 'modules/milestones.php'],
    ['Funding application approved', 'Your funding application has been approved by the research finance committee.', 'success', 'modules/funding_applications.php'],
    ['New supervisor feedback', 'Your supervisor has submitted evaluation feedback on your progress report.', 'info', 'modules/evaluations.php'],
    ['Competition results published', 'Evaluation results for the innovation competition are now available.', 'success', 'modules/evaluation_results.php'],
    ['Document uploaded', 'A new project document has been uploaded to the repository.', 'info', 'modules/documents.php'],
    ['Overdue project alert', 'A research project has passed its planned end date without completion.', 'danger', 'modules/research_projects.php'],
];
foreach ($userIds as $uid) {
    $count = mt_rand(2, 4);
    for ($i = 0; $i < $count; $i++) {
        $t = $notificationTemplates[array_rand($notificationTemplates)];
        ins($pdo, 'notifications', [
            'user_id' => $uid, 'title' => $t[0], 'message' => $t[1], 'type' => $t[2],
            'link' => $t[3], 'is_read' => mt_rand(0, 1), 'created_at' => ago(-mt_rand(0, 20)),
        ]);
    }
}

$auditSamples = [
    ['create', 'research_projects', 'Created research project record'],
    ['update', 'proposals', 'Approved a research proposal'],
    ['login', 'authentication', 'Signed in successfully'],
    ['update', 'budgets', 'Updated budget allocation'],
    ['create', 'publications', 'Recorded a new publication'],
    ['delete', 'documents', 'Removed an outdated document version'],
    ['update', 'users', 'Updated user account permissions'],
    ['create', 'innovation_projects', 'Registered an innovation project'],
    ['update', 'expenditure', 'Recorded project expenditure'],
    ['logout', 'authentication', 'Signed out'],
];
for ($i = 0; $i < 40; $i++) {
    $a = $auditSamples[array_rand($auditSamples)];
    ins($pdo, 'audit_logs', [
        'user_id' => $userIds[array_rand($userIds)], 'action' => $a[0], 'module' => $a[1],
        'description' => $a[2], 'ip_address' => '192.168.1.' . mt_rand(10, 240),
        'created_at' => ago(-mt_rand(0, 30), sprintf('%02d:%02d:00', mt_rand(8, 18), mt_rand(0, 59))),
    ]);
}

foreach ([
    'site_name' => 'URIMS',
    'site_full_name' => 'University Research & Innovation Management System',
    'institution' => 'Kampala International University in Tanzania (KIUT)',
    'support_email' => 'research@kiut.ac.tz',
    'research_office_email' => 'research.office@kiut.ac.tz',
    'financial_year' => '2025/2026',
    'default_currency' => 'TZS',
    'records_per_page' => '10',
    'submission_notice' => 'Proposals must be submitted at least two weeks before the departmental defence window.',
] as $key => $value) {
    ins($pdo, 'settings', ['setting_key' => $key, 'setting_value' => $value]);
}

if (!is_dir(ROOT_PATH . '/uploads')) {
    mkdir(ROOT_PATH . '/uploads', 0775, true);
}

out('Notifications, audit trail and settings seeded', 'ok');
} // end of demo data seeding

if (!is_dir(ROOT_PATH . '/uploads')) {
    mkdir(ROOT_PATH . '/uploads', 0775, true);
}

if ($seedDemoData) {
    out('Installation complete. Sign in with admin@kiut.ac.tz / Admin@123', 'ok');
} else {
    out('Schema ready. Existing data and accounts were preserved.', 'ok');
}

if (!$isCli) {
    echo '</pre>';
}
