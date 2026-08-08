# Versão Visual — Portal Admin de Licenças

## Versão actual

| Campo | Valor |
|-------|-------|
| **Versão** | `0.0.1` |
| **Codinome** | Baseline CRUD |
| **Estado** | Baseline formalizada (pré-melhoria total) |
| **Data de baseline** | `2026-08-08` |
| **Código (repo)** | `license-server/` |
| **Deploy live** | `192.168.100.244:/opt/layer7-license` |

## O que significa `0.0.1`

É a **primeira versão documentada** do portal como produto UI:

- painel com Dashboard / Licenças / Clientes
- activação online e emissão `.lic` via API
- auth administrativa F2 (sessão cookie)
- hardening e contratos F3 no backend
- **sem** completar o ciclo de vida operacional na UI (rebind, auditoria
  visível, renovação rápida, etc.)

Não confundir com a versão do pacote pfSense (`1.9.x`).

## Próxima versão planeada

| Versão alvo | Conteúdo | Plano |
|-------------|----------|-------|
| `0.1.0` | Bloco P0 (deploy SPA alinhada + UX chave/filtros/dashboard expiry) | [`planos/2026-08-08-melhoria-total-portal.md`](planos/2026-08-08-melhoria-total-portal.md) |
| `0.2.0` … | Blocos P1 (ciclo de vida) | mesmo plano |
| `1.0.0` | Completo para operador único | critérios em `OBJECTIVOS.md` |

## Política

Ver [`GOVERNANCE.md`](GOVERNANCE.md) §2.  
Histórico: [`CHANGELOG.md`](CHANGELOG.md).
