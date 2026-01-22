<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Response;

trait SweetAlertTrait
{
    /**
     * Agrega un mensaje flash de éxito
     */
    protected function addSuccessFlash(string $message): void
    {
        $this->addFlash('success', $message);
    }

    /**
     * Agrega un mensaje flash de error
     */
    protected function addErrorFlash(string $message): void
    {
        $this->addFlash('error', $message);
    }

    /**
     * Agrega un mensaje flash de advertencia
     */
    protected function addWarningFlash(string $message): void
    {
        $this->addFlash('warning', $message);
    }

    /**
     * Agrega un mensaje flash de información
     */
    protected function addInfoFlash(string $message): void
    {
        $this->addFlash('info', $message);
    }

    /**
     * Retorna una respuesta JSON para SweetAlert2
     */
    protected function jsonSweetAlert(string $type, string $message, array $options = []): Response
    {
        $data = [
            'success' => $type === 'success',
            'type' => $type,
            'message' => $message,
            'swal' => true
        ];

        if (isset($options['redirect'])) {
            $data['redirect'] = $options['redirect'];
        }

        if (isset($options['timer'])) {
            $data['timer'] = $options['timer'];
        }

        return $this->json($data);
    }

    /**
     * Respuesta JSON de éxito con SweetAlert2
     */
    protected function jsonSuccess(string $message, array $options = []): Response
    {
        return $this->jsonSweetAlert('success', $message, $options);
    }

    /**
     * Respuesta JSON de error con SweetAlert2
     */
    protected function jsonError(string $message, array $options = []): Response
    {
        return $this->jsonSweetAlert('error', $message, $options);
    }

    /**
     * Respuesta JSON de advertencia con SweetAlert2
     */
    protected function jsonWarning(string $message, array $options = []): Response
    {
        return $this->jsonSweetAlert('warning', $message, $options);
    }

    /**
     * Respuesta JSON de información con SweetAlert2
     */
    protected function jsonInfo(string $message, array $options = []): Response
    {
        return $this->jsonSweetAlert('info', $message, $options);
    }

    /**
     * Respuesta JSON para errores de validación
     */
    protected function jsonValidationErrors(array $errors, string $message = 'Por favor corrija los errores indicados'): Response
    {
        return $this->json([
            'success' => false,
            'type' => 'validation',
            'message' => $message,
            'errors' => $errors,
            'swal' => true
        ]);
    }
}
