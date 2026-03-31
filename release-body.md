## Layer7 v1.7.5 — Fix: botão "Aplicar" nos Perfis Rápidos não funcionava

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### O que foi corrigido (v1.7.5)

**Bug: botão "Aplicar" nos Perfis Rápidos não abria o modal nem criava a política**

**Causa raiz:**
`json_encode($prof_id)` e `json_encode($prof_name)` produzem strings JSON com aspas duplas literais — por exemplo, `"youtube"`. Estas strings eram inseridas directamente no atributo `onclick="..."` sem escaping HTML:

```html
<!-- HTML gerado (errado): -->
<button onclick="l7showProfileModal("youtube", "YouTube");">Aplicar</button>
```

O browser HTML5 termina o valor do atributo na primeira `"` não escapada, truncando o handler para `l7showProfileModal(` (JavaScript inválido → SyntaxError silencioso). Clicar no botão não fazia absolutamente nada.

**Correcção:**
`htmlspecialchars(json_encode(...), ENT_QUOTES)` converte `"` em `&quot;` no HTML. O browser converte `&quot;` de volta para `"` antes de executar o JavaScript:

```html
<!-- HTML gerado (correcto): -->
<button onclick="l7showProfileModal(&quot;youtube&quot;, &quot;YouTube&quot;);">Aplicar</button>
<!-- JS executado: l7showProfileModal("youtube", "YouTube") ✓ -->
```

---

### Instalação / Actualização

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

Ou directamente:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.5.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.5/pfSense-pkg-layer7-1.7.5.pkg
pkg add /tmp/pfSense-pkg-layer7-1.7.5.pkg
```

---

### Verificação pós-instalação

```sh
layer7d -V   # deve mostrar 1.7.5
```

Aceder a **Services → Layer7 → Políticas → Perfis Rápidos** e clicar "Aplicar" num perfil — o modal deve abrir correctamente.

---

### Rollback

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.7.4.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.7.4/pfSense-pkg-layer7-1.7.4.pkg
pkg delete pfSense-pkg-layer7 && pkg add /tmp/pfSense-pkg-layer7-1.7.4.pkg
```

---

### Compatibilidade

- pfSense CE 2.7.x e 2.8.x
- FreeBSD 14.x / 15.x
- Retrocompatível com configurações existentes
