# Runbook — Publicacao Segura do License Server

## Finalidade

Operar a F2.1 do license server com fronteira de rede explicita:

- `https://license.systemup.inf.br` em `443/TCP` como unico canal publico
- `8445/TCP` como origin privado do stack Docker
- TLS terminado na borda, sem fallback publico para HTTP

Este runbook complementa:

- [`../03-adr/ADR-0007-publicacao-segura-license-server.md`](../03-adr/ADR-0007-publicacao-segura-license-server.md)
- [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md)

---

## Topologia oficial

```text
Internet / pfSense
  -> http://license.systemup.inf.br:80
     -> redirect obrigatorio para https://license.systemup.inf.br:443
  -> https://license.systemup.inf.br:443
     -> edge proxy / certificado valido
     -> origin privado http://127.0.0.1:8445
     -> nginx interno do stack
     -> backend :3001 / frontend :80 / postgres :5432 (internos)
```

Se o edge proxy nao estiver no mesmo host do Docker, o origin deixa de ser
`127.0.0.1` e passa a ser um IP privado dedicado. Neste caso, e obrigatorio:

- bind do origin em IP privado controlado
- allowlist/firewall permitindo apenas o edge proxy e troubleshooting
  administrativo justificado
- ausencia de exposicao publica directa do `8445`

---

## Configuracao oficial do stack

### 1. Origin privado

`license-server/.env` deve conter, no minimo:

```env
LICENSE_SERVER_ORIGIN_BIND_IP=127.0.0.1
LICENSE_SERVER_ORIGIN_PORT=8445
```

Politica:

- `127.0.0.1` e o default seguro
- mudar o bind para IP privado/LAN so com firewall/ACL explicitos
- `3001` e `5432` permanecem sem `ports:` no host

### 2. Edge proxy

Responsabilidades minimas da borda:

- apresentar certificado valido e renovado
- redirecionar `http://` para `https://`
- encaminhar apenas para o origin privado autorizado
- preservar `Host`
- injectar `X-Forwarded-Proto`, `X-Forwarded-For` e `X-Forwarded-Host`
- negar hosts inesperados

Exemplo generico em Nginx de borda:

```nginx
server {
    listen 80;
    server_name license.systemup.inf.br;

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name license.systemup.inf.br;

    ssl_certificate /etc/letsencrypt/live/license.systemup.inf.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/license.systemup.inf.br/privkey.pem;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "same-origin" always;

    location / {
        proxy_pass http://127.0.0.1:8445;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

Se a borda for ISPConfig/Apache equivalente, o contrato e o mesmo.

---

## Validacao minima da F2.1

### Do lado publico

```bash
curl -I http://license.systemup.inf.br
curl -I https://license.systemup.inf.br
curl -I https://license.systemup.inf.br/api/health
```

Esperado:

- `http://` devolve `301`/`308` para `https://`
- `https://` responde com certificado valido
- `Strict-Transport-Security`, `X-Content-Type-Options`,
  `X-Frame-Options` e `Referrer-Policy` aparecem na resposta publica

### Do lado do host/origin

```bash
ss -ltnp | rg ':(443|80|8445|3001|5432)\\b'
curl -s -H 'Host: license.systemup.inf.br' http://127.0.0.1:8445/api/health
```

Esperado:

- `443` e `80` pertencem ao edge proxy de borda
- `8445` fica em `127.0.0.1:8445` por defeito
- `3001` e `5432` nao aparecem expostos no host
- o health do origin responde apenas no contexto privado/controlado

### Do lado do Docker

```bash
docker compose -f license-server/docker-compose.yml config
docker compose -f license-server/docker-compose.yml ps
```

Esperado:

- apenas o servico `nginx` publica `8445`
- `api` e `db` continuam sem `ports:`

---

## Falhas que devem fechar

- certificado invalido, expirado ou hostname errado
- `http://license.systemup.inf.br` sem redirect para HTTPS
- exposicao publica directa de `8445`
- abertura inadvertida de `3001` ou `5432`
- host inesperado a chegar ao nginx interno

Nao e permitido abrir bypass publico como compensacao.

---

## Troubleshooting controlado

### O painel publico responde 502/504

1. validar o edge proxy
2. validar se o origin local responde:

```bash
curl -s -H 'Host: license.systemup.inf.br' http://127.0.0.1:8445/api/health
```

3. validar os containers:

```bash
docker compose -f license-server/docker-compose.yml ps
docker compose -f license-server/docker-compose.yml logs --tail=100 nginx api web
```

### O origin responde, mas o publico nao

- rever vhost/certificado na borda
- rever redirect `80 -> 443`
- rever firewall/ACL entre edge proxy e origin

### O origin nao responde localmente

- rever `LICENSE_SERVER_ORIGIN_BIND_IP`
- rever `docker compose up -d --build`
- rever sintaxe de `license-server/nginx/nginx.conf`

---

## Rollback exacto da F2.1

1. restaurar `license-server/docker-compose.yml`
2. restaurar `license-server/nginx/nginx.conf`
3. restaurar `license-server/.env.example`
4. remover este runbook e reverter os docs da F2.1 no mesmo commit de rollback
5. reaplicar o deploy anterior do stack

Rollback de operacao:

- manter `443/TLS` na borda como canal publico
- se o origin local falhar, corrigir o origin; nao reabrir `8445` como canal
  publico directo

---

## Dependencias operacionais explicitas

- certificado TLS valido na borda
- edge proxy com redirect `HTTP -> HTTPS`
- ACL/firewall coerente para `8445`
- resolucao DNS correcta para `license.systemup.inf.br`
- operador com acesso ao host para validar o origin privado sem o tornar
  publico
