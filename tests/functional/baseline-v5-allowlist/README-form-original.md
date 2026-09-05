# Fixture manual auxiliar — não é evidência de baseline

`form-original.html` foi reconstruído à mão para referência visual rápida.
**Não** usar como prova de paridade FormData.

A paridade de payload usa render real de
`layer7_allowlist.php` pinado nesta pasta (`f36e0a42…`) via
`harness-allowlist-view/render-parity.php` + `test_allowlist_payload.js` (FormData jsdom).
