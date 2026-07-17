<?php
// app/models/TransporteAPIModel.php
require_once BASE_PATH . 'config/config.php';

class TransporteAPIModel {
    private $apiBaseUrl = 'https://agrotransporte.agrocentro.site/api/';
    
    /**
     * Obtener viajes no pagados para una empresa (CardCode)
     */
    public function getViajesPendientes($cardCode) {
        $url = $this->apiBaseUrl . 'companies-trips-unpaid';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['CardCode' => trim($cardCode)]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para entorno local, cambiar a true en producción
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("TransporteAPI Error: " . $curlError);
            return ['success' => false, 'message' => "Error de conexión: $curlError"];
        }
        
        if ($httpCode !== 200) {
            error_log("TransporteAPI HTTP $httpCode: $response");
            return ['success' => false, 'message' => "La API respondió con código $httpCode"];
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['success'])) {
            return ['success' => false, 'message' => 'Respuesta inválida de la API'];
        }
        
        return $data;
    }
    
    /**
     * Marcar viajes como pagados
     */
    public function marcarViajesPagados($cardCode, $tripIds, $date, $total, $serie, $numeroFactura) {
        $url = $this->apiBaseUrl . 'companies-trips-mark-paid';

        $payload = [
            'CardCode' => trim($cardCode),
            'trip_ids' => $tripIds,
            'date'     => $date,
            'total'    => (float)$total,
            'serie'    => $serie,
            'number'   => $numeroFactura,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("TransporteAPI MarkPaid Error: " . $curlError);
            return ['success' => false, 'message' => "Error de conexión: $curlError"];
        }

        if ($httpCode !== 200) {
            error_log("TransporteAPI MarkPaid HTTP $httpCode: $response");
            return ['success' => false, 'message' => "La API respondió con código $httpCode"];
        }
        
        $data = json_decode($response, true);

        error_log("TransporteAPI MarkPaid response (HTTP $httpCode): " . $response);

        if (!$data) {
            return ['success' => false, 'message' => 'Respuesta inválida de la API: ' . substr($response, 0, 200)];
        }

        return $data;
    }
}