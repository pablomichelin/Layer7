# Preparação 20.10 (NÃO é GO produto)

**Estado:** pós-Opção A lab (S1 inline PASS) — prep itens **1–7 PASS**; **ainda sem** GO produto  
**Proibido ainda:** empacotar no `.pkg` público, `mitm_runtime_available=true` no
`layer7d` de release, instalar/activar no `.254`.
**Draft:** [`drafts/mitm-packaging-20.10/`](drafts/mitm-packaging-20.10/)
**Evidência S8 OFF+binário:** `docs/tests/evidence/20260809T050000Z-s8-runtime-present-off-54/`

## Checklist antes de pedir GO produto

1. [x] S1 inline lab Opção A PASS — evidência `20260809T045500Z-s1-inline-opcao-a-54`
2. [x] S2/S3/S4 lab PASS
3. [x] S6 nota escrita (`s6-s7-lab-notas-opcao-a.md`)
4. [x] S7 auditoria runtime lab PASS (PoC; ver notas)
5. [x] IPC DECIDE mock smoke PASS (`ipc-decide-mock.py`; desenho integração `layer7d` ainda futuro)
6. [x] `pkg-plist` + rc script draft (flag default OFF) —
   [`drafts/mitm-packaging-20.10/`](drafts/mitm-packaging-20.10/)
   (**fora** do port vivo; sem merge)
7. [x] Rollback doc + smoke S8 com runtime presente e OFF —
   evidência `20260809T050000Z-s8-runtime-present-off-54`;
   [`drafts/mitm-packaging-20.10/rollback-runtime-present-off.md`](drafts/mitm-packaging-20.10/rollback-runtime-present-off.md)
8. [ ] **GO produto humano** explícito

Só então: passo plano **20.10**, GI2/GI3 runtime, release.
