# Rollback — pfSense-pkg-layer7

## Objetivo

Reverter a instalação do pacote Layer7 no pfSense, restaurando o estado anterior do appliance.

---

## Procedimento rápido

```sh
pkg delete pfSense-pkg-layer7
```

Confirmar remoção:

```sh
pkg info pfSense-pkg-layer7 2>&1
```

Esperado: `pkg: No package(s) matching pfSense-pkg-layer7`.

---

## O que o `pkg delete` remove

- `/usr/local/sbin/layer7d`
- `/usr/local/pkg/layer7.xml`
- `/usr/local/pkg/layer7.inc`
- `/usr/local/www/packages/layer7/*.php`
- `/usr/local/etc/rc.d/layer7d`
- `/usr/local/etc/layer7.json.sample`
- `/etc/inc/priv/layer7.priv.inc`
- `/usr/local/share/pfSense-pkg-layer7/info.xml`

---

## O que NÃO é removido automaticamente

- `/usr/local/etc/layer7.json` (configuração do operador)
- Tabelas PF criadas manualmente (ex: `layer7_block`)
- Entradas sysrc (`layer7d_enable`)
- Logs em syslog

### Limpeza manual (opcional)

```sh
rm -f /usr/local/etc/layer7.json
sysrc -x layer7d_enable 2>/dev/null || true
```

Para limpar tabela PF (se criada):

```sh
pfctl -t layer7_block -T flush 2>/dev/null || true
```

---

## Reinstalar versão anterior

Usar o `install-lab.sh` da release desejada:

```sh
fetch -o /tmp/install-lab.sh https://github.com/REPO_OWNER/REPO_NAME/releases/download/vVERSAO_ANTERIOR/install-lab.sh && sh /tmp/install-lab.sh
```

---

## Rollback completo (snapshot)

Se houver snapshot da VM antes da instalação:

1. Parar o pacote: `service layer7d onestop`
2. Remover: `pkg delete pfSense-pkg-layer7`
3. Ou restaurar snapshot diretamente

---

## Verificação pós-rollback

```sh
pkg info | grep layer7
ps auxww | grep layer7d | grep -v grep
ls -la /usr/local/sbin/layer7d 2>&1
ls -la /usr/local/etc/layer7.json 2>&1
```

Todos devem indicar ausência do pacote/binário/processo.
