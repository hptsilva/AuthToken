<?php

require __DIR__ . '/vendor/autoload.php';

use AuthToken\Auth;
use AuthToken\Exception\ErrorConnection;
use AuthToken\Exception\SecretNotFound;
use Dotenv\Dotenv;

function print_title(string $title): void
{
    echo "\n\n";
    echo "==========================================================\n";
    echo "=> TESTE: " . strtoupper($title) . "\n";
    echo "==========================================================\n";
}

function print_success(string $message): void
{
    echo "\033[32m[SUCCESS]\033[0m " . $message . "\n";
}

function print_failure(string $message): void
{
    echo "\033[31m[FAILURE]\033[0m " . $message . "\n";
}

function print_info(string $message): void
{
    echo "\033[34m[INFO]\033[0m " . $message . "\n";
}
// --------------------------------------------------------


try {

    $dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/'));
    $dotenv->load();

    $auth = new Auth();
    $userId = 543; // ID de usuário para o teste. Verifique o USER_TYPE
    $tokens = [];
    // ==========================================================
    // 1. TESTE DE LOGIN (CAMINHO DE SUCESSO)
    // ==========================================================
    print_title("Login bem-sucedido");
    $response = $auth->login($userId);
    if ($response['status'] && isset($response['access_token']) && isset($response['refresh_token'])) {
        print_success("Login realizado com sucesso.");
        $tokens = $response;
        print_info("Access Token (curto): " . $tokens['access_token']);
        print_info("Refresh Token (longo): " . $tokens['refresh_token']);
    } else {
        print_failure("O login falhou.");
        exit(1);
    }

    // ==========================================================
    // 2. AUTENTICAÇÃO COM ACCESS TOKEN VÁLIDO (CAMINHO DE SUCESSO)
    // ==========================================================
    print_title("Autenticação com Access Token VÁLIDO");
    $authResponse = $auth->authenticate($tokens['access_token']);
    if ($authResponse['status'] && $authResponse['user_id'] == $userId) {
        print_success("Autenticação bem-sucedida. User ID: " . $authResponse['user_id']);
    } else {
        print_failure("A autenticação com um token válido falhou.");
    }

    // ==========================================================
    // 3. AUTENTICAÇÃO COM ACCESS TOKEN INVÁLIDO (CAMINHO FALHO)
    // ==========================================================
    print_title("Autenticação com Access Token INVÁLIDO");
    $fakeAccessToken = $tokens['access_token'] . 'invalid';
    $authResponse = $auth->authenticate($fakeAccessToken);
    if (!$authResponse['status'] && $authResponse['code'] === 401) {
        print_success("Falha esperada ao autenticar com token inválido. Correto.");
    } else {
        print_failure("O sistema aceitou um token inválido, o que é um erro de segurança.");
    }

    // ==========================================================
    // 4. REFRESH COM REFRESH TOKEN VÁLIDO (CAMINHO DE SUCESSO)
    // ==========================================================
    print_title("Refresh de sessão com Refresh Token VÁLIDO");
    $oldTokens = $tokens;
    $newTokensResponse = $auth->refresh($tokens['refresh_token']);
    if ($newTokensResponse['status'] && isset($newTokensResponse['access_token']) && isset($newTokensResponse['refresh_token'])) {
        print_success("Sessão atualizada com sucesso. Novos tokens foram gerados.");
        $tokens = $newTokensResponse; // Atualiza para os novos tokens
        print_info("NOVO Access Token: " . $tokens['access_token']);
        print_info("NOVO Refresh Token: " . $tokens['refresh_token']);
    } else {
        print_failure("O refresh com um token válido falhou.");
        echo json_encode($newTokensResponse, JSON_PRETTY_PRINT);
    }

    // ==========================================================
    // 5. REFRESH COM REFRESH TOKEN ANTIGO/REVOGADO (CAMINHO FALHO)
    // ==========================================================
    print_title("Tentativa de Refresh com Refresh Token ANTIGO (revogado)");
    $refreshResponse = $auth->refresh($oldTokens['refresh_token']);
    if (!$refreshResponse['status'] && $refreshResponse['code'] === 401) {
        print_success("Falha esperada ao tentar usar um refresh token antigo. Rotação de token funcionando.");
    } else {
        print_failure("O sistema aceitou um refresh token revogado, o que é um erro de segurança.");
    }

    // ==========================================================
    // 6. TESTE DE EXPIRAÇÃO DO ACCESS TOKEN E REFRESH
    // ==========================================================
    print_title("Simulação de expiração do Access Token e Refresh automático");
    print_info("Para este teste, configure ACCESS_TOKEN_TIMEOUT='+1 second' no seu arquivo .env");

    // Gera um novo par de tokens com validade curta
    $shortLivedTokensResponse = $auth->login($userId);
    $shortLivedAccessToken = $shortLivedTokensResponse['access_token'];
    $validRefreshToken = $shortLivedTokensResponse['refresh_token'];

    print_info("Aguardando 2 segundos para o Access Token expirar...");
    sleep(2);

    $authResponse = $auth->authenticate($shortLivedAccessToken);
    if (!$authResponse['status'] && $authResponse['code'] === 401) {
        print_success("Access token expirou como esperado.");

        print_info("Tentando renovar a sessão com o Refresh Token...");
        $refreshedTokensResponse = $auth->refresh($validRefreshToken);
        if ($refreshedTokensResponse['status']) {
            print_success("Sessão renovada com sucesso após expiração.");
            $tokens = $refreshedTokensResponse; // Atualiza para os tokens mais recentes
        } else {
            print_failure("Não foi possível renovar a sessão com um refresh token válido.");
        }
    } else {
        print_failure("O access token não expirou conforme o esperado. Verifique o .env.");
    }

    // ==========================================================
    // 7. LOGOUT (CAMINHO DE SUCESSO)
    // ==========================================================
    print_title("Logout de sessão");
    $logoutResponse = $auth->logout($tokens['refresh_token']);
    if ($logoutResponse['status'] && $logoutResponse['code'] === 200) {
        print_success("Logout realizado com sucesso.");
    } else {
        print_failure("O logout falhou.");
    }

    // ==========================================================
    // 8. REFRESH COM REFRESH TOKEN APÓS LOGOUT (CAMINHO FALHO)
    // ==========================================================
    print_title("Tentativa de Refresh com Refresh Token após LOGOUT");
    $refreshResponse = $auth->refresh($tokens['refresh_token']);
    if (!$refreshResponse['status'] && $refreshResponse['code'] === 401) {
        print_success("Falha esperada ao usar um refresh token após o logout. Sessão invalidada corretamente.");
    } else {
        print_failure("O sistema permitiu o refresh de uma sessão encerrada (logout), o que é um erro de segurança.");
    }

} catch (SecretNotFound | ErrorConnection $e) {
    print_failure("ERRO CRÍTICO DE CONFIGURAÇÃO: " . $e->getMessage());
    exit(1);
}

echo "\n\n\033[32mTODOS OS TESTES FORAM CONCLUÍDOS.\033[0m\n\n";