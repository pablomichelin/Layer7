# Empacotamento pfSense e Distribuição

## 1. Objetivo

Definir como o projeto será empacotado, publicado e instalado com o menor risco operacional possível.

---

## 2. Regra principal

O GitHub é **fonte de código e releases**, mas não deve ser tratado como método mágico de instalação “direta” no pfSense.

O fluxo inicial recomendado é:

1. desenvolver no Cursor;
2. versionar no GitHub;
3. buildar o pacote em ambiente apropriado;
4. gerar artefato `.txz`;
5. instalar o artefato no pfSense de laboratório;
6. validar;
7. só então publicar release.

---

## 3. Estrutura do pacote

### Nome sugerido
- `pfSense-pkg-layer7`

### Componentes esperados
- `Makefile`
- `pkg-descr`
- `pkg-plist`
- diretório `files/`
- XML do pacote
- páginas PHP
- daemon/binário
- rc script
- arquivos de privilégio

---

## 4. Estratégia de build

## Fase inicial
Build manual/reproduzível em host builder controlado.

## Fase intermediária
Scripts de build versionados.

## Fase madura
Pipeline de release com geração de artefato.

---

## 5. Estratégia de instalação da V1

### Recomendada
- artefato `.txz`
- checksum
- instrução de `pkg add`
- rollback documentado

### Não recomendada na V1
- repositório alternativo para produção geral
- instalação improvisada de dependências direto no firewall sem trilha
- build em firewall de produção

---

## 6. Versionamento sugerido

### SemVer
- `0.1.0` primeiro release utilizável
- `0.1.x` correções
- `0.2.0` novas features pequenas
- `1.0.0` quando o produto já estiver estável e publicável com mais confiança

---

## 7. Estrutura sugerida de release

Cada release deve conter:
- source tag
- changelog
- release notes
- artefato `.txz`
- checksum
- nota de compatibilidade
- instrução de instalação
- instrução de rollback

---

## 8. Política de upgrade

Cada upgrade precisa responder:
- mudou formato de config?
- precisa migrar dados?
- precisa parada do serviço?
- precisa reboot?
- rollback é suportado?

---

## 9. Política de rollback

Toda release precisa informar:
- versão anterior suportada para rollback
- se a config é compatível
- se é necessário remover o pacote
- se há limpeza de estado runtime

---

## 10. Dependências do pacote

Separar claramente:

### Dependências obrigatórias
Sem elas o pacote não funciona.

### Dependências opcionais
Melhoram funcionalidade, mas não devem quebrar a V1 se estiverem ausentes.

---

## 11. Política de publicação no GitHub

### Releases
Publicar release somente quando:
- package build validado;
- docs atualizadas;
- testes críticos executados;
- changelog pronto.

### Source of truth
O GitHub deve conter:
- código
- docs
- scripts
- histórico de decisões
- issues
- release notes

---

## 12. Estrutura sugerida para artefatos de release

```text
release-0.1.0/
├─ pfSense-pkg-layer7-0.1.0.txz
├─ pfSense-pkg-layer7-0.1.0.sha256
├─ RELEASE-NOTES-0.1.0.md
├─ INSTALL-0.1.0.md
└─ ROLLBACK-0.1.0.md
```

---

## 13. Política de compatibilidade

Toda release deve declarar:
- versão do pfSense CE validada;
- builder usado;
- limites conhecidos;
- dependências externas testadas.

---

## 14. O que fazer antes de pensar em repositório próprio

Só considerar repo próprio depois que existir:
- build consistente;
- versão estável;
- estratégia de compatibilidade;
- política de upgrade;
- rollback testado;
- documentação sólida.

Antes disso, o `.txz` é mais simples e menos traiçoeiro.

