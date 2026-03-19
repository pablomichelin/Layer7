# Runbook - seguranca da WebGUI do pfSense no lab

## 1. Objetivo

Registrar o incidente ocorrido durante a rodada de ajuste visual do pacote Layer7, a causa real, a recuperacao aplicada no appliance e as regras operacionais para nao repetir a quebra da GUI base do pfSense.

---

## 2. Resumo do incidente

Durante a revalidacao visual do pacote Layer7, a WebGUI do pfSense deixou de responder corretamente:

- primeiro com erro `50x`
- depois com `502 Bad Gateway`
- e, numa fase seguinte, com a tela de login a voltar para si mesma sem completar a entrada no dashboard

O problema nao era a credencial do utilizador. O incidente foi causado por mudancas operacionais no stack web do appliance, fora do fluxo oficial do pfSense.

---

## 3. Causas encontradas

### 3.1. Nao reiniciar `nginx` manualmente no pfSense

Nao usar:

```sh
service nginx restart
service nginx onerestart
```

O webConfigurator do pfSense nao deve ser tratado como um `nginx` generico. O restart manual pode deixar o frontend em estado inconsistente.

### 3.2. `php-fpm` fora do modo esperado pelo webConfigurator

O `nginx` do pfSense esperava:

```text
unix:/var/run/php-fpm.socket
```

Mas o `php-fpm` estava configurado para ouvir em:

```text
127.0.0.1:9000
```

Isto levou ao `502 Bad Gateway`.

### 3.3. Permissoes incorretas em `/tmp/symfony-cache`

Mesmo depois da recuperacao do socket, o login ainda falhava ao entrar no dashboard porque o cache do pfSense ficou com ownership/permissoes erradas.

Erro observado no Crash Reporter:

```text
unlink(/tmp/symfony-cache/filesystem/...): Permission denied
```

Na pratica:

- o `php-fpm` corria como `www`
- parte do cache ficou como `root:wheel`
- o login autenticava, mas o `/index.php` quebrava e a GUI voltava para a tela de entrada

---

## 4. Recuperacao aplicada no appliance

### 4.1. Voltar o `php-fpm` para o socket esperado

Arquivo:

```text
/usr/local/etc/php-fpm.d/www.conf
```

Parametros operacionais confirmados:

```text
listen = /var/run/php-fpm.socket
listen.owner = www
listen.group = www
listen.mode = 0660
```

### 4.2. Reiniciar a GUI pelo caminho oficial

Usar:

```sh
service php_fpm onerestart
/etc/rc.restart_webgui
```

### 4.3. Corrigir cache e sessoes

Comandos usados:

```sh
chown -R www:www /tmp/symfony-cache
find /tmp/symfony-cache -type d -exec chmod 775 {} +
find /tmp/symfony-cache -type f -exec chmod 664 {} +
rm -f /tmp/sess_*
rm -rf /tmp/symfony-cache
install -d -o www -g www -m 775 /tmp/symfony-cache
/etc/rc.restart_webgui
```

---

## 5. O que nao fazer novamente

- nao reiniciar `nginx` manualmente no pfSense para atualizar paginas do pacote
- nao mexer no frontend base do appliance para "forcar refresh" do pacote
- nao declarar a GUI como recuperada sem testar:
  - raiz `https://IP/`
  - login
  - dashboard autenticado
  - paginas do pacote
- nao considerar o browser como unica prova; validar tambem com `curl`

---

## 6. Procedimento seguro para proximas sessoes

### 6.1. Antes de reinstalar o pacote

Verificar o pfSense base:

```sh
curl -k -I https://IP_DO_PFSENSE/
```

Esperado:

```text
HTTP/1.1 200 OK
```

### 6.2. Depois da reinstalacao do pacote

Validar:

1. login aceita `POST /` com `302 Found`
2. dashboard autenticado responde `200 OK`
3. paginas Layer7 respondem `200 OK`
4. `layer7d` continua operacional

### 6.3. Se a GUI quebrar

Checklist:

1. conferir `/usr/local/etc/php-fpm.d/www.conf`
2. garantir `listen = /var/run/php-fpm.socket`
3. reiniciar com `/etc/rc.restart_webgui`
4. conferir ownership/permissoes de `/tmp/symfony-cache`
5. limpar sessoes antigas se o login ficar em loop

---

## 7. Evidencia final desta rodada

A recuperacao so foi considerada concluida depois de:

- `curl -k -I https://192.168.0.195/` devolver `HTTP/1.1 200 OK`
- login autenticado devolver `HTTP/1.1 302 Found`
- `GET /index.php` autenticado devolver `HTTP/1.1 200 OK` repetidamente
- `GET /packages/layer7/layer7_status.php` autenticado devolver `HTTP/1.1 200 OK`
- `GET /packages/layer7/layer7_settings.php` autenticado devolver `HTTP/1.1 200 OK`
- validacao humana do utilizador confirmar:
  - login do pfSense funcional
  - visual do pacote aprovado
