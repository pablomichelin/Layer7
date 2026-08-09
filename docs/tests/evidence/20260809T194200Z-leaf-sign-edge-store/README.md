# Leaf assinado pela CA esperada + store Edge/Chromium

| Campo | Valor |
|-------|--------|
| **Run** | `20260809T194200Z` |
| **Veredicto** | **PASS** (assinatura); store máquina **correcto por desenho + evidência B+D** |
| **Live `.24`** | store RO não relido (sem credencial lab válida neste host) |

## 1) Leaf assinado pela CA esperada?

| Teste | Resultado |
|-------|-----------|
| `openssl verify -CAfile expected-ca.crt leaf.pem` | **OK** |
| `openssl verify -CAfile wrong-ca.crt leaf.pem` | **FAIL** (`unable to get local issuer`) |
| Issuer CN | = subject da CA esperada |
| Conclusão | Leaf **é** assinado pela CA carregada no tlsproxy |

Builder smoke `2026-08-09T15:4x` (mint D1).

Nota: leaf actual **não** emite AKI; a CA tem SKI. Path building por issuer name + verify OK; AKI seria melhoria, não bloqueador.

## 2) Edge/Chromium usa o store de máquina?

### Desenho do lab (código)

`tmp-release/phase-d-24-edge.ps1` / fase D:

- `certutil -addstore Root` → **`LocalMachine\Root`**
- validação pós-install: `Cert:\LocalMachine\Root` por thumbprint
- Edge headless **sem** `--ignore-certificate-errors`

### Política Chromium / Edge

Chrome Certificate Verifier (Edge partilha a stack Chromium) em Windows **consome** CAs adicionadas explicitamente em:

- **Local Machine → Trusted Root Certification Authorities**
- Local Machine → Enterprise/Group Policy Root
- Current User → Trusted Root (também)

Fonte: [Chrome Root Store FAQ](https://chromium.googlesource.com/chromium/src/+/HEAD/net/data/ssl/chrome_root_store/faq.md)  
Edge 112+: Microsoft Root Store / verifier próprio, mas **continua a honrar roots locais** nesses stores.

### Evidência B+D (comportamento real)

| Observação | Interpretação |
|------------|----------------|
| CA Phase D instalada em `LocalMachine\Root` com thumb `25EDD8…` = CA do appliance | Store máquina correcto |
| Edge erro = `ERR_SSL_KEY_USAGE_INCOMPATIBLE` (**não** `ERR_CERT_AUTHORITY_INVALID`) | Edge **aceitou** a CA como âncora; falhou no KU do peer |
| Se o store máquina fosse ignorado | Esperar-se-ia `AUTHORITY_INVALID` / interstitial de autoridade |

**Conclusão store:** o caminho lab (`LocalMachine\Root`) é o correcto para Edge/Chromium; na B+D o browser usou essa trust. O bloqueio foi peer=CA (KU), não store errado.

## Checklist próximo B+D

1. Confirmar `openssl verify -CAfile ca.crt leaf.pem` OK após mint D1 no appliance.  
2. Confirmar CA só em `LocalMachine\Root` (e opcionalmente inventariar `CurrentUser\Root` = sem Layer7 duplicado).  
3. Edge sem bypass; erro esperado se falhar: HTML Layer7 (não KEY_USAGE / AUTHORITY_INVALID).
