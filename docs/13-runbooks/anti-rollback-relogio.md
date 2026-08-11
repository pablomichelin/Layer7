# Runbook — Anti-rollback de relógio (passo 30.6 / ADR-0033)

**Versão do mecanismo:** a partir de `1.9.51`  
**Gate:** GA3  
**Estado persistente:** `/var/db/layer7/clock-mark.json`  
**Limiar:** retrocesso **> 86400 s (1 dia)** ⇒ estado temporal **suspeito**

---

## O que faz

O `layer7d` grava o maior timestamp já observado. Se o relógio do appliance
recuar **mais de 1 dia** face a essa marca, a licença passa a **inválida para
enforce** (modo monitor) e é emitido um evento de auditoria
`license_clock_suspect`.

Retrocessos pequenos (ajuste NTP / VM) são **tolerados** sem alarme.

O daemon **nunca** termina por causa deste estado.

---

## Sintomas

- GUI Definições → Licença: badge **Relógio suspeito**
- `layer7d --license-status` → `valid=0`, `clock_suspect=1`
- `/var/log/layer7-events.log` / syslog: `license_clock_suspect`
- Enforce Layer7 desligado (monitor-only) apesar de `.lic` aparentemente válido

---

## Recuperação (erro honesto — N6)

Executável pelo operador **sem** contactar suporte:

1. Corrigir a hora do sistema (NTP recomendado):
   ```sh
   ntpdate -u pool.ntp.org   # ou o procedimento NTP do pfSense
   date                      # confirmar hora correcta
   ```
2. Reiniciar o serviço:
   ```sh
   service layer7d restart
   ```
3. Verificar:
   ```sh
   layer7d --license-status
   # esperado: valid=1 e clock_suspect=0 (com .lic válido e hora correcta)
   ```

Quando `now` volta a ser ≥ marca (ou o retrocesso residual ≤ 1 dia), o estado
suspeito **desaparece** sozinho — não é preciso apagar o ficheiro.

---

## Limites honestos (RR-4) — GA3.9

Este mecanismo **encarece** o truque casual do `date`. **Não** contém o T2
técnico. Evasões conhecidas:

| Evasão | Porquê |
|--------|--------|
| Root apaga `/var/db/layer7/clock-mark.json` | Verificação local sob controlo do adversário (R-A) |
| Relógio **congelado/atrasado desde a instalação** | A marca detecta *retrocesso*, não um clock que nunca avançou |

**Fecho real do vector:** AP3 (check-in obrigatório/assinado) — o servidor
conhece a hora real. Sem AP3, `30.6` é higiene temporal.

---

## Rollback de pacote (N7 / GA3.8)

Instalar `.pkg` anterior (`1.9.50` ou abaixo) **ignora** o ficheiro de estado
sem erro. O ficheiro pode permanecer em disco; versões antigas não o lêem.

```sh
# exemplo — rollback lab a partir de 1.9.51
fetch -o /tmp/pfSense-pkg-layer7-1.9.50.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.50/pfSense-pkg-layer7-1.9.50.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.50.pkg
service layer7d onestart
```

---

## Referências

- [ADR-0033](../03-adr/ADR-0033-anti-rollback-relogio.md)
- [Plano anti-pirataria §30.6](../02-roadmap/plano-antipirataria-anti-tamper.md)
- [Gates GA3](../09-blocking/plano-gates-antipirataria.md)
