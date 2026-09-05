<?php

namespace App\Repositories;

use Config\Database;
use PDO;

class PasswordResetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    /**
     * Crea un token de reseteo de contraseña
     */
    public function createToken(string $email, string $token, string $expiresAt): bool
    {
        // Primero, eliminar tokens anteriores para este email
        $this->deleteByEmail($email);

        $query = "INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expiresAt);
        return $stmt->execute();
    }

    /**
     * Encuentra un token válido
     */
    public function findValidToken(string $token): ?array
    {
        $query = "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Elimina tokens por email
     */
    public function deleteByEmail(string $email): bool
    {
        $query = "DELETE FROM password_resets WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        return $stmt->execute();
    }

    /**
     * Elimina un token específico
     */
    public function deleteToken(string $token): bool
    {
        $query = "DELETE FROM password_resets WHERE token = :token";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    }
}
