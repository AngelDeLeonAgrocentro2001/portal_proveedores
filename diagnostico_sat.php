<?php
// test_nit_count.php
require_once 'config/config.php';
require_once 'database/DatabaseCajas.php';

$nit = '35111704'; // El NIT a buscar

try {
    $db = DatabaseCajas::getInstance()->getPdo();
    
    // 1. Conteo exacto
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM dte WHERE nit_emisor = ?");
    $stmt->execute([$nit]);
    $exacto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "🔍 NIT buscado: " . $nit . "<br>";
    echo "📊 Facturas con NIT exacto: " . $exacto['total'] . "<br>";
    
    // 2. Mostrar primeras 10 para verificar
    $stmt = $db->prepare("SELECT serie, numero_dte, fecha_emision, gran_total, usado FROM dte WHERE nit_emisor = ? LIMIT 10");
    $stmt->execute([$nit]);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Primeras 10 facturas:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Serie</th><th>Número</th><th>Fecha</th><th>Total</th><th>Usado</th></tr>";
    foreach ($facturas as $f) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($f['serie']) . "</td>";
        echo "<td>" . htmlspecialchars($f['numero_dte']) . "</td>";
        echo "<td>" . htmlspecialchars($f['fecha_emision']) . "</td>";
        echo "<td>" . htmlspecialchars($f['gran_total']) . "</td>";
        echo "<td>" . htmlspecialchars($f['usado']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>