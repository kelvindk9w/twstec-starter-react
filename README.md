# Starter React

> **Parte do [TWS Laravel Starter Kit](https://github.com/kelvindk9w/tws-laravel-starter-kit).** O código, as issues e os
> pull requests ficam no monorepo
> [kelvindk9w/tws-laravel-starter-kit](https://github.com/kelvindk9w/tws-laravel-starter-kit) (pasta `starters/react`); este
> repositório é o espelho só-leitura publicado a cada versão.
> Documentação: [docs/](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/docs) · Segurança:
> [SECURITY.md](SECURITY.md) · Licença: MIT ([LICENSE](LICENSE)).

O TWS Laravel Starter Kit com o painel do usuário em **React 19 + Inertia 3 +
TypeScript + Tailwind 4 + shadcn/ui**, a partir do
[kit oficial React do Laravel](https://github.com/laravel/react-starter-kit)
— com a autenticação, a segurança e a auditoria dos pacotes `twstec/kit-*`
no lugar das do kit oficial. Composer: `twstec/starter-react` (projeto).

O backend é o mesmo do [starter Livewire](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/starters/livewire): os mesmos pacotes, as
mesmas regras, as mesmas mensagens e o mesmo `/admin` (plugin Filament do
`twstec/kit-admin`). Muda a interface do painel e das telas de autenticação.

Completo: autenticação, painel, perfil, senha de transação, verificação em
duas etapas, notificações, seletor de conta, conta e membros, convites,
chaves de API, projetos e foto de perfil — com E2E próprio (Playwright),
imagem de produção própria e as combinações de módulos no CI.

**Criar um projeto** (Packagist; durante o beta, com `:^2.0@beta`):
`composer create-project "twstec/starter-react:^2.0@beta" meu-app` ou
`laravel new meu-app --using="twstec/starter-react:^2.0@beta"`. O
`post-create-project-cmd` chama o instalador (`php artisan tws:install`),
que pergunta os módulos opcionais num terminal — ver
[docs/instalacao.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/instalacao.md#starter-react).

## Rodar (Docker de desenvolvimento, porta 8181)

O `docker-compose.yml` da **raiz** sobe o React ao lado do Livewire (8180),
com o mesmo PostgreSQL, Redis e Mailpit, mas **banco próprio**
(`tws_starter_react`), bancos próprios no Redis e cookie de sessão com nome
próprio. Da raiz do repositório:

```bash
cd starters/react
cp .env.example .env

# Dependências PHP (monta a pasta dos pacotes: path repository ../../packages)
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/var/www/html \
  -v $(pwd)/../../packages:/var/packages -w /var/www/html composer:2 composer install \
  --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-intl \
  --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-gd

# Front (Node 24 glibc; a pasta dos pacotes entra por causa do tema do /admin)
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app \
  -v $(pwd)/../../packages:/packages -w /app node:24-slim npm install --ignore-scripts
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app \
  -v $(pwd)/../../packages:/packages -w /app node:24-slim npm run build

# Sobe só os serviços do React (não mexe nos do Livewire)
cd ../..
export UID GID=$(id -g)
docker compose up -d react-nginx react-queue react-scheduler
docker compose exec react-app php artisan key:generate --force
docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler
docker compose exec react-app php artisan migrate
```

Aplicação: **http://127.0.0.1:8181** · Mailpit: http://localhost:18025 ·
super admin: http://127.0.0.1:8181/admin (promova com
`docker compose exec react-app php artisan user:make-admin email@exemplo.com`).

**Por que 127.0.0.1 e não localhost:** cookie é por host, não por porta. Com o
Livewire em `localhost:8180` e o React em `localhost:8181`, o cookie
`XSRF-TOKEN` (nome fixo do Laravel, lido pelo front) era um só para os dois, e
o primeiro envio depois de trocar de aba caía no "sessão expirou" (419). Em
`127.0.0.1`, os cookies do React ficam separados. O `APP_URL` do
`.env.example` já é esse, e o `docker/nginx/dev.conf` leva quem abrir
`localhost:8181` ao mesmo caminho em `127.0.0.1:8181` (308: método e corpo
mantidos). Só no dev: a produção atende pelo domínio da `APP_URL`.

O serviço `react-db-init` cria `tws_starter_react` e `tws_starter_react_test`
no PostgreSQL compartilhado, se faltarem, e sai — sem tocar no serviço
`postgres` nem no banco do Livewire.

**Servidor de desenvolvimento do Vite** (`npm run dev`, com HMR): em
`APP_ENV=local`, enquanto o `public/hot` existe, a CSP inclui a origem do
servidor do Vite (e o WebSocket do HMR) — e nada além disso, nunca
`'unsafe-eval'`. Ver `App\Support\ViteDevServerCsp`.

## Comandos do dia a dia

```bash
# Testes (Pest) — SQLite em memória / PostgreSQL (banco tws_starter_react_test)
docker compose exec react-app ./vendor/bin/pest
docker compose exec react-app ./vendor/bin/pest -c phpunit.pgsql.xml
docker compose exec react-app ./vendor/bin/pint

# Front: tipos, lint/formatação (Vite+ do kit oficial: oxlint + oxfmt) e build
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npm run types:check
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npm run check
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npx vp fmt
```

(Os `docker run` montam a pasta atual: rode-os dentro de `starters/react`.)

## O que veio do kit oficial e o que foi trocado

| Veio do kit oficial | Trocado |
| --- | --- |
| React 19, Inertia 3 (`@inertiajs/vite`, `Form`, layouts por página), TypeScript estrito, Tailwind 4, componentes shadcn/ui (`resources/js/components/ui`), o layout do painel com menu lateral recolhível e o avatar no pé, as telas de autenticação em cartão, o React Compiler, o Vite+ (`vp`: build, lint com oxlint e formatação com oxfmt) | **Autenticação:** sai o Fortify (e passkeys, 2FA por app, confirmação de senha); entram os controllers do `twstec/kit-auth` com respostas Inertia (abaixo) |
| | **Rotas no front:** sai o Wayfinder (roda `php artisan` no build; aqui o build é só Node). O servidor manda o mapa nome → caminho das rotas usadas (`App\Support\FrontRoutes`) e o front pergunta `route('login')` (`resources/js/lib/routes.ts`) |
| | **Textos:** nada em inglês fixo. O front lê as traduções do Laravel (`lang/{pt_BR,en,es}`), enviadas uma vez por idioma (`App\Support\FrontTranslations`, `resources/js/lib/i18n.ts`) |
| | **Fonte:** a Instrument Sans vem do `@fontsource` (a CSP do kit não permite fonte externa; o oficial usa o Bunny Fonts) |
| | **Tema:** a regra do Livewire — `localStorage` do dispositivo → o tema da CONTA (`users.theme`) → sistema, aplicada antes da primeira pintura |
| | **Usuário nas props:** lista fechada de campos (`App\Support\SharedUser`), nunca o model serializado |
| | **Marca:** a do kit (monocromática), ou o `PLATFORM_LOGO_URL` |

## Autenticação: a do pacote, com respostas Inertia

As **telas** (GET) são do starter (`App\Http\Controllers\Auth\AuthPageController`
→ páginas em `resources/js/pages/auth`); os **envios** (POST) são os
controllers do `twstec/kit-auth`, que trazem o próprio `throttle:sensitive`.
Os endereços e os nomes das rotas são os mesmos do Livewire. O que volta ao
navegador sai das **implementações Inertia dos 11 contratos de resposta** do
pacote (`App\Http\Responses\Inertia`, registradas no `AppServiceProvider`):

- **Quem atravessa a porta** (login, login pelo código do segundo fator,
  cadastro, e-mail confirmado, logout) recebe carga **completa** do destino:
  numa visita do Inertia, 409 com `X-Inertia-Location`; fora dele, o redirect
  de sempre. O estado do front da sessão anterior não sobrevive, e o destino
  pode nem ser uma página Inertia (`/admin`, o link tentado antes do login).
  O destino guardado passa pelo `SafeRedirect` — só volta para dentro da
  aplicação.
- **Quem fica do mesmo lado** (código errado, reenvio, link reenviado,
  recusa de redefinição) recebe um redirect comum, com o erro no campo
  (prop `errors`) ou o aviso na sessão (prop `flash.status`, mostrado como
  toast; `flash.verification_error` fica fixo na tela).

A regra (bloqueio por tentativas, conta ativa, anti-enumeração, sessão
regenerada, segundo fator, e-mails) roda no pacote antes da resposta; as
mensagens são as mesmas do Livewire.

**Ligar/desligar o segundo fator** (perfil) é ação sensível: senha de
transação → código por e-mail → a operação. O token de ação sensível nasce e
morre **no servidor** (`TwoFactorPreferenceController`), como no Livewire; as
rotas JSON `sensitive-actions.*` do pacote seguem disponíveis para clientes
próprios.

## Props compartilhadas

`App\Http\Middleware\HandleInertiaRequests`: `app` (nome, logotipo, idioma,
idiomas), `auth.user` (lista fechada: uuid, código público, nome, e-mail,
idioma, tema, foto, e três estados), `kit.modules` (módulos opcionais
instalados — `Kit::has()`), `navigation` (o menu lateral, montado no servidor
com a arquitetura de informação do Livewire), `routes`, `flash`,
`accountMenu` (o seletor de conta: a conta atual e as contas da pessoa, com
o papel — só com o pacote de contas), `translations` e `sidebarOpen`. Rota com
parâmetro chega em `routes` como modelo (`/api-keys/{key}/rotate`), preenchido
no front (`route('panel.api-keys.rotate', { key })`). **Nenhuma credencial** — um teste varre as
props de todas as telas atrás de senha, hash, token, código e pepper
(`tests/Feature/Inertia/SharedPropsTest.php`).

## Módulos opcionais e demonstração

Como no Livewire: `foundation` e `auth` sempre; `accounts`, `uploads` e
`admin` opcionais (`php artisan tws:install`). O front recebe os módulos
instalados em `kit.modules`, e o menu só mostra tela que existe. O `/admin` é
o mesmo plugin Filament do Livewire (tema em `resources/css/filament.css`,
só no build com o painel instalado). O starter React **não usa a
demonstração do kit** (`twstec/kit-demo`): `/` é a página inicial mínima do
produto.

## Contas, chaves de API, projetos e foto (módulos opcionais)

Com o `twstec/kit-accounts`: o **seletor de conta** em todo o painel, a
**página da conta** (`/account`: dados, membros em tabela ou cartões,
convites, transferir e excluir), **criar conta de empresa**
(`/accounts/create`), **chaves de API** (`/api-keys`), **projetos**
(`/projects`) e a **tela pública do convite** (`/invitations/{token}`). Com o
`twstec/kit-uploads`: a **foto de perfil** no `/profile` (enviar e tirar).

- A regra é do pacote: cada mudança de conta é uma Action (papel, trilha,
  recusas `denied`); chaves pelo `ApiKeyService`, projetos pelo
  `ProjectService`. A tela esconde o que o papel não permite e o servidor
  recusa (403) o que vier por fora.
- Ações sensíveis (criar e rotacionar chave, transferir e excluir a conta):
  `…/code` confere o pedido (`stage=check`) e manda o código (`stage=send`,
  com a senha de transação); o envio da ação traz o código, que vira o token
  **no servidor** (`App\Http\Controllers\Panel\Concerns\ConfirmsSensitiveAction`).
- **A secreta da chave** só existe na resposta imediata da criação/rotação,
  como `flash` do Inertia (fora das props e do histórico do navegador); nunca
  na listagem nem na sessão depois da resposta.
- As respostas do link de convite e da troca de conta são as Inertia do
  aplicativo (`App\Http\Responses\Inertia\Accounts`). O token do convite
  não vai para as props: o front o lê do próprio endereço.
- Componentes reutilizáveis em `resources/js/components` (seletor de conta,
  diálogo de ação sensível, diálogo de confirmação, alternador tabela/cartões,
  ação só com ícone + tooltip, revelação única da secreta, listas de membros e
  convites, foto de perfil).

## Diferenças conhecidas para o Livewire

- Projetos: além de renomear, o React **arquiva e reativa** (o
  `ProjectService` já aceita o status; o Livewire só renomeia).
- A foto de perfil pode ser **removida** (`AvatarService::remove()`); o
  Livewire só troca.
- A escolha tabela/cartões fica no navegador (no Livewire, na sessão).
- `LoginPrefillProvider` (credenciais sugeridas no login, usado pela demo)
  não é lido: credencial não vai para as props.
- `/mail-preview` é uma página Blade autossuficiente (os e-mails em si são os
  mesmos).
- No Docker de dev, o React roda em `127.0.0.1:8181` (e não em `localhost`)
  para não dividir o cookie `XSRF-TOKEN` com o Livewire — ver "Rodar".

## E2E (Playwright)

Em `tests/e2e`, com o Playwright 1.63 do projeto, contra o dev
(`http://127.0.0.1:8181`), com os e-mails de verdade lidos no Mailpit:
cadastro com verificação de e-mail, login com segundo fator, perfil (idioma,
tema gravado na conta, foto com URL assinada e arquivo falso recusado), senha
de transação, contas (convidar → aceitar criando o acesso → trocar de conta →
transferir a propriedade com senha de transação e código → remover membro),
chave de API com a secreta mostrada uma vez, projetos, e o `/admin` (login e
uma ação auditada).

```bash
# Pessoas fixas do E2E (idempotente): e2e@example.com e admin-e2e@example.com
docker compose exec -T react-app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"

# De starters/react (espere 61 s entre duas rodadas: limite de borda por IP)
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble npx playwright test
```

**Sem lixo no banco:** cada teste que cria pessoas (sempre `e2e-…@example.com`)
as apaga no fim, passando ou falhando, pelo `/admin` com a sessão do admin do
E2E — com elas saem a conta pessoal, os projetos, as chaves e a foto — e apaga
as mensagens delas no Mailpit. A limpeza não depende do idioma (acha a ação
do Filament pelo nome e confere refazendo a busca). No fim da suíte, uma
varredura (`tests/e2e/global-teardown.ts`) apaga qualquer `e2e-…` que um teste
interrompido tenha deixado. Os seletores são ids e atributos `data-*`, não
textos.

## Produção (imagem Docker)

A imagem de produção é do próprio starter, no mesmo desenho da do Livewire:
`docker/php/Dockerfile` (target `prod`: PHP-FPM 8.4 com as extensões do kit,
dependências de produção instaladas sobre o PHP da imagem final, os pacotes
do kit **copiados** em `vendor/`, o front React compilado no estágio `assets`
— Node 24 glibc, por causa dos binários nativos do Vite+ —, código de root e
processo como `www-data`) e `docker/nginx/Dockerfile` (nginx com TLS
autoassinado; `docker/nginx/prod.conf`). O mesmo Dockerfile serve nos dois
cenários: no monorepo, a pasta `packages/` entra como contexto de build
nomeado; num projeto criado pelo `create-project`, sem o contexto, os pacotes
vêm do Composer (Packagist) como qualquer dependência:

```bash
# no monorepo
docker build --build-context packages=../../packages --target prod \
  -f docker/php/Dockerfile -t meu-app:prod .
# num projeto criado pelo create-project
docker build --target prod -f docker/php/Dockerfile -t meu-app:prod .

docker build --target prod -f docker/nginx/Dockerfile -t meu-app-nginx:prod .
```

O `.dockerignore` deixa de fora todo arquivo de ambiente, testes (inclusive o
E2E), storage local, bancos SQLite e artefatos de build. A demonstração e o
instalador (require-dev) não entram. O CI constrói as duas imagens e confere
a do app com `.github/images/check-react-app.sh` (job "Imagens de produção do
starter React") — e, na simulação da instalação publicada, constrói as
imagens do projeto criado e passa a mesma conferência. Para subir a stack completa: `docker-compose.prod.yml` e
`.env.prod.example` (os mesmos do Livewire, com o banco `tws_starter_react`)
— ver [docs/producao.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/producao.md).

