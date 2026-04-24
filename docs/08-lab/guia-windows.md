# Desenvolvimento em Windows

## Objetivo

Permitir que quem desenvolve em **Windows** contribua e valide o projeto sem builder FreeBSD local.

## Limitações

- **Windows sem `make`/ports não serve como builder** para o port `pfSense-pkg-layer7` (ver [`validacao-lab.md`](../04-package/validacao-lab.md)).
- O smoke `scripts/package/smoke-layer7d.sh` exige `cc` e `make`.

## Opções para o desenvolvedor Windows

### 1. CI (recomendado para smoke)

O workflow **smoke-layer7d** corre em cada push/PR para `main`/`master`:

- Compila `layer7d` em Ubuntu
- Executa `check-port-files.sh` e `smoke-layer7d.sh`

**Uso:** faça push e verifique o badge no README ou em **Actions** do GitHub. Se passar, o código compila e o smoke básico está OK.

### 2. WSL2 + Ubuntu

Com **WSL2** e distro Ubuntu:

```powershell
wsl
cd /mnt/d/Layer7   # ou caminho do clone
sh scripts/package/check-port-files.sh
sh scripts/package/smoke-layer7d.sh
```

O `make package` (gerar `.pkg`) continua a exigir **FreeBSD** — WSL Ubuntu não produz binário compatível com pfSense.

### 3. Verificação local (PowerShell)

Para checar alinhamento `pkg-plist` ↔ `files/` sem `sh`:

```powershell
.\scripts\package\check-port-files.ps1
```

Ver [`scripts/package/README.md`](../../scripts/package/README.md).

### 4. Lab completo (builder + pfSense)

Para **validação em lab** (build `.pkg`, `pkg add`, serviço, GUI):

1. Provisionar **VM FreeBSD** conforme [`builder-freebsd.md`](builder-freebsd.md)
2. Provisionar **pfSense CE** conforme [`lab-topology.md`](lab-topology.md)
3. Seguir [`validacao-lab.md`](../04-package/validacao-lab.md)

Pode usar Hyper-V, VMware ou VirtualBox no Windows para as VMs.

## Resumo

| Tarefa              | Windows nativo | WSL Ubuntu | CI GitHub | FreeBSD lab |
|---------------------|----------------|------------|-----------|-------------|
| check-port-files    | PowerShell     | sh         | sim       | sh          |
| smoke layer7d       | não            | sim        | sim       | sim         |
| make package (.pkg) | não            | não        | não       | sim         |
| pkg add + serviço   | não            | não        | não       | pfSense VM  |
