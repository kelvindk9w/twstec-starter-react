// Credenciais e endereços do E2E (sobreponíveis pelo ambiente). As pessoas
// fixas são criadas por tests/e2e/fixtures.php.

export const userEmail = process.env.E2E_USER_EMAIL ?? 'e2e@example.com';
export const userPassword = process.env.E2E_USER_PASSWORD ?? 'E2eSenhaForte123';
export const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'admin-e2e@example.com';
export const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'E2eAdminSenha123';
export const mailpitUrl = process.env.E2E_MAILPIT_URL ?? 'http://localhost:18025';

// Sessões gravadas pelo global-setup.
export const userState = 'tests/e2e/.auth/e2e.json';
export const adminState = 'tests/e2e/.auth/admin.json';

// Senhas das pessoas que os testes criam (e apagam no fim).
export const newPassword = 'SenhaForte123';
export const transactionPassword = 'Transacao9React';
