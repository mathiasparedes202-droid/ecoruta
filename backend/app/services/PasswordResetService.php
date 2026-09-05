<?php

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Repositories\PasswordResetRepository;
use Config\App;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class PasswordResetService
{
    private UsuarioRepository $usuarioRepo;
    private PasswordResetRepository $resetRepo;

    public function __construct()
    {
        $this->usuarioRepo = new UsuarioRepository();
        $this->resetRepo = new PasswordResetRepository();
    }

    /**
     * Inicia el proceso de reseteo de contraseña
     */
    public function sendResetLink(string $email): ?string
    {
        // Verificar si el usuario existe
        $usuario = $this->usuarioRepo->findByEmail($email);
        if (!$usuario) {
            // Por seguridad, no revelamos si el email existe o no
            error_log("Intento de reseteo de contraseña para email no registrado: $email");
            // revelar que el email no existe puede ser un vector de ataque, así que solo logueamos y devolvemos éxito genérico
            return null;
        }

        // Generar token único
        $token = bin2hex(random_bytes(32));

        // Expiración en 1 hora
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Guardar token
        if (!$this->resetRepo->createToken($email, $token, $expiresAt)) {
            throw new Exception('Error al guardar el token');
        }

        $this->sendResetEmail($email, $token);

        // En desarrollo, devolver la URL para testing
        if (\Config\App::isDev()) {
            $frontendUrl = \Config\App::env('FRONTEND_URL', 'http://localhost:5173');
            return $frontendUrl . "/reset-password?token=" . $token;
        }

        return null;
    }

    /**
     * Verifica y obtiene el token válido
     */
    public function verifyToken(string $token): ?array
    {
        return $this->resetRepo->findValidToken($token);
    }

    /**
     * Resetea la contraseña usando el token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $resetData = $this->verifyToken($token);
        if (!$resetData) {
            throw new Exception('Token inválido o expirado');
        }

        // Obtener usuario
        $usuario = $this->usuarioRepo->findByEmail($resetData['email']);
        if (!$usuario) {
            throw new Exception('Usuario no encontrado');
        }

        // Actualizar contraseña
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->updatePassword($usuario->id_usuario, $hashedPassword);

        // Eliminar token
        $this->resetRepo->deleteToken($token);

        return true;
    }

    /**
     * Actualiza la contraseña del usuario
     */
    private function updatePassword(int $userId, string $hashedPassword): void
    {
        $db = (new \Config\Database())->connect();
        $stmt = $db->prepare("UPDATE usuario SET password = :password WHERE id_usuario = :id");
        $stmt->execute(['password' => $hashedPassword, 'id' => $userId]);
    }

    /**
     * Envía el email de reseteo usando PHPMailer.
     */
    private function sendResetEmail(string $email, string $token): void
    {
        $frontendUrl = App::env('FRONTEND_URL', 'http://localhost:5173');
        $resetUrl = $frontendUrl . "/reset-password?token=" . $token;

        $mailUsername = App::env('MAIL_USERNAME');
        $mailPassword = App::env('MAIL_PASSWORD');
        if (empty($mailUsername) || empty($mailPassword)) {
            error_log("No hay credenciales SMTP configuradas. URL de reseteo para $email: $resetUrl");
            return;
        }

        $subject = App::env('MAIL_SUBJECT_PASSWORD_RESET', 'Recuperación de Contraseña - Floracia');
        $message = "Hola,\r\n\r\n" .
            "Has solicitado restablecer tu contraseña.\r\n\r\n" .
            "Haz clic en el siguiente enlace para restablecer tu contraseña:\r\n" .
            "$resetUrl\r\n\r\n" .
            "Este enlace expirará en 1 hora.\r\n\r\n" .
            "Si no solicitaste este cambio, ignora este email.\r\n\r\n" .
            "Saludos,\r\n" .
            "Equipo de Floracia\r\n";

        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = App::env('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $mailUsername;
            $mail->Password = $mailPassword;
            $mail->SMTPSecure = App::env('MAIL_ENCRYPTION', 'tls');
            $mail->Port = App::env('MAIL_PORT', 587);

            // Remitente
            $fromAddress = App::env('MAIL_FROM_ADDRESS', $mailUsername);
            $mail->setFrom($fromAddress, App::env('MAIL_FROM_NAME', 'FloreriaApp'));
            $mail->addReplyTo(App::env('MAIL_REPLY_TO_ADDRESS', $fromAddress));

            // Destinatario
            $mail->addAddress($email);

            // Contenido
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->send();
            error_log("Email de reseteo enviado exitosamente a $email");
        } catch (PHPMailerException $e) {
            error_log("Error al enviar email: {$mail->ErrorInfo} - {$e->getMessage()}");
            throw new Exception('No se pudo enviar el email de recuperación');
        }
    }
}
