# Builder FreeBSD (requisitos)

## Objetivo

Host onde se compila código nativo (nDPI, PoC, futuro pacote) com **ABI/libs próximas** ao pfSense CE alvo.

## Alinhamento de versão

1. Anotar a versão do **pfSense CE** do lab (ex.: 2.7.x).
2. Consultar a **versão base FreeBSD** dessa release (documentação Netgate/pfSense).
3. Preferir **VM FreeBSD com a mesma major** (ex.: FreeBSD 14.x se o CE for 14.x).

> Desvio de major aumenta risco de binários incompatíveis no appliance.

## VM / host mínimo sugerido

| Recurso | Mínimo razoável |
|---------|-----------------|
| vCPU | 2 |
| RAM | 4 GiB |
| Disco | 32 GiB |
| Rede | 1 NIC (NAT ou bridge) para `pkg` e Git |

## Pacotes iniciais (no builder)

```sh
pkg install -y git ca_root_nss
```

Opcional cedo na PoC:

```sh
pkg install -y cmake pkgconf gcc
```

Árvore **ports** ou **poudriere** entram quando o port `pfSense-pkg-layer7` estiver maduro (Bloco 5+).

## Fluxo de trabalho

1. Clonar: `git clone https://github.com/pablomichelin/pfsense-layer7.git`
2. Desenvolvimento da PoC em `src/` (Bloco 3).
3. Binários de teste no builder; instalação no pfSense só via pacote `.txz` quando o empacotamento existir.

### Compilar só o `layer7d` (sem `make package`)

```sh
cd src/layer7d
make
./layer7d -t -c ../../samples/config/layer7-minimal.json
make check   # -V + parse sample (opcional)
make clean
```

Flags alinhadas ao port; **`version.str`** em `src/layer7d/` define a string de **`layer7d -V`**. O script **`scripts/package/smoke-layer7d.sh`** usa o mesmo Makefile com **`VSTR_DIR`** temporário e binário **`layer7d-smoke`**.

## Segurança

- Builder com acesso GitHub (HTTPS ou SSH).
- Não reutilizar chaves de produção no lab sem isolamento.
