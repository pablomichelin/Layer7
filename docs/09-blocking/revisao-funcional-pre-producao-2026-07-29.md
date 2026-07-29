# Revisão funcional pré-produção — 2026-07-29

## Escopo e regra de segurança

Revisão estática end-to-end do caminho:

```text
configuração -> captura pcap -> fluxo nDPI -> decisão -> tabela PF -> estado PF
```

O appliance `192.168.100.254` permaneceu intocado. Nenhum pacote foi instalado,
nenhum modo foi activado e nenhuma regra de produção foi alterada.

## Resultado executivo

O candidato `_26` ainda não deveria seguir para produção. Foram encontrados
defeitos capazes de explicar os dois sintomas reportados:

- **não bloqueia:** ida e volta da mesma conversa recebiam hashes diferentes,
  dividindo a classificação nDPI; além disso, uma conexão já presente na state
  table do PF continuava activa depois do `pfctl -T add`;
- **bloqueia tudo:** match normal de aplicação/categoria usava `psrc`, cuja
  regra corta todo o tráfego externo da origem, mesmo sem quarentena explícita.

O primeiro bloco de correcção está no candidato `1.8.11_27`. Suite completa,
build nDPI e validação do pacote passaram no builder FreeBSD 15; artefacto
interno `pfSense-pkg-layer7-1.8.11_27.pkg`,
`SHA256=8eae978d8d3120f050be21d2fdf511aacbf03ba0ad2c9c350c15100818ed5388`.

## Achados corrigidos no `_27`

| ID | Severidade | Defeito | Correcção | Teste/gate |
|----|------------|---------|-----------|------------|
| FP-001 | Crítica | Hash de fluxo não era bidireccional; reverse tuple normalmente caía em outro bucket | Canonicalização `(IP,porta)` antes do hash | `test_capture_flow_key.c`; build nDPI |
| FP-002 | Crítica | App/categoria normal colocava cliente em `layer7_psrc_N`, cortando toda a Internet | App normal usa destino em `layer7_pdst_N`; `psrc` só com `quarantine_origin=true` | C/PHP + two-client |
| FP-003 | Crítica | Inserir IP em tabela não invalidava estado PF já estabelecido | Kill selectivo: par cliente/destino; host inteiro apenas em quarentena; destino inteiro no legado global | appliance, sessão persistente |
| FP-004 | Alta | `allow` manual/excepção era ignorado pelo callback e blacklist podia inserir novo destino | Callback agora respeita allow explícito; garantia contra entradas já existentes continua em FP-017 | `test_policy_decide.c` |
| FP-005 | Alta | SNI blacklist adicionava IP sem registar TTL no cache | `enforce_cache_add()` também no caminho SNI | appliance + expiração |
| FP-006 | Alta | Self-heal aceitava tabelas globais prontas mesmo se a tabela `pdst/psrc` que falhou continuasse ausente | Verificação da tabela solicitada; helper valida todas as tabelas scoped quando o modelo está activo | shell lint + falha induzida no lab |
| FP-007 | Média | Parser DNS sobrescrevia o QNAME original com o nome do RR/CNAME | QNAME original preservado para política, blacklist e hint | resposta DNS com CNAME no lab |
| FP-008 | Média | Expiração de fluxos não era chamada em pacotes de fluxo já classificado | Sweep movido antes do retorno de `classified` | estatística `cap_expired` |

## Achado corrigido no `_28`

| ID | Severidade | Defeito | Correcção | Teste/gate |
|----|------------|---------|-----------|------------|
| FP-017 | Crítica | Allow/excepção não vencia entrada PF prévia; a solução óbvia com `pass quick` poderia furar regras nativas do pfSense | `pallow_N` + tabelas de excepção e `match/tag L7ALLOW`; somente blocks Layer7 ignoram a marca; `except_ips` UT1 usa origem negativa `blsrc_N` | C/PHP/shell/smoke/build PASS (`62dd9ae5…9dc6`); `pfctl -nf`, regra nativa negativa e two-client pendentes |

## Riscos ainda abertos — não declarar produção pronta

### Achado FP-018 — corrigido no candidato `_29`

O pré-gate read-only provou que `block ... inet on <if>` é rejeitado pelo PF.
O código passa a gerar `block ... on <if> inet`; a forma corrigida passou em
`pfctl -nf -` no appliance sem carregar regras. `_28` fica supersedido.

| ID | Severidade | Limitação/risco | Próxima decisão |
|----|------------|-----------------|-----------------|
| FP-009 | Crítica | `legacy_global` continua default e um destino pode afectar todos os clientes | Gate two-client e migração controlada para `scoped_hybrid` |
| FP-010 | Alta | Pipeline de captura/enforcement é IPv4-only | Definir suporte IPv6 ou bloquear rollout em redes dual-stack |
| FP-011 | Alta | Builder FreeBSD 15 e appliance observado pfSense Plus/FreeBSD 16 têm ABI diferentes | Instalar primeiro passivo e validar binário/bibliotecas |
| FP-012 | Alta | Tabela de fluxos tenta só 64 posições e rejeita colisão sem contador visível | Adicionar métrica de drops/pressão e estratégia de eviction |
| FP-013 | Alta | DNS hint é global, limitado e associa um único hostname por IP compartilhado | Testar CDN multi-host; priorizar SNI quando disponível |
| FP-014 | Alta | ECH, DoH hardcoded e QUIC podem ocultar host; bloqueio por IP pode atingir serviços compartilhados do mesmo cliente | UX de limitação, perfis de fallback e testes CDN |
| FP-015 | Média | `config_parse.c` ainda usa busca textual sensível à estrutura/ordem do JSON | Migrar para parser JSON real em bloco separado |
| FP-016 | Alta | Falta evidência física two-client, expiração, state kill, allow seguro e rollback | Build `_28` passou; executar roteiro começando passivo |

## Critérios mínimos antes de activar

1. Build `_28` com nDPI e PHP no builder FreeBSD — **PASS**,
   SHA256 `62dd9ae5…9dc6`.
2. Instalação no appliance com `enabled=false`, sem regras/tabelas populadas.
3. Monitor com captura real: ida/volta classificadas e sem bloqueio.
4. `scoped_hybrid` com dois clientes: A bloqueado, B permitido.
5. Política app normal não corta navegação não relacionada de A.
6. Quarentena explícita corta A e somente A.
7. Sessão já aberta é interrompida após decisão e volta após TTL/reconexão.
8. Allow manual/excepção prevalece sobre blacklist e não ultrapassa block
   nativo controlado do pfSense.
9. Stop, downgrade de licença e volta a monitor deixam tabelas vazias.

## Rollback

- não activar `_28` antes dos gates;
- se o gate falhar, voltar para `_24` em modo passivo;
- executar `layer7-pfctl flush-all`, resync do filtro e confirmar tabelas vazias;
- preservar logs e artefactos do teste para diagnóstico;
- não publicar nem alterar o default para `scoped_hybrid`.
