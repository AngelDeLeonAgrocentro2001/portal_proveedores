<?php
// app/models/SAPServiceLayer.php
require_once BASE_PATH . 'database/DatabaseSAP.php';

class SAPServiceLayer {
    private $baseUrl = 'https://192.168.1.9:50000/b1s/v1/';
    private $companyDB = 'T_GT_AGROCENTRO_2016';
    private $username = 'manager';
    private $password = 'Team64110';
    private $cookieFile = null;
    private $sessionId = null;
    private $routeId = null;
    private $isLoggedIn = false;
    
    public function __construct() {
        $this->cookieFile = BASE_PATH . 'temp/sap_cookie_' . session_id() . '.txt';
        if (!is_dir(dirname($this->cookieFile))) {
            mkdir(dirname($this->cookieFile), 0755, true);
        }
    }
    
    /**
     * Login a SAP Service Layer
     */
    public function login() {
        // Si ya hay sesión, no volver a loguear
        if ($this->isLoggedIn && file_exists($this->cookieFile) && filesize($this->cookieFile) > 0) {
            error_log("SAP Login - Usando sesión existente");
            return ['success' => true, 'sessionId' => $this->sessionId];
        }
        
        $curl = curl_init();
        
        $postData = json_encode([
            "UserName" => $this->username,
            "Password" => $this->password,
            "CompanyDB" => $this->companyDB
        ]);
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $this->baseUrl . 'Login',
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postData)
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        
        error_log("=== SAP LOGIN DEBUG ===");
        error_log("URL: " . $this->baseUrl . 'Login');
        error_log("HTTP Code: $httpCode");
        error_log("CURL Error: " . ($curlError ?: "None"));
        error_log("Cookie File: " . $this->cookieFile);
        error_log("======================");
        
        curl_close($curl);
        
        if ($curlError) {
            return ['success' => false, 'error' => "cURL Error: $curlError"];
        }
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            return ['success' => false, 'error' => "HTTP $httpCode - " . substr($response, 0, 200)];
        }
        
        $data = json_decode($response, true);
        if (isset($data['SessionId'])) {
            $this->sessionId = $data['SessionId'];
            $this->routeId = $data['RouteId'] ?? '.guid';
            $this->isLoggedIn = true;
            error_log("SAP Login - SessionId: " . $this->sessionId);
            return ['success' => true, 'sessionId' => $this->sessionId];
        }
        
        return ['success' => false, 'error' => 'No SessionId in response'];
    }
    
    /**
     * Logout de SAP Service Layer
     */
    public function logout() {
        if (!$this->isLoggedIn) {
            return true;
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $this->baseUrl . 'Logout',
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->isLoggedIn = false;
        
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
        
        return $httpCode === 204;
    }
    
    /**
     * Crear una Purchase Invoice en SAP
     * @param array $invoiceData Datos de la factura
     * @return array Respuesta de SAP
     */
    public function createPurchaseInvoice($invoiceData) {
        // Login para obtener sesión
        $login = $this->login();
        if (!$login['success']) {
            return ['success' => false, 'error' => 'Error de conexión con SAP: ' . ($login['error'] ?? 'Unknown')];
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . 'PurchaseInvoices';
        
        $jsonPayload = json_encode($invoiceData, JSON_UNESCAPED_UNICODE);
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache",
                "Accept: application/json",
                'Content-Length: ' . strlen($jsonPayload)
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        
        error_log("SAP CreatePurchaseInvoice - HTTP Code: $httpCode");
        error_log("SAP CreatePurchaseInvoice - URL: $url");
        error_log("SAP CreatePurchaseInvoice - Response: " . substr($response, 0, 500));
        
        curl_close($curl);
        
        if ($response === false || $curlError) {
            return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
        }
        
        $sapResponse = json_decode($response, true);
        
        // Si la sesión expiró (401), reintentar una vez
        if ($httpCode == 401) {
            error_log("SAP CreatePurchaseInvoice - Sesión expirada, reintentando...");
            $this->isLoggedIn = false;
            if (file_exists($this->cookieFile)) {
                unlink($this->cookieFile);
            }
            
            // Reintentar
            $login = $this->login();
            if ($login['success']) {
                return $this->createPurchaseInvoice($invoiceData);
            }
            return ['success' => false, 'error' => 'Error de sesión en SAP'];
        }
        
        if ($httpCode >= 400) {
            $errorMsg = $sapResponse['error']['message']['value'] ?? 
                        ($sapResponse['error']['message'] ?? 'Error desconocido en SAP');
            return ['success' => false, 'error' => $errorMsg, 'httpCode' => $httpCode, 'response' => $sapResponse];
        }
        
        return [
            'success' => true,
            'docEntry' => $sapResponse['DocEntry'] ?? null,
            'docNum' => $sapResponse['DocNum'] ?? null,
            'response' => $sapResponse
        ];
    }
    
    /**
     * Buscar un proveedor por NIT en SAP
     * @param string $nit NIT del proveedor
     * @return array|null Datos del proveedor o null
     */
    public function findBusinessPartnerByNIT($nit) {
        $login = $this->login();
        if (!$login['success']) {
            return null;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners?\$filter=U_NIT eq '$nit'";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        $this->logout();
        
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['value'][0])) {
                return $data['value'][0];
            }
        }
        
        return null;
    }

    /**
     * Obtener un Business Partner por CardCode
     */
    public function getBusinessPartner($cardCode) {
        $login = $this->login();
        if (!$login['success']) {
            return null;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners('$cardCode')";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->logout();
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    }

    /**
     * Obtener un Business Partner por NIT (campo U_NIT)
     */
    public function getBusinessPartnerByNIT($nit) {
        $login = $this->login();
        if (!$login['success']) {
            return null;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners?\$filter=U_NIT eq '$nit'";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->logout();
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['value'][0])) {
                return $data['value'][0];
            }
        }
        
        return null;
    }

    /**
     * Crear un nuevo Business Partner en SAP
     */
    public function createBusinessPartner($bpData) {
        $login = $this->login();
        if (!$login['success']) {
            return null;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_POSTFIELDS => json_encode($bpData),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->logout();
        
        if ($httpCode === 201 && $response) {
            return json_decode($response, true);
        }
        
        error_log("createBusinessPartner - Error: HTTP $httpCode - $response");
        return null;
    }

    public function updateBusinessPartner($cardCode, $data) {
        $login = $this->login();
        if (!$login['success']) {
            error_log("updateBusinessPartner - Login failed");
            return false;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners('$cardCode')";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $this->logout();
        
        if ($httpCode === 204 || $httpCode === 200) {
            error_log("updateBusinessPartner - Business Partner $cardCode actualizado correctamente");
            return true;
        }
        
        error_log("updateBusinessPartner - Error actualizando: HTTP $httpCode - $response");
        return false;
    }

    /**
     * Actualizar el NIT de un Business Partner
     */
    public function updateBusinessPartnerNIT($cardCode, $nit) {
        $login = $this->login();
        if (!$login['success']) {
            error_log("updateBusinessPartnerNIT - Login failed");
            return false;
        }
        
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners('$cardCode')";
        
        // Obtener el Business Partner actual
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200 || !$response) {
            error_log("updateBusinessPartnerNIT - Business Partner no encontrado: $cardCode");
            curl_close($curl);
            $this->logout();
            return false;
        }
        
        $businessPartner = json_decode($response, true);
        
        // ACTUALIZAR TODOS los campos relacionados con NIT
        $updateData = [];
        
        // Campo estándar de SAP
        if (isset($businessPartner['FederalTaxID'])) {
            $updateData['FederalTaxID'] = $nit;
            error_log("updateBusinessPartnerNIT - Actualizando FederalTaxID a: $nit");
        }
        
        // Campo de usuario U_NIT (importante para validación)
        if (isset($businessPartner['U_NIT'])) {
            $updateData['U_NIT'] = $nit;
            error_log("updateBusinessPartnerNIT - Actualizando U_NIT a: $nit");
        }
        
        // También actualizar LicTradNum si existe
        if (isset($businessPartner['LicTradNum'])) {
            $updateData['LicTradNum'] = $nit;
            error_log("updateBusinessPartnerNIT - Actualizando LicTradNum a: $nit");
        }
        
        if (empty($updateData)) {
            error_log("updateBusinessPartnerNIT - No se encontró campo para NIT");
            curl_close($curl);
            $this->logout();
            return false;
        }
        
        // Ejecutar PATCH
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => json_encode($updateData),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Cache-Control: no-cache",
                "Accept: application/json"
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        $this->logout();
        
        if ($httpCode === 204 || $httpCode === 200) {
            error_log("updateBusinessPartnerNIT - NIT actualizado correctamente a: $nit");
            return true;
        }
        
        error_log("updateBusinessPartnerNIT - Error actualizando: HTTP $httpCode - $response");
        if ($curlError) {
            error_log("updateBusinessPartnerNIT - CURL Error: $curlError");
        }
        return false;
    }

    private function getBusinessPartnerWithSession($cardCode) {
        $curl = curl_init();
        $url = $this->baseUrl . "BusinessPartners('$cardCode')";
        
        curl_setopt_array($curl, [
            CURLOPT_PORT => 50000,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    }
}
?>