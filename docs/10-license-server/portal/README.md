# Portal Admin de Licenças — START HERE

> **Trilha activa (produto UI do license server).**  
> Não substitui `CORTEX.md` nem `MANUAL-USO-LICENCAS.md`.  
> Governa o painel `https://license.systemup.inf.br` e a organização
> documental **obrigatória** desta trilha.

## Leitura obrigatória (ordem)

1. [`../../../CORTEX.md`](../../../CORTEX.md)
2. [`../README.md`](../README.md) — índice da área license-server
3. **Este ficheiro**
4. [`GOVERNANCE.md`](GOVERNANCE.md) — regras que **sempre** se seguem
5. [`VERSION.md`](VERSION.md) + [`CHANGELOG.md`](CHANGELOG.md)
6. [`ESTADO.md`](ESTADO.md) — snapshot operacional
7. [`OBJECTIVOS.md`](OBJECTIVOS.md)
8. Plano activo em [`planos/`](planos/README.md)
9. [`IDEIAS.md`](IDEIAS.md) + [`ACOES.md`](ACOES.md)

Só depois: código em `license-server/` e manuais
[`../MANUAL-USO-LICENCAS.md`](../MANUAL-USO-LICENCAS.md) /
[`../MANUAL-INSTALL.md`](../MANUAL-INSTALL.md).

## O que é este portal

Painel web (React + API Node + PostgreSQL em Docker) para o **operador
Systemup** (hoje: gestão individual, sem escala MSP/self-service)
gerir clientes, licenças, activações e artefactos `.lic`.

| Campo | Valor |
|-------|-------|
| Produto UI | Layer7 License Manager |
| Versão visual actual | **1.3.2** (nomenclatura equipamento) |
| Código | `license-server/` |
| Live | `192.168.100.244:/opt/layer7-license` |
| URL | `https://license.systemup.inf.br` |

## Mapa dos ficheiros desta pasta

| Ficheiro | Função |
|----------|--------|
| [`GOVERNANCE.md`](GOVERNANCE.md) | Regras documentais e de entrega (obrigatório) |
| [`VERSION.md`](VERSION.md) | Versão visual actual + política de bump |
| [`CHANGELOG.md`](CHANGELOG.md) | Histórico de versões do portal |
| [`ESTADO.md`](ESTADO.md) | Estado vivo (deploy, drift, inventário) |
| [`OBJECTIVOS.md`](OBJECTIVOS.md) | Objectivos do software / portal |
| [`IDEIAS.md`](IDEIAS.md) | Aceites, futuras, diferidas, rejeitadas |
| [`ACOES.md`](ACOES.md) | Diário de acções e decisões executadas |
| [`checklist.md`](checklist.md) | Checklist por versão / bloco |
| [`planos/`](planos/) | Planos activos e concluídos |
| [`historico/`](historico/) | Baselines e registos fechados |

## Escopo actual vs futuro

| Agora (operador único) | Próximo momento (fora desta trilha até GO) |
|------------------------|--------------------------------------------|
| Completar ciclo de vida no painel | Portal cliente / MSP |
| Rebind, renovar, auditar, check-in UI | Multi-admin / papéis |
| Alinhar SPA ao backend; SKU legível | Faturação / alertas email em massa |

Plano de melhoria total:  
[`planos/2026-08-08-melhoria-total-portal.md`](planos/2026-08-08-melhoria-total-portal.md)

## Regra de ouro

Nenhuma alteração de código do portal sem:

1. ler `GOVERNANCE.md`;
2. actualizar `ACOES.md` no mesmo bloco;
3. actualizar `CHANGELOG.md` / `VERSION.md` se a versão visual subir;
4. actualizar `ESTADO.md` se o live mudar;
5. fechar itens do checklist do bloco.
