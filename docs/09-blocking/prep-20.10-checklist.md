# Preparação 20.10 (NÃO é GO produto) — 【FECHADO】

**Estado:** **FECHADO** `2026-08-09` — GO produto dado; passo **20.10a** em curso  
**GO:** [`GO-produto-20.10.md`](GO-produto-20.10.md)  
**Draft histórico:** [`drafts/mitm-packaging-20.10/`](drafts/mitm-packaging-20.10/) (mergeado no port em 20.10a)

## Checklist (pré-GO) — completo

1. [x] S1 inline lab Opção A PASS — evidência `20260809T045500Z-s1-inline-opcao-a-54`
2. [x] S2/S3/S4 lab PASS
3. [x] S6 nota escrita (`s6-s7-lab-notas-opcao-a.md`)
4. [x] S7 auditoria runtime lab PASS (PoC; ver notas)
5. [x] IPC DECIDE mock smoke PASS (`ipc-decide-mock.py`; desenho integração `layer7d` ainda futuro)
6. [x] `pkg-plist` + rc script draft (flag default OFF) — depois mergeado em 20.10a
7. [x] Rollback doc + smoke S8 com runtime presente e OFF —
   evidência `20260809T050000Z-s8-runtime-present-off-54`
8. [x] **GO produto humano** explícito — [`GO-produto-20.10.md`](GO-produto-20.10.md)

## Pós-GO (plano)

| Bloco | Conteúdo | Estado |
|-------|----------|--------|
| **20.10a** | Runtime no `.pkg` + rc default OFF + `runtime_available`; `intercept_ready=false` | **em curso** |
| **20.10b** | Listen selectivo + PF rdr + block page HTTPS | Pendente |
| **20.11** | GI2/GI3 lab | Pendente |
