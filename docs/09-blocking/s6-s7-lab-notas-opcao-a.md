# S6 / S7 — notas lab (pós Opção A)

## S6 — ECH

**Estado lab:** não exercitado. ClientHello no path netns usa SNI em claro
(`lab-inline.test`). ECH exigiria clientes/servidores ECH; fora do PoC actual.
**Previsível:** com ECH, decisão por SNI falha → política deve **bypass/fail-open
selectivo** (alinhado ao contrato 20.9), nunca bloquear LAN inteira.

## S7 — privacidade com runtime

**Auditoria PoC no `.54`:**
- Respostas JSON/HTML sem body de origem persistido
- Upstream stub local; logs só metadados (`orig_dst`, SNI policy)
- Cert/key só em `/opt/layer7-poc/lab-certs/` (0600 key)
- Sem gravação de payload desencriptado em disco no código PoC

**PASS lab** para política S7 do helper; auditoria produto 20.10 continua futura.
