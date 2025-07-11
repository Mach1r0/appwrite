<?php
/**
 * DEMONSTRAÇÃO DA CORREÇÃO APLICADA PARA O BUG #9067
 * 
 * ANTES da correção (BUG):
 * - Sistema aceitava credenciais SMTP inválidas
 * - SMTPAuth não era configurado
 * - Validação de autenticação não funcionava
 * 
 * DEPOIS da correção (CORRIGIDO):
 * - Sistema rejeita credenciais SMTP inválidas
 * - SMTPAuth configurado condicionalmente
 * - Validação de autenticação funciona corretamente
 */

// CÓDIGO ANTES DA CORREÇÃO (BUGGY):
/*
if ($enabled) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Host = $host;
    $mail->Port = $port;
    // BUG: SMTPAuth not set, so invalid credentials are not validated
    $mail->SMTPSecure = $secure;
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = 5;

    try {
        $valid = $mail->SmtpConnect();
        if (!$valid) {
            throw new Exception('Connection is not valid.');
        }
    } catch (Throwable $error) {
        throw new Exception(Exception::PROJECT_SMTP_CONFIG_INVALID, 'Could not connect to SMTP server: ' . $error->getMessage());
    }
}
*/

// CÓDIGO APÓS A CORREÇÃO (FIXED):
if ($enabled) {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Host = $host;
    $mail->Port = $port;
    $mail->SMTPAuth = (!empty($username) && !empty($password)); // ← CORREÇÃO APLICADA
    $mail->SMTPSecure = $secure;
    $mail->SMTPAutoTLS = false;
    $mail->Timeout = 5;

    try {
        $valid = $mail->SmtpConnect();
        if (!$valid) {
            throw new Exception('Connection is not valid.');
        }
    } catch (Throwable $error) {
        throw new Exception(Exception::PROJECT_SMTP_CONFIG_INVALID, 'Could not connect to SMTP server: ' . $error->getMessage());
    }
}

/**
 * EXPLICAÇÃO DA CORREÇÃO:
 * 
 * A linha adicionada:
 * $mail->SMTPAuth = (!empty($username) && !empty($password));
 * 
 * Faz com que:
 * 1. Se username E password estiverem preenchidos → SMTPAuth = true
 * 2. Se username OU password estiverem vazios → SMTPAuth = false
 * 
 * Isso garante que:
 * - Credenciais inválidas sejam rejeitadas (quando SMTPAuth = true)
 * - Servidores abertos continuem funcionando (quando SMTPAuth = false)
 * - Compatibilidade seja mantida com configurações existentes
 */

/**
 * CENÁRIOS DE TESTE COBERTOS:
 * 
 * 1. testRejectInvalidSmtpCredentials
 *    → Credenciais inválidas são rejeitadas
 *    → Status 400 retornado
 *    → Mensagem de erro apropriada
 * 
 * 2. testAcceptSmtpWithoutCredentials  
 *    → Servidores abertos funcionam
 *    → Status 200 retornado
 *    → Configuração salva com sucesso
 * 
 * 3. testAcceptValidSmtpCredentials
 *    → Credenciais válidas aceitas
 *    → Status 200 retornado
 *    → Todas as configurações salvas
 * 
 * 4. testValidateRequiredSmtpFields
 *    → Campos obrigatórios validados
 *    → Mensagens de erro específicas
 *    → Status 400 para campos ausentes
 */
