# Release — Instalação e Gestão de Frota

## Instalação em 1 pfSense (um comando)

No pfSense, via **SSH** ou **Diagnostics > Command Prompt**:

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/install.sh && sh /tmp/install.sh
```

O script faz **tudo automaticamente**:
1. Baixa o `.pkg` do GitHub Releases
2. Instala o pacote
3. Cria tabelas PF (`layer7_block`, `layer7_tagged`)
4. Habilita o serviço (`sysrc layer7d_enable=YES`)
5. Inicia o daemon em modo **monitor** (seguro, não bloqueia nada)
6. Mostra instruções para configuração via GUI

**Não precisa editar nada.** Funciona em qualquer pfSense CE.

### Versão específica

```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/install.sh && sh /tmp/install.sh --version 1.8.3
```

### Reinstalar

```sh
sh /tmp/install.sh --force
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

---

## Instalação em 52+ firewalls (frota)

### Método 1: Script de frota (`fleet-update.sh`)

Instala/actualiza **N firewalls em paralelo** via SSH. Um único comando.

#### Pré-requisitos (uma única vez)

1. **SSH keys**: configurar acesso SSH sem senha do builder (ou qualquer máquina Linux/FreeBSD) para todos os firewalls:

```sh
# Para cada firewall:
ssh-copy-id root@192.168.1.1
ssh-copy-id root@10.0.1.1
ssh-copy-id root@10.0.2.1
# ... etc
```

2. **Inventário**: criar ficheiro com IPs dos firewalls:

```sh
cat > firewalls.txt << 'EOF'
192.168.1.1    # Matriz
192.168.2.1    # Filial SP
10.0.1.1       # Filial RJ
10.0.2.1       # Filial MG
10.0.3.1       # Filial RS
# ... até 52 firewalls
EOF
```

#### Instalar/actualizar todos

```sh
# Compilar o pacote no builder (1x):
cd /root/pfsense-layer7
make -C package/pfSense-pkg-layer7 package

# Distribuir para todos (4 em paralelo):
./scripts/release/fleet-update.sh \
  -i firewalls.txt \
  -p package/pfSense-pkg-layer7/work/pkg/pfSense-pkg-layer7-0.2.0.pkg \
  --parallel 4
```

O script faz em **cada firewall** automaticamente:
1. Copia o `.pkg` via SCP
2. Para o daemon
3. Instala com `pkg add -f`
4. Cria tabelas PF (se não existirem)
5. Habilita e inicia o daemon
6. Verifica versão e PID
7. Limpa ficheiros temporários
8. Gera log individual em `/tmp/layer7-fleet-*/`

#### Verificar após

```sh
# Ver status de todos:
for ip in $(grep -v '#' firewalls.txt); do
    echo "$ip: $(ssh root@$ip '/usr/local/sbin/layer7d -V')"
done
```

#### Dry-run (ver o que seria feito)

```sh
./scripts/release/fleet-update.sh -i firewalls.txt -p pkg.pkg --dry-run
```

### Método 2: Comando individual (sem SSH keys)

Se preferir instalar um a um via GUI do pfSense:

Em cada pfSense, **Diagnostics > Command Prompt**:
```sh
fetch -o /tmp/install.sh https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/install.sh && sh /tmp/install.sh
```

---

## Actualizar regras nDPI (sem recompilação)

Para actualizar protocolos customizados em todos os firewalls **sem reinstalar o pacote**:

```sh
# Editar regras:
vim layer7-protos.txt

# Sincronizar + reload em todos:
./scripts/release/fleet-protos-sync.sh -i firewalls.txt -f layer7-protos.txt
```

---

## Fluxo completo recomendado para 52 firewalls

```
1. Compilar no builder (1x)
   └─> pfSense-pkg-layer7-0.2.0.pkg

2. Testar em 1 pfSense de lab
   └─> install.sh ou pkg add manual

3. Configurar SSH keys para todos os firewalls
   └─> ssh-copy-id root@IP (52x, uma única vez)

4. Criar inventário (firewalls.txt)

5. Distribuir para todos
   └─> fleet-update.sh -i firewalls.txt -p pkg --parallel 4

6. Cada admin configura via GUI
   └─> Services > Layer 7 > Definições > Interfaces
   └─> Services > Layer 7 > Políticas > Adicionar regras
   └─> Services > Layer 7 > Definições > Modo enforce

7. Actualizações futuras
   └─> Regras: fleet-protos-sync.sh (sem reinstalar)
   └─> Pacote: fleet-update.sh (nova versão)
```

---

## Release oficial assinada (F1.2)

### Passo 1: builder prepara o stage dir

```sh
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name Layer7 \
  --version 1.8.3
```

O builder passa a gerar apenas o **stage dir** com:

- `.pkg`
- `.pkg.sha256`
- `install.sh`
- `uninstall.sh`
- `release-manifest.v1.txt`

### Passo 2: signer assina fora do builder

```sh
sh scripts/release/sign-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.3 \
  --private-key /caminho/seguro/layer7-release-ed25519.pem
```

### Passo 3: validar o conjunto assinado

```sh
sh scripts/release/verify-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.3
```

### Passo 4: publicar no GitHub Releases

```sh
sh scripts/release/publish-release.sh \
  --stage-dir /tmp/layer7-release-v1.8.3 \
  --repo-owner pablomichelin \
  --repo-name Layer7 \
  --version 1.8.3
```

---

## Ficheiros

| Ficheiro                   | Descrição                                       |
|----------------------------|------------------------------------------------|
| `install.sh`               | Instalação universal (1 pfSense, zero config)  |
| `fleet-update.sh`          | Distribui `.pkg` para N firewalls via SSH      |
| `fleet-protos-sync.sh`     | Sincroniza regras custom para N firewalls      |
| `update-ndpi.sh`           | Actualiza nDPI no builder e reconstrói pacote  |
| `deployz.sh`               | Builder: prepara stage dir com manifesto       |
| `sign-release.sh`          | Signer: assina o manifesto fora do builder     |
| `verify-release.sh`        | Verifica manifesto, assets e assinatura        |
| `publish-release.sh`       | Publica o stage dir já assinado                |
| `generate-release-signing-key.sh` | Gera par Ed25519 fora do builder      |
| `install-lab.sh.template`  | Template de lab preservado como legado         |
