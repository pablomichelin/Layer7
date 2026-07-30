# Matriz — compatibilidade pfSense CE / Plus / FreeBSD

**Data:** 2026-07-29  
**Estado:** CANDIDATO INTERNO EM VALIDAÇÃO — evidência física limitada a observação read-only documentada.

---

## 1. Declaração de produto vs observação real

| Dimensão | Documentação / decisão congelada | Observação real (2026-07-29) | Gap |
|----------|----------------------------------|------------------------------|-----|
| Alvo comercial | **pfSense CE** exclusivo | Appliance lab: **pfSense Plus 26.03.1** | Validar CE antes de claim CE-only |
| FreeBSD base | Builder: **15.0-RELEASE** | Appliance: **16.0-CURRENT** | ABI/linkagem não provada em FB16 |
| nDPI | 5.x vendorizado estático (`libndpi.a`) | Build `_31` OK no builder FB15 | Versão exacta depende do builder |
| PHP GUI | pfSense 2.x package API | Plus 26.03 — compatível em smoke histórico | Regressão GUI não auditada nesta rodada |

---

## 2. Matriz de compatibilidade por componente

| Componente | pfSense CE (alvo) | pfSense Plus 26.x (observado) | FreeBSD 15 (builder) | FreeBSD 16 (appliance) | macOS (dev) |
|------------|-------------------|-------------------------------|----------------------|------------------------|-------------|
| Pacote `.pkg` | **Claim** SSOT | Instalado `_24` read-only OK | Build PASS `_31` | **Não instalado `_31`** | N/A |
| `layer7d` binário | Esperado | `_24` parado/passivo | Compilado + link nDPI | **PENDENTE** | N/A |
| libpcap capture | `DLT_EN10MB`/`DLT_RAW` | Observado captures=0 (passivo) | PASS smoke | **PENDENTE** | N/A |
| PF rules inject | `layer7.inc` hooks | Ruleset válido `_24` | `pfctl -nf` sintético `_29` | Parser completo **PENDENTE** | N/A |
| NAT anchor DNS | `natrules/layer7_nat` | Não activado (passivo) | Lint shell PASS | **PENDENTE** | N/A |
| Unbound anti-DoH | Hook `config.xml` | N/A nesta rodada | PHP lint SKIP macOS | **PENDENTE** | N/A |
| License `.lic` | Ed25519 OpenSSL | Válida no appliance | N/A | N/A | N/A |
| Testes unitários C | N/A | N/A | N/A | N/A | **PASS** `run-local.sh` |
| Testes PHP simulados | N/A | N/A | Builder | Appliance | **SKIP** (sem PHP) |

**Legenda:** PASS = evidência presente | PENDENTE = sem gate | SKIP = ambiente inadequado

---

## 3. Matriz de risco por plataforma

| ID | Risco | CE | Plus | FB15 | FB16 | Mitigação |
|----|-------|----|------|------|------|-----------|
| FP-011 | ABI binário `layer7d` + `libndpi.a` | ? | Observado | Build OK | **Não testado** | Instalar `_31` passivo primeiro |
| AUD-004 | `pfctl` syntax divergente | Baixo | FP-018 corrigido `_29` | Sintético OK | Read-only OK | `pfctl -nf` ruleset completo |
| AUD-005 | API PHP pfSense (`get_real_interface`) | Esperado | Usado em Plus | N/A | N/A | `test_interface_normalization.php` |
| FP-010 | IPv6 dual-stack | Limitação V1 | Idem | Idem | Idem | Documentar / bloquear rollout |
| BG-028 | Trust chain `.pkg` | Não activo | Não activo | N/A | N/A | Instalação manual SHA256 |

---

## 4. Dependências de linkagem (Makefile port)

```
layer7d ← main.c, config_parse.c, policy.c, enforce.c, license.c,
          blacklist.c, bl_config.c, allowlist.c, log_store.c, capture.c
        ← /usr/local/lib/libndpi.a -lpcap -lm -lpthread
        ← /usr/lib/libcrypto.a (OpenSSL base FreeBSD)
```

| Dependência | Versão pinada | Risco |
|-------------|---------------|-------|
| nDPI estático | Builder `/usr/local/lib/libndpi.a` (5.x) | Rebuild obrigatório ao mudar FB major |
| libpcap | Sistema FreeBSD | Baixo |
| libcrypto | Base FreeBSD | Baixo |
| PHP | Pacote pfSense | Médio em upgrades GUI |

---

## 5. Versões reportadas

| Artefacto | `_24` publicada | `_31` candidato |
|-----------|-----------------|-----------------|
| PORTVERSION | 1.8.11 | 1.8.11 |
| PORTREVISION | 24 | 31 |
| `layer7d -V` | `1.8.11_24` (via `version.str`) | `1.8.11_31` |
| GUI `pkg query` | `1.8.11_24` | N/A (não instalado) |
| SHA256 `.pkg` | `1d5573f0…2c7818` | `dc5118dd…453e33` |

---

## 6. Recomendações de gate por plataforma

1. **Primeiro:** pfSense Plus 26.03.1 (lab existente) — instalação passiva `_31`, `pfctl -nf`, captura monitor.
2. **Segundo:** pfSense CE referência (VM dedicada) — repetir two-client e smoke Caminho A/B.
3. **Terceiro:** Confirmar binário FB16 (`ldd /usr/local/sbin/layer7d`, smoke `layer7d -t`).
4. **Não fazer:** activar enforce em produção até matriz two-client PASS em ambas plataformas alvo.

---

## Referências

- `CORTEX.md` (candidatos `_25`–`_31`, observação Plus/FB16)
- `docs/08-lab/builder-freebsd.md`
- `docs/04-package/validacao-lab.md`
- FP-011, BG-052
