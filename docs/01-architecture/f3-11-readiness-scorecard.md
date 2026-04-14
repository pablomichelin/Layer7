# F3.11 - Readiness Scorecard

## Finalidade

Pagina executiva de estado da F3.11 — responde se a campanha pode avancar.

---

## 1. Estado executivo

| Campo | Valor |
|-------|-------|
| Ultima actualizacao | `2026-04-14` |
| Readiness status geral | `GO condicional` |
| Campanha status geral | `GO condicional` |
| Fase | `F3 aberta` |
| Subtrilha | `F3.11 alinhada no license-server live` |
| Blockers F3 reais restantes | `DR-05` (appliance) |
| Drifts reclassificados como fora de F3 | `DR-07` |
| Drifts resolvidos | `DR-01` (schema/admin live alinhado), `DR-02` (403 vs 409 cosmetico), `DR-03` (sessao stateful + Bearer), `DR-04` (inventario obtido), `DR-06` (same-origin fail-closed) |

---

## 2. Resumo por insumo

| Insumo | Estado | Bloqueante para F3? |
|--------|--------|---------------------|
| acesso ao host `192.168.100.244` | `disponivel` — SSH confirmado, stack observada | nao |
| PostgreSQL live | `disponivel` — schema de licenciamento e tabelas admin presentes no ambiente activo | nao |
| credencial admin | `disponivel` — superficie admin alinhada; bootstrap e sessao observados no live | nao |
| appliance pfSense `192.168.100.254` | `parcial` — SSH funcional, baseline exportado, mas cenarios mutaveis ainda pendentes | sim (DR-05) |
| inventario de licencas | `disponivel` — 4 licencas reais obtidas do live em 2026-04-03 | nao |

---

## 3. Inventario real obtido em 2026-04-03

| Slot | ID | Cliente | Status | Expiry | Cenario |
|------|----|---------|--------|--------|---------|
| LIC-A | 8 | Compasi | `active` | 2026-12-31 | activacao valida em producao |
| LIC-B | 7 | Systemup | `active` | 2033-10-24 | activacao longo prazo, mesmo hw que LIC-D |
| LIC-C | 6 | Lasalle Agro | `revoked` | 2026-04-30 | revogacao real |
| LIC-D | 5 | Lasalle | `active` (expirada por data) | 2026-03-31 | expiracao por data vs status |

---

## 4. Proximos passos para fechar a F3

1. executar cenarios locais do appliance: snapshot/restore, offline/online,
   NIC/UUID (fechar DR-05);
2. consolidar evidencias e preencher relatorio final de campanha;
3. decidir binariamente `F3 pode fechar` ou `F3 nao pode fechar`.

### Evidencia DR-05 parcial (obtida em 2026-04-14)

- SSH funcional ao appliance via utilizador temporario `codex`;
- `layer7d --fingerprint` = `e31560f5bc9894e92b9007d3e2e897a374f3d0b493b803c929d54acf51f8f826`;
- licenca local valida de `Systemup`, expiry `2033-10-24`, coerente com a
  `ID 7` do backend;
- runtime exportado com stats JSON validos e restart de `layer7d` por
  `pfSsh.php playback svc restart layer7d`;
- cenarios que exigem reescrever `/usr/local/etc/layer7.lic` continuam
  pendentes porque `codex` nao tem permissao de escrita nesse ficheiro.

### Evidencia DR-02 (obtida em 2026-04-03)

| Cenario | Licenca | Repo | Live | Logica |
|---------|---------|------|------|--------|
| hw diferente do binding | ID 8 | 409 | 403 | correcta (rejeita) |
| licenca revogada | ID 6 | 409 | 403 | correcta (rejeita) |
| licenca expirada | ID 5 | 409 | 403 | correcta (rejeita) |
| reactivacao legitima | ID 7 | 200 | 200 | correcta (aceita) |

Conclusao: drift cosmetico de codigo HTTP, logica de negocio correcta.

---

## 5. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** reflectir estado real apos evidencia de 2026-04-03.
- **Impacto:** documental.
- **Risco:** baixo.
- **Rollback:** `git revert <commit-deste-bloco>`.
