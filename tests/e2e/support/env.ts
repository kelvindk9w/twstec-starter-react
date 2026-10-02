import { mailpitBaseUrl } from './project-env';

// Credenciais e endereços do E2E (sobreponíveis pelo ambiente). As pessoas
// fixas são criadas por tests/e2e/fixtures.php. O Mailpit é o do PRÓPRIO
// projeto (support/project-env.ts), nunca o de outro ambiente da máquina.

export const userEmail = process.env.E2E_USER_EMAIL ?? 'e2e@example.com';
export const userPassword = process.env.E2E_USER_PASSWORD ?? 'E2eSenhaForte123';
export const adminEmail = process.env.E2E_ADMIN_EMAIL ?? 'admin-e2e@example.com';
export const adminPassword = process.env.E2E_ADMIN_PASSWORD ?? 'E2eAdminSenha123';

// Pessoas fixas SÓ dos testes de "login pela tela" (painel e /admin), com as
// mesmas senhas das de cima. Com o segundo fator obrigatório, cada login
// manda um código por e-mail, e um código novo só sai depois do intervalo de
// reenvio: se esses testes usassem as pessoas acima, que o global-setup
// acabou de logar, o código não sairia.
export const loginUserEmail = process.env.E2E_LOGIN_USER_EMAIL ?? 'login-e2e@example.com';
export const loginAdminEmail = process.env.E2E_LOGIN_ADMIN_EMAIL ?? 'admin-login-e2e@example.com';

// Todas as pessoas fixas (as mensagens delas saem do Mailpit no fim da rodada).
export const fixedPeople = [userEmail, loginUserEmail, adminEmail, loginAdminEmail];

export const mailpitUrl = mailpitBaseUrl;

// Sessões gravadas pelo global-setup.
export const userState = 'tests/e2e/.auth/e2e.json';
export const adminState = 'tests/e2e/.auth/admin.json';

// Senhas das pessoas que os testes criam (e apagam no fim).
export const newPassword = 'SenhaForte123';
export const transactionPassword = 'Transacao9React';
