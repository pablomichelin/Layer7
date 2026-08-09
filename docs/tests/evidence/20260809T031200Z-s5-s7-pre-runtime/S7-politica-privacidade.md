# Política S7 — privacidade MITM (pré-runtime)

| Campo | Valor |
|-------|--------|
| Critério | **S7** — sem payload desencriptado em disco por defeito; log = metadados |
| Data | `2026-08-09` |
| Pacote referência | **`1.9.38`** |
| Runtime | **AUSENTE** — não há processo que possa dump de payload |
| Evidência | este ficheiro + `02-code-privacy-refs.txt` + extracto S8 (sem tlsproxy) |

## Política canónica (obrigatória em qualquer PoC futura)

| # | Regra | Estado actual (`1.9.38`) |
|---|--------|---------------------------|
| P1 | **Default:** não gravar payload TLS desencriptado em disco | Cumprido por **ausência** de runtime |
| P2 | Logs MITM (quando existirem) = **metadados** (SNI, IP, policy, timestamps) | Desenho + contrato IPC: JSON **sem** payload |
| P3 | Chaves CA / privadas **fora do git**; permissões restritas (ex. `0600`) | Gestão CA 20.8; segredos não versionados |
| P4 | Opt-in explícito para qualquer captura de payload (lab only; nunca default prod) | **Proibido** como default; só GO lab futuro |
| P5 | `mitm_effective=false` ⇒ zero terminação TLS ⇒ zero payload MITM | **PASS** lab S8 (`mitm_effective=false`, sem tlsproxy) |
| P6 | Squid / ssl_bump **rejeitado** (caminho alternativo de dump) | Permanente |

## Checklist de auditoria (PoC futura — template)

Quando existir `layer7-tlsproxy`, antes de GO lab:

- [ ] Confirmar paths de log sem body/payload
- [ ] `find` em `/var/db/layer7` / tmp sem dumps TLS
- [ ] CA files mode `0600` / owner root
- [ ] Config default sem flag “store_payload”
- [ ] Documentar no `run_id` da PoC

## Veredicto S7 (fase pré-runtime)

**PASS documental + verificação de ausência de runtime**  
(política escrita; appliance S8 sem tlsproxy/Squid; IPC proíbe payload).

**Não autoriza `20.10`.** Auditoria com runtime continua obrigatória na PoC.
