<?php

namespace App\Controllers;

use App\Application\DTOs\UserResponseDto;
use App\Application\UseCases\LoginUser;
use App\Application\UseCases\LogoutUser;
use App\Application\UseCases\RefreshToken;
use App\Application\UseCases\RegisterUser;
use App\Application\UseCases\ResetPassword;
use App\Application\UseCases\SendPasswordResetCode;
use App\Core\Request;
use App\Core\Response;
use Exception;

class AuthController
{
    public function __construct(
        private LoginUser $loginUseCase,
        private RegisterUser $registerUseCase,
        private RefreshToken $refreshTokenUseCase,
        private LogoutUser $logoutUseCase,
        private SendPasswordResetCode $sendPasswordResetCode,
        private ResetPassword $resetPassword
    ) {}

    public function login(Request $request): void
    {
        $body = $request->body();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            Response::json([], 400, 'fail', 'El correo y la contraseña son requeridos.');
            return;
        }

        try {
            $result = $this->loginUseCase->execute($email, $password);
            Response::json($result, 200, 'success', 'Inicio de sesión exitoso.');
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 401;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function register(Request $request): void
    {
        $body = $request->body();

        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $phone = trim($body['phone'] ?? '');
        $role = trim($body['role'] ?? 'client');

        // Consentimiento explícito de términos y privacidad (FR-03 / NFR-21)
        $rawConsent = $body['consent'] ?? $body['terms_accepted'] ?? false;
        $consent = filter_var($rawConsent, FILTER_VALIDATE_BOOLEAN);

        // Validación de campos obligatorios
        if (empty($email) || empty($password) || empty($phone)) {
            Response::json([], 400, 'fail', 'Los campos email, password y phone son requeridos.');
            return;
        }

        // Comprobar archivo del documento de identidad para KYC (FR-07)
        if (!$request->hasFile('document')) {
            Response::json([], 400, 'fail', 'Debe adjuntar la imagen del documento de identidad (campo "document").');
            return;
        }

        $documentFile = $request->file('document');
        $filePath = $documentFile['tmp_name'];

        try {
            $result = $this->registerUseCase->execute(
                rawEmail: $email,
                rawPassword: $password,
                rawPhone: $phone,
                rawRole: $role,
                termsAccepted: $consent,
                documentFilePath: $filePath
            );

            Response::json($result, 201, 'success', 'Usuario registrado y verificado exitosamente.');
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 422;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function refreshToken(Request $request): void
    {
        $body = $request->body();
        $refreshToken = trim($body['refresh_token'] ?? '');

        if (empty($refreshToken)) {
            Response::json([], 400, 'fail', 'El campo refresh_token es obligatorio.');
            return;
        }

        try {
            $result = $this->refreshTokenUseCase->execute($refreshToken);
            Response::json($result, 200, 'success', 'Token renovado exitosamente.');
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 401;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function logout(Request $request): void
    {
        $body = $request->body();
        $refreshToken = trim($body['refresh_token'] ?? '');

        if (empty($refreshToken)) {
            Response::json([], 400, 'fail', 'El campo refresh_token es obligatorio para cerrar sesión.');
            return;
        }

        try {
            $this->logoutUseCase->execute($refreshToken);
            Response::json([], 200, 'success', 'Sesión cerrada exitosamente.');
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 500;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function me(Request $request): void
    {
        $user = $request->user();

        if (!$user) {
            Response::json([], 401, 'fail', 'Usuario no autenticado.');
            return;
        }

        Response::json(
            UserResponseDto::fromEntity($user)->toArray(),
            200,
            'success',
            'Usuario autenticado.'
        );
    }

    public function forgotPassword(Request $request): void
    {
        $body = $request->body();
        $email = trim($body['email'] ?? '');

        if (empty($email)) {
            Response::json([], 400, 'fail', 'El correo electrónico es requerido.');
            return;
        }

        try {
            $this->sendPasswordResetCode->execute($email);
            Response::json(
                [],
                200,
                'success',
                'Si el correo electrónico está registrado, recibirás un código de recuperación en breve.'
            );
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }

    public function resetPassword(Request $request): void
    {
        $body = $request->body();

        $email = trim($body['email'] ?? '');
        $code = trim($body['code'] ?? '');
        $password = $body['password'] ?? '';
        $passwordConfirmation = $body['password_confirmation'] ?? '';

        if (empty($email) || empty($code) || empty($password)) {
            Response::json([], 400, 'fail', 'Todos los campos son obligatorios: email, code, password y password_confirmation.');
            return;
        }

        try {
            $this->resetPassword->execute(
                rawEmail: $email,
                rawCode: $code,
                newPassword: $password,
                newPasswordConfirmation: $passwordConfirmation
            );

            Response::json(
                [],
                200,
                'success',
                'Tu contraseña ha sido actualizada exitosamente. Inicia sesión con tus nuevas credenciales.'
            );
        } catch (Exception $e) {
            $rawCode = $e->getCode();
            $code = (is_int($rawCode) && $rawCode >= 400 && $rawCode < 500) ? $rawCode : 400;
            Response::json([], $code, 'fail', $e->getMessage());
        }
    }
}
