<?php

require_once "../../config/db.php";
require_once "../../config/response.php";
require_once "../../config/session.php";

require_login();

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_response(false, "Método no permitido", null, 405);
}

$buscar = trim($_GET["buscar"] ?? "");
$estado = trim($_GET["estado"] ?? "");

$pagina = intval($_GET["page"] ?? 1);
$por_pagina = intval($_GET["per_page"] ?? 10);

if ($pagina < 1) {
    $pagina = 1;
}

if ($por_pagina < 1) {
    $por_pagina = 10;
}

if ($por_pagina > 50) {
    $por_pagina = 50;
}

$offset = ($pagina - 1) * $por_pagina;

try {
    $where = "
        FROM expedientes e
        INNER JOIN clientes c ON c.id = e.cliente_id
        WHERE 1 = 1
        AND e.eliminado = 0
    ";

    $params = [];

    if ($buscar !== "") {
        $where .= "
            AND (
                e.numero_expediente LIKE ?
                OR e.numero_escritura LIKE ?
                OR c.nombre LIKE ?
                OR e.tipo_acto LIKE ?
                OR e.notaria LIKE ?
                OR e.registro_publico LIKE ?
            )
        ";

        $like = "%" . $buscar . "%";

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($estado !== "") {
        $where .= " AND e.estado_actual = ? ";
        $params[] = $estado;
    }

    $sql_total = "SELECT COUNT(*) AS total " . $where;

    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute($params);
    $total = intval($stmt_total->fetch()["total"] ?? 0);

    $total_paginas = (int) ceil($total / $por_pagina);

    $sql = "
        SELECT 
            e.id,
            e.numero_expediente,
            e.numero_escritura,
            e.fecha_escritura,
            e.tipo_acto,
            e.notaria,
            e.municipio,
            e.estado,
            e.registro_publico,
            e.estado_actual,
            e.responsable_actual,
            e.fecha_recepcion,
            e.fecha_cierre,
            e.created_at,
            c.nombre AS cliente_nombre,
            c.telefono AS cliente_telefono
        " . $where . "
        ORDER BY e.id DESC
        LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset) . "
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $expedientes = $stmt->fetchAll();

    json_response(true, "Expedientes obtenidos correctamente", [
        "expedientes" => $expedientes,
        "total" => $total,
        "pagina" => $pagina,
        "por_pagina" => $por_pagina,
        "total_paginas" => $total_paginas
    ]);

} catch (Exception $e) {
    json_response(false, "Error al obtener expedientes", $e->getMessage(), 500);
}