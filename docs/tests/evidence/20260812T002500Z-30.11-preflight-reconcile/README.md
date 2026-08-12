# Evidência — reconciliação preflight `30.11` (sem cut)

**RUNID:** `20260812T002500Z` (aprox. fecho local)  
**Escopo:** reconciliar código local não commitado ↔ deploy `.244`; testes;
gitignore `content/`; docs GA4.11/GA4.12  
**Proibido neste bloco:** cut espelho · DNS/CF · GitHub assets · `.254` ·
commit/push · Bearer

## Resultado

| Item | Resultado |
|------|----------|
| Código content-auth / routes / index / compose / nginx | **MATCH** local = `.244` (cksum idênticos) |
| Snapshot `content/.../current` | **MATCH** (incl. tar.gz 31169229 B) |
| `.env.example` | Local **à frente** só com comentários `CONTENT_BLACKLISTS_DIR` (não sincronizado no deploy — sem alteração de serviço) |
| `npm test` (backend) | **116 pass / 0 fail** |
| Varredura segredos (ficheiros preflight + novos docs) | **0 hits** |
| Gitignore snapshot | tarball/manifest/sig/pem **ignorados**; `.gitkeep` preservado; ficheiros **intactos no disco** |
| Cut espelho / GA4.10 | **não executado** |

## STOP — teste autenticado (neste RUNID: não feito)

**Supersedido:** smoke autenticado feito depois em
[`../20260812T003214Z-30.11-auth-get-254/`](../20260812T003214Z-30.11-auth-get-254/)
(**PASS** — 200/200 + 401). Este RUNID permanece só como reconciliação
código/snapshot; o STOP abaixo é histórico deste instante:

Para fecho de caminho autenticado ponta a ponta faltava (quando o gestor autorizar):

| Campo | Valor |
|-------|--------|
| Destino | `https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt` (+ `.sig`) |
| Esperado com token | HTTP **200** + manifesto ~823 B |
| Onde correr | Preferência lab; se appliance: **no host** que já tem `content-subscription.json` (token **não** exportar) |
| Método | `curl` local no host com Bearer em memória; evidência só códigos/tamanhos |
| Fora de escopo até GO | update-blacklists completo na `.254` |
