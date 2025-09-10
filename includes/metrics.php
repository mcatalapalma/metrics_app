<?php
// includes/metrics.php
declare(strict_types=1);

// Reutiliza tu conexión
require_once __DIR__ . '/../config/db.php'; // $pdo

function list_cities(PDO $pdo): array {
    // Si tienes tabla cities, úsala; si no, derivamos de datos
    $sql = "SELECT DISTINCT city FROM courier_metrics WHERE city IS NOT NULL AND city <> '' ORDER BY city";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

function fetch_kpis(PDO $pdo, string $from, string $to, ?string $city): array {
    $where = "metric_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];
    if ($city && $city !== 'ALL') {
        $where .= " AND city = :city";
        $params[':city'] = $city;
    }

    // Media ponderada de tiempo (por pedidos) y de km/hours simples
    $sql = "
      SELECT
        COALESCE(SUM(orders),0) AS orders_sum,
        COALESCE(SUM(tips),0)   AS tips_sum,
        COALESCE(SUM(km),0)     AS km_sum,
        COALESCE(SUM(hours),0)  AS hours_sum,
        CASE WHEN COALESCE(SUM(orders),0) = 0
           THEN NULL
           ELSE SUM(avg_delivery_time_min * orders) / NULLIF(SUM(orders),0)
        END AS avg_time_w
      FROM courier_metrics
      WHERE $where
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch() ?: [];
}

function fetch_trend(PDO $pdo, string $from, string $to, ?string $city): array {
    $where = "metric_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];
    if ($city && $city !== 'ALL') {
        $where .= " AND city = :city";
        $params[':city'] = $city;
    }
    $sql = "
      SELECT metric_date,
             SUM(orders) AS orders_sum,
             SUM(tips)   AS tips_sum,
             CASE WHEN COALESCE(SUM(orders),0)=0
                  THEN NULL
                  ELSE SUM(avg_delivery_time_min * orders) / NULLIF(SUM(orders),0)
             END AS avg_time_w
      FROM courier_metrics
      WHERE $where
      GROUP BY metric_date
      ORDER BY metric_date
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function fetch_top_couriers(PDO $pdo, string $from, string $to, ?string $city, int $limit = 10): array {
    $where = "cm.metric_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];
    if ($city && $city !== 'ALL') {
        $where .= " AND cm.city = :city";
        $params[':city'] = $city;
    }
    // Intentamos nombre del repartidor si el ID coincide; si no, mostramos el user_id
    $sql = "
      SELECT
        cm.user_id,
        COALESCE(CONCAT(r.nombre, ' ', r.apellido), CONCAT('ID ', cm.user_id)) AS nombre,
        SUM(cm.orders) AS orders_sum,
        SUM(cm.tips)   AS tips_sum,
        CASE WHEN COALESCE(SUM(cm.orders),0)=0
             THEN NULL
             ELSE SUM(cm.avg_delivery_time_min * cm.orders) / NULLIF(SUM(cm.orders),0)
        END AS avg_time_w
      FROM courier_metrics cm
      LEFT JOIN repartidores r ON r.id = cm.user_id
      WHERE $where
      GROUP BY cm.user_id, r.nombre, r.apellido
      ORDER BY orders_sum DESC
      LIMIT $limit
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function parse_date_or_default(?string $v, string $fallback): string {
    // Espera Y-m-d; si viene vacío o inválido, usa fallback
    $d = DateTime::createFromFormat('Y-m-d', (string)$v);
    if (!$d) return $fallback;
    return $d->format('Y-m-d');
}
