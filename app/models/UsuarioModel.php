<?php
// app/models/UsuarioModel.php
require_once BASE_PATH . 'database/DatabasePortal.php';

class UsuarioModel {
    private $pdo;

    public function __construct() {
        $this->pdo = DatabasePortal::getInstance()->getPdo();
    }

   // app/models/UsuarioModel.php

public function login($cardcode, $email, $password) {
    $stmt = $this->pdo->prepare("
        SELECT u.id, u.cardcode, u.email, u.username, u.rol, u.password,
               p.nombre, p.nit, p.dias_credito 
        FROM usuarios u 
        JOIN proveedores p ON u.cardcode = p.cardcode 
        WHERE u.cardcode = ? AND u.email = ? AND p.estado = 'activo'
    ");
    $stmt->execute([$cardcode, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Devolver solo los datos necesarios, SIN el password
        return [
            'id' => $user['id'],
            'cardcode' => $user['cardcode'],
            'email' => $user['email'],
            'username' => $user['username'], // ← Este es el campo crítico
            'rol' => $user['rol'],
            'nombre' => $user['nombre'],
            'nit' => $user['nit'],
            'dias_credito' => $user['dias_credito']
        ];
    }
    
    return false;
}

    // Crear nuevo usuario (solo admin)
    public function crearUsuario($cardcode, $email, $username, $password, $rol) {
        $rolesValidos = ['admin', 'consultas', 'crear_contrasenas'];
        if (!in_array($rol, $rolesValidos)) {
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare("
            INSERT INTO usuarios (cardcode, email, username, password, rol)
            VALUES (?, ?, ?, ?, ?)
        ");

        try {
            return $stmt->execute([$cardcode, $email, $username, $hashedPassword, $rol]);
        } catch (PDOException $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return false;
        }
    }

    // Verificar si el usuario tiene permiso para una acción
    public function tienePermiso($user, $accion) {
        $rol = $user['rol'] ?? 'crear_contrasenas';

        switch ($accion) {
            case 'ver_pagos':
                return in_array($rol, ['admin', 'consultas']);
            case 'reportar_factura':
                return true; // todos los roles pueden reportar
            case 'admin_usuarios':
                return $rol === 'admin';
            default:
                return false;
        }
    }

        // Obtener todos los usuarios de un cardcode (para admin)
    public function getUsuariosByCardcode($cardcode) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, email, rol, fecha_creacion 
            FROM usuarios 
            WHERE cardcode = ? 
            ORDER BY fecha_creacion DESC
        ");
        $stmt->execute([$cardcode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // app/models/UsuarioModel.php

// Obtener usuario sin necesidad de password (para refrescar sesión)
public function getUserByCardcodeAndEmail($cardcode, $email) {
    $stmt = $this->pdo->prepare("
        SELECT u.id, u.cardcode, u.email, u.username, u.rol,
               p.nombre, p.nit, p.dias_credito 
        FROM usuarios u 
        JOIN proveedores p ON u.cardcode = p.cardcode 
        WHERE u.cardcode = ? AND u.email = ? AND p.estado = 'activo'
    ");
    $stmt->execute([$cardcode, $email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}