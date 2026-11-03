<?php
declare(strict_types=1);

// Sørg for at API'et altid svarer med ren JSON, også hvis PHP vil skrive en advarsel
ini_set('display_errors', '0');
ob_start();

require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $code, string $msg): never
{
    ob_clean();
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if ($user === null) {
    fail(401, 'Du er ikke logget ind.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    fail(400, 'Ugyldig forespørgsel. Genindlæs siden.');
}

$action = (string) ($_POST['action'] ?? '');

try {
    $state = with_user_data($user, function (array &$data) use ($action) {
        switch ($action) {
            case 'start':
                if ($data['running'] === null) {
                    $data['running']      = time();
                    $data['running_note'] = clean_note((string) ($_POST['note'] ?? ''));
                }
                break;
            case 'setnote':
                if ($data['running'] !== null) {
                    $data['running_note'] = clean_note((string) ($_POST['note'] ?? ''));
                }
                break;
            case 'stop':
                if ($data['running'] !== null) {
                    $note = isset($_POST['note'])
                        ? clean_note((string) $_POST['note'])
                        : (string) ($data['running_note'] ?? '');
                    add_entry($data, (int) $data['running'], time(), $note);
                    $data['running']      = null;
                    $data['running_note'] = '';
                }
                break;
            case 'editnote':
                $id   = (string) ($_POST['id'] ?? '');
                $note = clean_note((string) ($_POST['note'] ?? ''));
                foreach ($data['entries'] as &$e) {
                    if ($e['id'] === $id) {
                        $e['note'] = $note;
                    }
                }
                unset($e);
                break;
            case 'delete':
                $id = (string) ($_POST['id'] ?? '');
                $data['entries'] = array_values(array_filter(
                    $data['entries'],
                    fn($e) => $e['id'] !== $id
                ));
                break;
            case 'status':
                break;
            default:
                fail(400, 'Ukendt handling.');
        }
        return build_state($data);
    });
} catch (Throwable $t) {
    error_log('api.php: ' . $t->getMessage());
    fail(500, $t instanceof RuntimeException
        ? $t->getMessage()
        : 'Der skete en fejl på serveren.');
}

ob_clean();
echo json_encode($state);
