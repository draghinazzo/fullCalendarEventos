<?php
// DEBUG: descomenta para ver errores durante pruebas (en hosting puede estar bloqueado)
 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Responder preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$servername = "sql202.ezyro.com";
$username   = "ezyro_39613828";
$password   = "8410a42";
$dbname     = "ezyro_39613828_proyectos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status"=>"error","error" => "Error de conexión: " . $conn->connect_error]);
    exit();
}

function json_fail($code, $msg, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(["status"=>"error","error"=>$msg], $extra));
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    if ($action === 'read' || $action === null) {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT `id`,`title`,`start`,`end`,`allDay`,`url`,`description`,
                            `backgroundColor`,`borderColor`,`textColor`,`groupId`,
                            `extendedProps`,`file`
                    FROM `eventos` WHERE `id` = $id";
        } elseif (isset($_GET['start']) && isset($_GET['end'])) {
            $start = $conn->real_escape_string($_GET['start']);
            $end   = $conn->real_escape_string($_GET['end']);
            $sql = "SELECT `id`,`title`,`start`,`end`,`allDay`,`url`,`description`,
                           `backgroundColor`,`borderColor`,`textColor`,`groupId`,
                           `extendedProps`,`file`
                    FROM `eventos`
                    WHERE (`start` BETWEEN '$start' AND '$end')
                       OR (`end`   BETWEEN '$start' AND '$end')
                       OR (`start` <= '$start' AND `end` >= '$end')";
        } else {
            $sql = "SELECT `id`,`title`,`start`,`end`,`allDay`,`url`,`description`,
                           `backgroundColor`,`borderColor`,`textColor`,`groupId`,
                           `extendedProps`,`file`
                    FROM `eventos`";
        }

        $result = $conn->query($sql);
        if (!$result) {
            json_fail(500, "Error al leer eventos", ["mysql"=>$conn->error, "sql"=>$sql]);
        }

        $eventos = [];
        while ($row = $result->fetch_assoc()) {
            $eventos[] = [
                "id"            => $row["id"],
                "title"         => $row["title"],
                "start"         => $row["start"],
                "end"           => $row["end"],
                "allDay"        => (bool)$row["allDay"],
                "url"           => $row["url"],
                "description"   => $row["description"],
                "backgroundColor"=> $row["backgroundColor"],
                "borderColor"   => $row["borderColor"],
                "textColor"     => $row["textColor"],
                "groupId"       => $row["groupId"],
                "extendedProps" => $row["extendedProps"] ? json_decode($row["extendedProps"], true) : null,
                "file"          => $row["file"]
            ];
        }
        echo json_encode($eventos);
        exit();
    } else {
        json_fail(400, "Acción no válida");
    }
}

elseif ($method === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    // --- subir archivo ---
    function subirArchivo($fileInputName) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
            $tmpName  = $_FILES[$fileInputName]['tmp_name'];
            $fileName = basename($_FILES[$fileInputName]['name']);
            $fileName = time() . "_" . preg_replace("/[^a-zA-Z0-9_\.-]/", "_", $fileName);
            $destPath = $uploadDir . $fileName;
            if (move_uploaded_file($tmpName, $destPath)) {
                return $fileName;
            }
        }
        return null;
    }

    $title          = isset($_POST["title"]) ? $conn->real_escape_string($_POST["title"]) : null;
    $start          = isset($_POST["start"]) ? $conn->real_escape_string($_POST["start"]) : null;
    $end            = isset($_POST["end"]) ? $conn->real_escape_string($_POST["end"]) : null;
    $allDay         = isset($_POST["allDay"]) ? (int)$_POST["allDay"] : 0;
    $url            = isset($_POST["url"]) ? $conn->real_escape_string($_POST["url"]) : null;
    $description    = isset($_POST["description"]) ? $conn->real_escape_string($_POST["description"]) : null;
    $backgroundColor= isset($_POST["backgroundColor"]) ? $conn->real_escape_string($_POST["backgroundColor"]) : null;
    $borderColor    = isset($_POST["borderColor"]) ? $conn->real_escape_string($_POST["borderColor"]) : null;
    $textColor      = isset($_POST["textColor"]) ? $conn->real_escape_string($_POST["textColor"]) : null;
    $groupId        = isset($_POST["groupId"]) ? $conn->real_escape_string($_POST["groupId"]) : null;
    // Si te llega JSON como string, NO volver a json_encode.
    $extendedProps  = isset($_POST["extendedProps"])
                      ? (is_array($_POST["extendedProps"]) ? json_encode($_POST["extendedProps"]) : $_POST["extendedProps"])
                      : null;

    $archivo = subirArchivo('file');

    if ($action === 'create') {
        $sql = "INSERT INTO `eventos`
        (`title`,`start`,`end`,`allDay`,`url`,`description`,`backgroundColor`,
         `borderColor`,`textColor`,`groupId`,`extendedProps`,`file`)
        VALUES (
            ".($title ? "'$title'" : "NULL").",
            ".($start ? "'$start'" : "NULL").",
            ".($end   ? "'$end'"   : "NULL").",
            $allDay,
            ".($url ? "'$url'" : "NULL").",
            ".($description ? "'$description'" : "NULL").",
            ".($backgroundColor ? "'$backgroundColor'" : "NULL").",
            ".($borderColor ? "'$borderColor'" : "NULL").",
            ".($textColor ? "'$textColor'" : "NULL").",
            ".($groupId ? "'$groupId'" : "NULL").",
            ".($extendedProps ? "'".$conn->real_escape_string($extendedProps)."'" : "NULL").",
            ".($archivo ? "'$archivo'" : "NULL")."
        )";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status"=>"success","message"=>"Evento creado correctamente","id"=>$conn->insert_id]);
        } else {
            json_fail(500, "Error al crear evento", ["mysql"=>$conn->error, "sql"=>$sql]);
        }
        exit();
    }

    elseif ($action === 'update' && isset($_GET['id'])) {
        $id = intval($_GET['id']);

        // Si hay archivo nuevo, elimina el anterior
        if ($archivo) {
            $qryOld = $conn->query("SELECT `file` FROM `eventos` WHERE `id`=$id");
            if ($qryOld) {
                $rowOld = $qryOld->fetch_assoc();
                if (!empty($rowOld['file'])) {
                    $oldPath = __DIR__ . '/uploads/' . $rowOld['file'];
                    if (is_file($oldPath)) { @unlink($oldPath); }
                }
            }
        }

        $updateFields = [
            "`title`=" . ($title ? "'$title'" : "NULL"),
            "`start`=" . ($start ? "'$start'" : "NULL"),
            "`end`="   . ($end   ? "'$end'"   : "NULL"),
            "`allDay`=$allDay",
            "`url`=" . ($url ? "'$url'" : "NULL"),
            "`description`=" . ($description ? "'$description'" : "NULL"),
            "`backgroundColor`=" . ($backgroundColor ? "'$backgroundColor'" : "NULL"),
            "`borderColor`=" . ($borderColor ? "'$borderColor'" : "NULL"),
            "`textColor`=" . ($textColor ? "'$textColor'" : "NULL"),
            "`groupId`=" . ($groupId ? "'$groupId'" : "NULL"),
            "`extendedProps`=" . ($extendedProps ? "'".$conn->real_escape_string($extendedProps)."'" : "NULL")
        ];

        if ($archivo) {
            $updateFields[] = "`file`='$archivo'";
        }

        $updateFields[] = "`updated_at`=NOW()";

        $sql = "UPDATE `eventos` SET " . implode(", ", $updateFields) . " WHERE `id` = $id";

        if ($conn->query($sql) === TRUE) {
            echo json_encode(["status"=>"success","message"=>"Evento actualizado correctamente"]);
        } else {
            json_fail(500, "Error al actualizar evento", ["mysql"=>$conn->error, "sql"=>$sql]);
        }
        exit();
    }

    else {
        json_fail(400, "Acción no válida o falta ID para update");
    }
}
else {
    json_fail(405, "Método no permitido");
}

$conn->close();
