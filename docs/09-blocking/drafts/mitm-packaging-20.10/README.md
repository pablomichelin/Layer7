# Draft packaging — `layer7-tlsproxy` (prep 20.10)

**Estado:** `DRAFT` — **NÃO** mergeado no port vivo  
**Checklist:** [`../../prep-20.10-checklist.md`](../../prep-20.10-checklist.md) item 6  
**Data:** `2026-08-09`

## Objectivo

Preparar a forma correcta de empacotar o helper **quando** existir GO produto:

1. entradas propostas de `pkg-plist`;
2. `rc.d` FreeBSD com **default OFF**;
3. flag de build `WITH_LAYER7_TLSPROXY=no` (default).

## Proibições (ainda)

| Proibido | Motivo |
|----------|--------|
| Copiar estes ficheiros para `package/pfSense-pkg-layer7/files/` | Sem GO produto |
| Acrescentar linhas ao `pkg-plist` vivo | Sem GO produto |
| Ligar build no `Makefile` do port | Sem GO produto |
| `mitm_runtime_available=true` no `layer7d` de release | Sem GO produto |
| Instalar/activar no `.254` | Produção |

## Conteúdo deste draft

| Ficheiro | Papel |
|----------|--------|
| [`pkg-plist.fragment`](pkg-plist.fragment) | Linhas a acrescentar **só** após GO |
| [`Makefile.snippet.md`](Makefile.snippet.md) | Flag `WITH_LAYER7_TLSPROXY?=no` |
| [`files/usr/local/etc/rc.d/layer7-tlsproxy`](files/usr/local/etc/rc.d/layer7-tlsproxy) | rc.d default `NO` |
| [`rollback-runtime-present-off.md`](rollback-runtime-present-off.md) | Rollback + critérios S8 |
| [`smoke-s8-runtime-present-off.sh`](smoke-s8-runtime-present-off.sh) | Smoke lab `.54` (binário presente, OFF) |

## Contrato de arranque (futuro)

Mesmo com binário no `.pkg`:

1. `layer7_tlsproxy_enable=NO` por defeito (rcvar);
2. upgrade **nunca** liga o serviço;
3. `mitm.enabled` continua **intenção**; `mitm_effective` só true com runtime + GO + S1–S8;
4. Squid permanece rejeitado.

## Smoke deste bloco

Evidência lab: [`../../../tests/evidence/20260809T050000Z-s8-runtime-present-off-54/`](../../../tests/evidence/20260809T050000Z-s8-runtime-present-off-54/)
