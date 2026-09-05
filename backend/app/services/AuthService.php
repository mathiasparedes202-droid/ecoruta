<?php

namespace App\Services;

use Config\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\App;

class AuthService
{
    private $db;

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    /**
     * Verifica las credenciales del usuario
     *
     * @param string $email
     * @param string $password
     * @return array|null Devuelve usuario si es correcto, null si falla
     */
    public function attempt(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE email = :email AND estado = 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // No enviar contraseña
            return $user;
        }

        return null;
    }

    /**
     * Genera un JWT para un usuario
     *
     * @param array $user
     * @return string Token JWT
     */
    public function generateToken(array $user): string
    {
        $payload = [
            'iss' => App::url(),          // Emisor
            'iat' => time(),              // Timestamp actual
            'exp' => time() + App::jwtExpiration(), // Expiración
            'sub' => $user['id_usuario'],         // Subject = id usuario
            'rol' => $user['id_rol']      // rol del usuario
        ];

        return JWT::encode($payload, App::jwtSecret(), 'HS256');
    }

    /**
     * Decodifica y valida un JWT
     *
     * @param string $token
     * @return object|null Payload decodificado o null si inválido
     */
    public function decodeToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key(App::jwtSecret(), 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
    }
}